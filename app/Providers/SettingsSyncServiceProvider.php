<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;

class SettingsSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            $emailSettings = Setting::where('group', 'email')->get()->keyBy('key');

            if ($emailSettings->isNotEmpty()) {
                $smtpHost = $emailSettings->get('smtp_host')?->value;
                if ($smtpHost) {
                    $smtpPassword = $emailSettings->get('smtp_password')?->value;
                    if ($smtpPassword && $emailSettings->get('smtp_password')?->is_encrypted) {
                        try {
                            $smtpPassword = Crypt::decryptString($smtpPassword);
                        } catch (\Exception $e) {
                        }
                    }

                    config([
                        'mail.mailers.smtp.host' => $smtpHost,
                        'mail.mailers.smtp.port' => (int) ($emailSettings->get('smtp_port')?->value ?? 587),
                        'mail.mailers.smtp.encryption' => $emailSettings->get('smtp_encryption')?->value ?? 'tls',
                        'mail.mailers.smtp.username' => $emailSettings->get('smtp_username')?->value ?? '',
                        'mail.mailers.smtp.password' => $smtpPassword ?? '',
                        'mail.from.address' => $emailSettings->get('from_address')?->value ?? config('mail.from.address'),
                        'mail.from.name' => $emailSettings->get('from_name')?->value ?? config('mail.from.name'),
                    ]);
                }

                $replyTo = $emailSettings->get('reply_to')?->value;
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

            $authSettings = Setting::where('group', 'auth')->get()->keyBy('key');

            if ($authSettings->isNotEmpty()) {
                $decrypt = function (?string $key) use ($authSettings): ?string {
                    $setting = $authSettings->get($key);
                    $value = $setting?->value;

                    if ($value && $setting->is_encrypted) {
                        try {
                            $value = Crypt::decryptString($value);
                        } catch (\Exception $e) {
                        }
                    }

                    return $value ?: null;
                };

                if ($googleClientId = $decrypt('google_client_id')) {
                    config([
                        'services.google.client_id' => $googleClientId,
                        'services.google.client_secret' => $decrypt('google_client_secret'),
                    ]);
                }

                if ($facebookClientId = $decrypt('facebook_client_id')) {
                    config([
                        'services.facebook.client_id' => $facebookClientId,
                        'services.facebook.client_secret' => $decrypt('facebook_client_secret'),
                    ]);
                }
            }

            $securitySettings = Setting::where('group', 'security')->get()->keyBy('key');
            if ($securitySettings->isNotEmpty()) {
                $sessionLifetime = $securitySettings->get('session_lifetime')?->value;
                if ($sessionLifetime) {
                    config(['session.lifetime' => (int) $sessionLifetime]);
                }
            }

            $performanceSettings = Setting::where('group', 'performance')->get()->keyBy('key');
            if ($performanceSettings->isNotEmpty()) {
                $retryAfter = $performanceSettings->get('queue_retry_after')?->value;
                if ($retryAfter) {
                    config(['queue.connections.redis.retry_after' => (int) $retryAfter]);
                }
            }
        } catch (\Exception $e) {
            // DB may not exist yet during install/migration
        }
    }
}
