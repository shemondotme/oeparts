<?php

namespace App\Services\Push;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;

/**
 * The server's VAPID keypair identifies this site to the browsers' push
 * services. It is generated automatically on first use and kept (encrypted) in
 * the settings table, so operators never have to create or paste keys by hand.
 *
 * Do NOT rotate it casually: every existing browser subscription is bound to
 * the public key it was created with and stops working if the key changes.
 */
class VapidKeys
{
    public function __construct(private readonly SettingsService $settings) {}

    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /**
     * False when the stored keys exist but cannot be read with this server's
     * key. Does not generate anything. Callers that would otherwise treat a
     * failed send as a dead subscription must check this first.
     */
    public function isUsable(): bool
    {
        return ! $this->isUnreadable();
    }

    private function isUnreadable(): bool
    {
        return $this->settings->isStoredSecretUnreadable('push.vapid_private_key')
            || $this->settings->isStoredSecretUnreadable('push.vapid_public_key');
    }

    /** @return array{subject: string, publicKey: string, privateKey: string} */
    public function forWebPush(): array
    {
        $keys = $this->keys();

        return [
            'subject' => $this->subject(),
            'publicKey' => $keys['public'],
            'privateKey' => $keys['private'],
        ];
    }

    /** @return array{public: string, private: string} */
    private function keys(): array
    {
        $public = (string) $this->settings->get('push.vapid_public_key', '');
        $private = (string) $this->settings->get('push.vapid_private_key', '');

        if ($public !== '' && $private !== '') {
            return ['public' => $public, 'private' => $private];
        }

        // Stored but unreadable (a restored/migrated site with a different
        // APP_KEY): do NOT generate a new pair — that would orphan every browser
        // subscription at once. Push stays off until the old key is supplied
        // (APP_PREVIOUS_KEYS) or an admin deliberately resets the keys.
        if ($this->isUnreadable()) {
            // Logged at most once an hour: this runs on every admin page render.
            if (Cache::add('push-vapid-unreadable-logged', 1, 3600)) {
                Log::critical('VAPID keys are stored but cannot be decrypted with this server\'s APP_KEY; web push is disabled. Put the old APP_KEY in APP_PREVIOUS_KEYS, or run `php artisan oeparts:admin-push:reset-keys` to replace the keys (admins then re-enable notifications).');
            }

            return ['public' => '', 'private' => ''];
        }

        // Two requests racing on a brand-new install must not each generate a
        // different pair (the loser's subscriptions would be orphaned at once).
        $lock = Cache::lock('push-vapid-generate', 15);

        try {
            $lock->block(10);
        } catch (\Throwable) {
            // Could not get the lock in time — fall through, re-read below.
        }

        try {
            $this->settings->forget('push');
            $public = (string) $this->settings->get('push.vapid_public_key', '');
            $private = (string) $this->settings->get('push.vapid_private_key', '');

            if ($public === '' || $private === '') {
                $generated = VAPID::createVapidKeys();
                $public = $generated['publicKey'];
                $private = $generated['privateKey'];

                $this->settings->set('push.vapid_public_key', $public);
                $this->settings->set('push.vapid_private_key', $private);
            }
        } finally {
            optional($lock)->release();
        }

        return ['public' => $public, 'private' => $private];
    }

    private function subject(): string
    {
        $email = (string) $this->settings->get('general.site_email', '');

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'mailto:'.$email;
        }

        return (string) config('app.url', 'https://localhost');
    }
}
