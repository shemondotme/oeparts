<?php

namespace App\Services;

/**
 * Applies DB-backed settings (SMTP, OAuth client credentials, session
 * lifetime, queue retry_after) onto the in-memory config() array.
 *
 * Extracted out of SettingsSyncServiceProvider::boot(), which only ran
 * this once per process — correct under classic PHP-FPM (a fresh process,
 * and fresh boot(), per request) but silently stale for the entire
 * lifetime of any long-running process that boots once and then serves
 * many requests/jobs: the existing supervisor-managed queue:work worker
 * (deploy/supervisor/oeparts-queue-worker.conf) already has this problem
 * today for any queued mail job reading SMTP config, and it would also
 * affect Laravel Octane's persistent HTTP workers if ever adopted (a
 * runtime evaluated but not adopted in this SEO program — see the plan's
 * §5.6.5 Octane section). Called from three boundaries now instead of
 * once at boot: SyncRuntimeSettingsIntoConfig middleware (per HTTP
 * request), a Queue::before() listener (per queued job), and still once
 * at boot() itself (covers one-off artisan commands that are neither).
 */
class RuntimeSettingsSyncService
{
    public function sync(SettingsService $settings): void
    {
        try {
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
            // DB may not exist yet during install/migration.
        }
    }
}
