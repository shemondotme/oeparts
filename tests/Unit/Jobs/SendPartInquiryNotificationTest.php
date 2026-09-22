<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendPartInquiryNotification;
use App\Mail\PartInquiryReceived;
use App\Models\PartInquiry;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 15 (Email/Notification & Queue Failure Handling). This job — the
 * admin-facing "a new part inquiry came in" notification — had no test
 * coverage anywhere before this, and no explicit $tries/$backoff either
 * (found alongside several sibling jobs sharing the same gap).
 */
class SendPartInquiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeInquiry(): PartInquiry
    {
        return PartInquiry::create([
            'email' => 'customer@example.com',
            'oem_number' => '04L115399F',
            'quantity' => 1,
            'urgency' => 'normal',
            'ip_address' => '127.0.0.1',
        ]);
    }

    #[Test]
    public function it_is_queued_on_default(): void
    {
        Queue::fake();

        dispatch(new SendPartInquiryNotification($this->makeInquiry()));

        Queue::assertPushedOn('default', SendPartInquiryNotification::class);
    }

    #[Test]
    public function it_notifies_the_configured_site_email(): void
    {
        Mail::fake();
        Setting::updateOrCreate(['group' => 'general', 'key' => 'site_email'], ['value' => 'admin@oeparts.test', 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('general');

        (new SendPartInquiryNotification($this->makeInquiry()))->handle();

        Mail::assertSent(PartInquiryReceived::class, fn ($mail) => $mail->hasTo('admin@oeparts.test'));
    }

    #[Test]
    public function it_skips_silently_when_no_site_email_is_configured(): void
    {
        Mail::fake();
        Setting::updateOrCreate(['group' => 'general', 'key' => 'site_email'], ['value' => '', 'type' => 'string', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('general');
        config(['mail.from.address' => '']);

        (new SendPartInquiryNotification($this->makeInquiry()))->handle();

        Mail::assertNothingSent();
    }

    #[Test]
    public function it_has_a_retry_policy(): void
    {
        $job = new SendPartInquiryNotification($this->makeInquiry());

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300, 600], $job->backoff);
    }
}
