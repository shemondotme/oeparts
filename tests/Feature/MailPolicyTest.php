<?php

namespace Tests\Feature;

use App\Services\Instance\SafeMode;
use App\Services\RuntimeSettingsSyncService;
use App\Services\SettingsService;
use Illuminate\Mail\Message;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Notifications;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Phase 4.2: a copy of the shop cannot mail a real customer by any route. Mail::fake() could not prove this
 * (it replaces the very layer that cancels), so these tests send through the real mailer into the real
 * array transport and look at what actually arrived.
 */
class MailPolicyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mail.default' => 'array', 'instance.mail.allowlist' => '']);
        SafeMode::resetLogMemory();
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    private function transport(): ArrayTransport
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return $transport;
    }

    /** @return array<int, Email> */
    private function delivered(): array
    {
        return array_map(fn ($sent) => $sent->getOriginalMessage(), $this->transport()->messages()->all());
    }

    /** @param  array<int, string>  $to */
    private function send(array $to, string $subject = 'Your order', array $cc = [], array $bcc = []): void
    {
        Mail::mailer('array')->raw('Hello, secret body 12345', function (Message $message) use ($to, $subject, $cc, $bcc): void {
            $message->to($to)->subject($subject);
            if ($cc !== []) {
                $message->cc($cc);
            }
            if ($bcc !== []) {
                $message->bcc($bcc);
            }
        });
    }

    /** @return array<int, string> */
    private function addresses(Email $email, string $field): array
    {
        $getter = 'get'.ucfirst($field);

        return array_map(fn ($a) => $a->getAddress(), $email->{$getter}());
    }

    // ---- production -----------------------------------------------------------------------------------

    #[Test]
    public function production_delivers_everything_exactly_as_before(): void
    {
        $this->mode('production');

        $this->send(['customer@shop.test'], 'Your order', ['cc@shop.test'], ['bcc@shop.test']);

        $this->assertCount(1, $this->delivered());
        $mail = $this->delivered()[0];
        $this->assertSame('Your order', $mail->getSubject());
        $this->assertSame(['customer@shop.test'], $this->addresses($mail, 'to'));
        $this->assertSame(['cc@shop.test'], $this->addresses($mail, 'cc'));
        $this->assertSame(['bcc@shop.test'], $this->addresses($mail, 'bcc'));
    }

    #[Test]
    public function a_missing_mode_is_production_for_mail_too(): void
    {
        $this->mode('');

        $this->send(['customer@shop.test']);

        $this->assertCount(1, $this->delivered());
    }

    // ---- quarantine -----------------------------------------------------------------------------------

    #[Test]
    public function quarantine_delivers_nothing_not_even_to_an_allow_listed_address(): void
    {
        config(['instance.mail.allowlist' => 'dev@example.com']);
        $this->mode('quarantine');

        $this->send(['dev@example.com']);
        $this->send(['customer@shop.test']);

        $this->assertCount(0, $this->delivered());
    }

    #[Test]
    public function a_typo_in_the_mode_cancels_mail_like_quarantine(): void
    {
        Log::spy();
        $this->mode('stagging');

        $this->send(['customer@shop.test']);

        $this->assertCount(0, $this->delivered());
    }

    // ---- staging --------------------------------------------------------------------------------------

    #[Test]
    public function staging_with_no_allow_list_delivers_nothing(): void
    {
        $this->mode('staging');

        $this->send(['customer@shop.test'], 'Your order', ['cc@shop.test']);

        $this->assertCount(0, $this->delivered());
    }

    #[Test]
    public function staging_delivers_to_the_allow_list_only_and_marks_the_subject(): void
    {
        config(['instance.mail.allowlist' => 'dev@example.com,@team.example']);
        $this->mode('staging');

        $this->send(['dev@example.com'], 'Your order');
        $this->send(['anna@team.example'], 'Invoice');

        $mails = $this->delivered();
        $this->assertCount(2, $mails);
        $this->assertSame('[STAGING] Your order', $mails[0]->getSubject());
        $this->assertSame('[STAGING] Invoice', $mails[1]->getSubject());
    }

    #[Test]
    public function a_message_to_a_customer_and_a_developer_reaches_only_the_developer(): void
    {
        config(['instance.mail.allowlist' => 'dev@example.com']);
        $this->mode('staging');

        $this->send(['customer@shop.test', 'dev@example.com'], 'Your order', ['other-customer@shop.test'], ['boss@shop.test', 'dev@example.com']);

        $mails = $this->delivered();
        $this->assertCount(1, $mails);
        $this->assertSame(['dev@example.com'], $this->addresses($mails[0], 'to'));
        $this->assertSame([], $this->addresses($mails[0], 'cc'), 'no stray Cc header with a customer in it');
        $this->assertSame(['dev@example.com'], $this->addresses($mails[0], 'bcc'));
        $this->assertStringNotContainsString('customer@shop.test', $mails[0]->toString());
        $this->assertStringNotContainsString('boss@shop.test', $mails[0]->toString());
    }

    #[Test]
    public function the_allow_list_is_matched_exactly_not_by_a_substring(): void
    {
        config(['instance.mail.allowlist' => 'dev@example.com,@team.example']);
        $this->mode('staging');

        $this->send(['xdev@example.com']);
        $this->send(['dev@example.com.evil.test']);
        $this->send(['anna@team.example.evil.test']);
        $this->send(['anna@subteam.example']);

        $this->assertCount(0, $this->delivered());
    }

    #[Test]
    public function the_staging_marker_is_never_added_twice(): void
    {
        config(['instance.mail.allowlist' => 'dev@example.com']);
        $this->mode('staging');

        $this->send(['dev@example.com'], '[STAGING] Already marked');

        $this->assertSame('[STAGING] Already marked', $this->delivered()[0]->getSubject());
    }

    // ---- notifications and queued mail take the same road ----------------------------------------------------

    #[Test]
    public function a_mail_notification_is_cancelled_on_a_copy_too(): void
    {
        $this->mode('quarantine');

        Notification::route('mail', 'customer@shop.test')->notify(new class extends \Illuminate\Notifications\Notification
        {
            public function via(): array
            {
                return ['mail'];
            }

            public function toMail(): MailMessage
            {
                return (new MailMessage)->subject('Hello')->line('Body');
            }
        });

        $this->assertCount(0, $this->delivered());
    }

    // ---- what is logged ---------------------------------------------------------------------------------------

    #[Test]
    public function a_cancelled_message_is_logged_by_subject_and_count_never_by_body_or_address(): void
    {
        $logged = [];
        Log::shouldReceive('info')->andReturnUsing(function (string $message) use (&$logged): void {
            $logged[] = $message;
        });
        $this->mode('quarantine');

        $this->send(['customer@shop.test'], 'Your order 100234');

        $this->assertCount(1, $logged);
        $this->assertStringContainsString('Your order 100234', $logged[0]);
        $this->assertStringContainsString('1 recipient', $logged[0]);
        $this->assertStringNotContainsString('secret body', $logged[0]);
        $this->assertStringNotContainsString('customer@shop.test', $logged[0]);
    }

    // ---- the mailer itself ---------------------------------------------------------------------------------------

    #[Test]
    public function the_runtime_sync_points_a_copy_at_the_log_mailer_even_with_database_smtp_settings(): void
    {
        config(['mail.default' => 'smtp']);
        $this->mode('quarantine');

        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        $this->assertSame('log', config('mail.default'));
    }

    #[Test]
    public function the_runtime_sync_leaves_the_mailer_alone_on_the_real_shop(): void
    {
        config(['mail.default' => 'smtp']);
        $this->mode('production');

        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        $this->assertSame('smtp', config('mail.default'));
    }

    #[Test]
    public function a_staging_copy_with_an_allow_list_keeps_its_mailer_so_those_addresses_are_really_reached(): void
    {
        config(['mail.default' => 'smtp', 'instance.mail.allowlist' => 'dev@example.com']);
        $this->mode('staging');

        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        $this->assertSame('smtp', config('mail.default'));
    }
}
