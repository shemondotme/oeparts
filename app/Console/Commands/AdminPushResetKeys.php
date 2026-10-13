<?php

namespace App\Console\Commands;

use App\Models\AdminPushSubscription;
use App\Services\Push\VapidKeys;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Deliberately replaces the server's web-push (VAPID) key pair.
 *
 * Needed after a restore or move onto a server with a different APP_KEY when the old
 * key could not be supplied (APP_PREVIOUS_KEYS): the stored keys are then unreadable,
 * and VapidKeys refuses to silently generate new ones because that would orphan every
 * browser subscription at once. This is the explicit, confirmed way to do it. Every
 * browser subscription is bound to the OLD public key and can never work again, so they
 * are removed; admins turn notifications on again from the bell menu.
 */
class AdminPushResetKeys extends Command
{
    protected $signature = 'oeparts:admin-push:reset-keys {--force : Skip the confirmation prompt}';

    protected $description = 'Replace the web-push key pair (needed when the stored one cannot be decrypted); removes all admin push subscriptions';

    public function handle(SettingsService $settings, VapidKeys $vapid): int
    {
        $count = AdminPushSubscription::query()->count();

        $this->warn("This replaces the web-push keys and removes {$count} admin push subscription(s). Admins must enable notifications again.");

        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Nothing changed.');

            return self::SUCCESS;
        }

        // Empty values, not deleted rows: VapidKeys generates a fresh pair the moment it
        // finds them empty, and the rows keep their "encrypted" flag.
        $settings->set('push.vapid_public_key', '');
        $settings->set('push.vapid_private_key', '');
        AdminPushSubscription::query()->delete();

        $publicKey = $vapid->publicKey();

        Log::warning('Web-push keys were reset on request; all admin push subscriptions were removed.', ['subscriptions_removed' => $count]);
        $this->info('New web-push keys generated ('.strlen($publicKey).'-character public key). '.$count.' subscription(s) removed.');

        return self::SUCCESS;
    }
}
