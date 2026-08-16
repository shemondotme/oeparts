<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * store.currency_position was seeded as 'left' on installations older than
 * SettingsSeeder's later fix to 'after', but StoreSettings'/
 * GeneralBrandSettings' Select field only ever declared 'before'/'after'
 * options ('left' has no other reader anywhere in the app, confirmed via
 * grep — it was stale/dead data). This went unnoticed while the field
 * rendered in isolation; the Store & Commerce/Brand & Storefront settings
 * reorg (Phase 3/4) merges it onto a single Livewire component whose
 * save() validates the WHOLE form together, so this one legacy value
 * blocked saving the *entire* General & Brand page for any installation
 * still carrying it — confirmed live: typing an unrelated Site Name
 * change and clicking Save silently failed, jumping to the Regional
 * Defaults tab with "The selected symbol Position is invalid."
 *
 * The seeder fix alone only helps fresh installs; this migration carries
 * existing installations' data forward the same way
 * 2026_08_15_000002_rename_contact_rate_limit_per_minute_to_form_rate_limit
 * did for its own legacy-value gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('group', 'store')
            ->where('key', 'currency_position')
            ->where('value', 'left')
            ->update(['value' => 'after']);

        Cache::forget('settings.store');
        Cache::forget('settings.general');
    }

    public function down(): void
    {
        // Not reversible — 'left' was always dead/invalid data, never a
        // legitimate prior state worth restoring.
    }
};
