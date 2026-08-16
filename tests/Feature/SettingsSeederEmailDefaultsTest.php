<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Services\RuntimeSettingsSyncService;
use App\Services\SettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression guard for a severe, previously-shipped bug found during a
 * full manual QA pass (2026-08-16): SettingsSeeder seeded
 * email.smtp_host as the literal string 'smtp.mailtrap.io' — a real,
 * externally-resolvable but uncredentialed host. RuntimeSettingsSyncService
 * applies ANY non-empty email.smtp_host onto config('mail.mailers.smtp.host'),
 * unconditionally overriding whatever .env's MAIL_HOST says (mailpit for
 * local dev). Every fresh seed of this project therefore silently broke
 * ALL outgoing mail — order confirmations, OTP codes, password resets,
 * contact replies — with no visible error until a mail job actually ran
 * and failed deep in Symfony Mailer with "530 5.7.1 Authentication
 * required" against a real (but foreign, credential-less) SMTP server.
 * Confirmed live: a real checkout's SendOrderConfirmationEmail job landed
 * in failed_jobs with exactly that exception before this fix.
 *
 * The settings PAGE field itself already had the correct default
 * (->default(null) in StoreOperationsSettings) — only the seeder's row
 * was wrong, which is why this went unnoticed: the admin UI looked fine,
 * .env looked fine, and RuntimeSettingsSyncServiceTest/
 * SyncRuntimeSettingsIntoConfigTest only ever exercise "does sync() apply
 * an EXISTING setting correctly" — none of them run the real seeder and
 * check what it leaves the mail config as.
 */
class SettingsSeederEmailDefaultsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_fresh_seed_never_overrides_the_environment_configured_mail_host(): void
    {
        config(['mail.mailers.smtp.host' => 'env-configured-host.test']);

        $this->seed(SettingsSeeder::class);
        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        // smtp_host must seed empty, so RuntimeSettingsSyncService's
        // `if ($smtpHost)` guard never fires and the environment-configured
        // mail host (.env's MAIL_HOST in a real app) wins.
        $this->assertSame('', settings('email.smtp_host'));
        $this->assertSame(
            'env-configured-host.test',
            config('mail.mailers.smtp.host'),
            'A freshly seeded smtp_host must not overwrite the environment-configured mail host — only an admin explicitly setting it should.'
        );
    }

    #[Test]
    public function a_real_order_confirmation_mailable_builds_cleanly_on_a_fresh_seed(): void
    {
        // Mail::fake() here (no real SMTP round trip, unlike the manual
        // Mailpit verification done during the original investigation) —
        // this just proves OrderConfirmation itself constructs/sends
        // without error post-seed, and that config isn't left pointing at
        // the placeholder host regardless of whether anything is actually
        // dispatched through it.
        $this->seed(SettingsSeeder::class);
        app(RuntimeSettingsSyncService::class)->sync(app(SettingsService::class));

        Mail::fake();

        $order = Order::factory()->create();
        Mail::to('customer@example.com')->send(new OrderConfirmation($order));

        Mail::assertSent(OrderConfirmation::class);
        $this->assertNotSame('smtp.mailtrap.io', config('mail.mailers.smtp.host'));
    }
}
