<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Support\ServiceProvider;

class SettingsSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    // Previously ran 4 uncached Setting::where('group', ...)->get() queries
    // directly against the DB on every single request boot — not a
    // correctness bug, but needless DB load for values that barely ever
    // change. SettingsService::getGroup() shares the exact same 5-minute
    // "settings.{group}" cache SettingsPage::save() already invalidates on
    // write, so this now costs one DB query per group per 5 minutes
    // (cache-wide, not per-request) instead of 4 queries every request. It
    // also returns already-decrypted values, so the manual
    // is_encrypted/Crypt::decryptString handling below is gone too.
    public function boot(): void
    {
        try {
            $settings = app(SettingsService::class);

            $emailSettings = $settings->getGroup('email');

            if ($emailSettings !== []) {
                $smtpHost = $emailSettings['smtp_host'] ?? null;
                if ($smtpHost) {
                    config([
                        'mail.mailers.smtp.host' => $smtpHost,
                        'mail.mailers.smtp.port' => (int) ($emailSettings['smtp_port'] ?? 587),
                        'mail.mailers.smtp.encryption' => $emailSettings['smtp_encryption'] ?? 'tls',
                        'mail.mailers.smtp.username' => $emailSettings['smtp_username'] ?? '',
                        'mail.mailers.smtp.password' => $emailSettings['smtp_password'] ?? '',
                        'mail.from.address' => $emailSettings['from_address'] ?? config('mail.from.address'),
                        'mail.from.name' => $emailSettings['from_name'] ?? config('mail.from.name'),
                    ]);
                }

                $replyTo = $emailSettings['reply_to'] ?? null;
                if ($replyTo) {
                    // Laravel's MailManager::setGlobalAddress() unconditionally reads
                    // mail.reply_to (for every mailer resolution, not just mailables
                    // that opt in) and dereferences both 'address' and 'name' — setting
                    // only 'address' throws "Undefined array key 'name'" the moment ANY
                    // mail is sent, breaking OTP/order-confirmation/contact emails
                    // sitewide the instant an operator configures a Reply-To address.
                    // Confirmed via a live reproduction (login -> SendOtpEmail -> 500).
                    config([
                        'mail.reply_to.address' => $replyTo,
                        'mail.reply_to.name' => config('mail.from.name'),
                    ]);
                }
            }

            $authSettings = $settings->getGroup('auth');

            if ($authSettings !== []) {
                if ($googleClientId = ($authSettings['google_client_id'] ?? null)) {
                    config([
                        'services.google.client_id' => $googleClientId,
                        'services.google.client_secret' => $authSettings['google_client_secret'] ?? null,
                    ]);
                }

                if ($facebookClientId = ($authSettings['facebook_client_id'] ?? null)) {
                    config([
                        'services.facebook.client_id' => $facebookClientId,
                        'services.facebook.client_secret' => $authSettings['facebook_client_secret'] ?? null,
                    ]);
                }
            }

            $securitySettings = $settings->getGroup('security');
            if ($securitySettings !== []) {
                $sessionLifetime = $securitySettings['session_lifetime'] ?? null;
                if ($sessionLifetime) {
                    config(['session.lifetime' => (int) $sessionLifetime]);
                }
            }

            $performanceSettings = $settings->getGroup('performance');
            if ($performanceSettings !== []) {
                $retryAfter = $performanceSettings['queue_retry_after'] ?? null;
                if ($retryAfter) {
                    config(['queue.connections.redis.retry_after' => (int) $retryAfter]);
                }
            }
        } catch (\Exception $e) {
            // DB may not exist yet during install/migration
        }
    }
}
