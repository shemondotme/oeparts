<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * contact.facebook_url/linkedin_url were "quick link" duplicates of
 * social_links.facebook_url/linkedin_url per ContactSettings' own helper
 * text ("For full social link management ... use the dedicated Social
 * Links settings page") — but the storefront footer and the homepage's
 * Organization JSON-LD sameAs array were both wired to the contact.* copy
 * instead, orphaning social_links.* (6 platforms + show/hide + icon style)
 * entirely. Rewired the footer + JSON-LD to social_links.*; this migration
 * carries any operator-entered legacy contact.* value across (same
 * existing-target-wins logic as 2026_07_12_000001's tax VAT number fix)
 * before retiring the two orphans. Idempotent + reversible (rule #42);
 * two-row op (rule #44).
 */
return new class extends Migration
{
    private array $pairs = [
        ['legacyGroup' => 'contact', 'legacyKey' => 'facebook_url', 'targetGroup' => 'social_links', 'targetKey' => 'facebook_url'],
        ['legacyGroup' => 'contact', 'legacyKey' => 'linkedin_url', 'targetGroup' => 'social_links', 'targetKey' => 'linkedin_url'],
    ];

    public function up(): void
    {
        foreach ($this->pairs as $pair) {
            $legacy = DB::table('settings')
                ->where('group', $pair['legacyGroup'])
                ->where('key', $pair['legacyKey'])
                ->first();

            if ($legacy === null) {
                continue;
            }

            $targetRow = DB::table('settings')
                ->where('group', $pair['targetGroup'])
                ->where('key', $pair['targetKey'])
                ->first();
            $legacyValue = (string) ($legacy->value ?? '');

            if ($targetRow !== null && trim((string) $targetRow->value) === '' && trim($legacyValue) !== '') {
                DB::table('settings')->where('id', $targetRow->id)->update(['value' => $legacyValue]);
            }

            DB::table('settings')->where('id', $legacy->id)->delete();
        }

        Cache::forget('settings.contact');
        Cache::forget('settings.social_links');
    }

    public function down(): void
    {
        // Nothing safe to restore — the legacy orphan rows are intentionally gone.
    }
};
