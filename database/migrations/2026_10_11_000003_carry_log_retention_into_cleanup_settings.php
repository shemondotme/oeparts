<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Until the Cleanup Engine, ONE number (security.log_retention_days, default 90) governed six log
 * tables. Each now has its own retention under the "cleanup" settings group. An admin who had
 * customised that number keeps their choice: it is copied into each of those six per-target
 * settings (the engine still enforces every target's own floor on top).
 *
 * An install that never changed the default needs nothing: the engine's own defaults (90 days for
 * the visitor logs, 365 for the admin activity log, 30 for scheduled-task logs) apply.
 *
 * Idempotent: never overwrites a per-target value that already exists.
 */
return new class extends Migration
{
    private const LEGACY_TARGETS = [
        'search_logs', 'failed_search_logs', 'login_logs', 'email_logs', 'activity_logs', 'cron_logs',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $legacy = DB::table('settings')->where('group', 'security')->where('key', 'log_retention_days')->value('value');

        if ($legacy === null || (int) $legacy === 90 || (int) $legacy < 1) {
            return; // default (or nonsense): the engine's own defaults are the right answer
        }

        foreach (self::LEGACY_TARGETS as $target) {
            $exists = DB::table('settings')->where('group', 'cleanup')->where('key', 'retention_'.$target)->exists();

            if (! $exists) {
                DB::table('settings')->insert([
                    'group' => 'cleanup',
                    'key' => 'retention_'.$target,
                    'value' => (string) (int) $legacy,
                    'type' => 'integer',
                    'is_encrypted' => false,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Left in place: the values are the admin's own choices.
    }
};
