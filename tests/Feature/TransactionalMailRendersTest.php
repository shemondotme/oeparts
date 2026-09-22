<?php

namespace Tests\Feature;

use App\Mail\AbandonedCartReminder;
use App\Mail\BackupFailedMail;
use App\Mail\BulkUpdateAppliedMail;
use App\Mail\ContactReply;
use App\Mail\FatalErrorMail;
use App\Mail\NewsletterCampaignEmail;
use App\Mail\NewsletterConfirmation;
use App\Mail\PartInquiryReceived;
use App\Mail\PasswordReset;
use App\Mail\WelcomeEmail;
use App\Models\ContactMessage;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Models\PartInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 15 (Email/Notification & Queue Failure Handling) — "confirm
 * transactional emails land". No real Mailtrap/SMTP credentials exist in
 * this environment, so the practical, CI-safe equivalent is proving each
 * Mailable's Blade view actually renders without throwing. This codebase
 * has already been bitten by exactly this gap before (see RefundJobsTest's
 * own "Option P" comment: Mail::fake() intercepts a Mailable before its
 * view ever renders, so it can never catch a broken route() call or
 * missing variable inside that view) — these 10 Mailables were the only
 * ones left with literally zero render coverage anywhere (every existing
 * test for them only ever went through Mail::fake()).
 */
class TransactionalMailRendersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function backup_failed_mail_renders(): void
    {
        $html = (new BackupFailedMail('full', 'Disk full', 42, now()->toIso8601String()))->render();

        $this->assertStringContainsString('Disk full', $html);
    }

    #[Test]
    public function bulk_update_applied_mail_renders(): void
    {
        $html = (new BulkUpdateAppliedMail('Jane Admin', 'Regenerated SEO meta', 150))->render();

        $this->assertStringContainsString('Jane Admin', $html);
        $this->assertStringContainsString('150', $html);
    }

    #[Test]
    public function fatal_error_mail_renders(): void
    {
        $html = (new FatalErrorMail([
            'message' => 'Undefined array key "foo"', 'file' => '/app/Foo.php', 'line' => 42,
            'exception_class' => 'ErrorException', 'url' => 'https://oeparts.test/admin', 'trace' => '#0 {main}',
        ]))->render();

        $this->assertStringContainsString('Undefined array key', $html);
    }

    #[Test]
    public function abandoned_cart_reminder_renders(): void
    {
        $html = (new AbandonedCartReminder([
            'items' => [['oem_number' => 'BRAKE-PAD-01', 'quantity' => 2, 'price_at_add' => '25.00']],
            'subtotal' => '50.00',
        ], 'en', 'Jane Doe'))->render();

        $this->assertStringContainsString('BRAKE-PAD-01', $html);
    }

    #[Test]
    public function contact_reply_renders(): void
    {
        $message = ContactMessage::factory()->create();

        $html = (new ContactReply($message, 'Thanks for reaching out, here is the answer.'))->render();

        $this->assertStringContainsString('Thanks for reaching out', $html);
    }

    #[Test]
    public function newsletter_campaign_email_renders(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create(['unsubscribe_token' => 'test-token-123']);
        $campaign = NewsletterCampaign::factory()->create(['html_content' => '<p>Big sale this week.</p>']);
        $recipient = NewsletterCampaignRecipient::create([
            'campaign_id' => $campaign->id, 'subscriber_id' => $subscriber->id,
            'email' => $subscriber->email, 'status' => 'pending',
        ]);

        $html = (new NewsletterCampaignEmail($campaign, $recipient))->render();

        $this->assertStringContainsString('Big sale this week', $html);
    }

    #[Test]
    public function newsletter_confirmation_renders(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $html = (new NewsletterConfirmation($subscriber, 'https://oeparts.test/en/newsletter/confirm/token123'))->render();

        $this->assertStringContainsString('token123', $html);
    }

    #[Test]
    public function part_inquiry_received_renders(): void
    {
        $inquiry = PartInquiry::factory()->create(['oem_number' => '04L115399F']);

        $html = (new PartInquiryReceived($inquiry))->render();

        $this->assertStringContainsString('04L115399F', $html);
    }

    #[Test]
    public function password_reset_renders(): void
    {
        $html = (new PasswordReset('user@example.com', 'https://oeparts.test/en/reset-password/token456'))->render();

        $this->assertStringContainsString('token456', $html);
    }

    #[Test]
    public function welcome_email_renders(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $html = (new WelcomeEmail($user))->render();

        $this->assertStringContainsString('Jane Doe', $html);
    }
}
