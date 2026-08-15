<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * contact.rate_limit_per_minute and cart.rate_limit_per_minute shared the
 * exact same bare key name — invisible while ContactSettings/CartSettings
 * were separate pages, but the Store & Commerce settings reorg (Phase 4)
 * merges both groups onto ONE Livewire component whose fillForm()/
 * persistChanges()/getFactoryDefaults() flatten every group's rows into a
 * single key-keyed array. With both fields present, the two Filament
 * TextInputs (Cart Rules tab vs Customer Service tab) would have silently
 * been bound to the SAME underlying property — editing one field's value
 * in the browser would visibly change the other, and only one Setting row
 * would ever actually be written on save. Renaming the contact-form side
 * (the narrower use case) to form_rate_limit_per_minute — cart's stays
 * unchanged since app/Http/Controllers/Api/CartController.php reads it
 * directly, no reason to touch the more heavily-referenced one. Carries
 * forward any operator-set legacy value; same pattern as
 * 2026_07_11_000001's group move.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = DB::table('settings')->where('group', 'contact')->where('key', 'rate_limit_per_minute')->first();

        if ($legacy === null) {
            return;
        }

        $existing = DB::table('settings')->where('group', 'contact')->where('key', 'form_rate_limit_per_minute')->exists();

        if ($existing) {
            DB::table('settings')->where('id', $legacy->id)->delete();
        } else {
            DB::table('settings')->where('id', $legacy->id)->update(['key' => 'form_rate_limit_per_minute']);
        }

        Cache::forget('settings.contact');
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('group', 'contact')
            ->where('key', 'form_rate_limit_per_minute')
            ->update(['key' => 'rate_limit_per_minute']);

        Cache::forget('settings.contact');
    }
};
