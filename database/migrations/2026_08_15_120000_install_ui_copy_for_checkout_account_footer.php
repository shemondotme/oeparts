<?php

use App\Models\Setting;
use App\Services\UiCopyInstaller;
use Illuminate\Database\Migrations\Migration;

/**
 * Fast-follow to 2026_04_24_120000_install_ui_copy_for_search_cart_nav —
 * expands the same ui.* text-override mechanism to the checkout_/account_/
 * footer_ prefixes, deliberately deferred out of that original migration
 * since checkout/account copy is more sensitive and the smaller 344-key
 * surface (Site Copy Library) needed to prove out first. See
 * memory/project_ui_copy_text_override_gap.md's BACKLOG section.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new UiCopyInstaller)->installCheckoutAccountFooterSettings();
    }

    public function down(): void
    {
        $keys = UiCopyInstaller::installedCheckoutAccountFooterUiKeyPrefixes();
        foreach ($keys as $k) {
            Setting::where('group', 'ui')->where('key', $k)->delete();
        }
    }
};
