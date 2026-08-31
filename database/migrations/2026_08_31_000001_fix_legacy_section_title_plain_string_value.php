<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SectionsSeeder assigned `title` as a plain PHP string ('Hero', 'Trust
 * Bar', ...) directly into a column the Section model casts as `array` —
 * Eloquent's array cast then JSON-encodes just that string ("Hero"), not
 * the {"en": "Hero", ...} translatable shape every admin form field for
 * this column actually expects. Confirmed live on every one of the 14
 * seeded homepage sections: the storefront rendered fine (the list-page
 * label helper already tolerated a plain string as a fallback), but the
 * admin Edit form's `title.en` field read `null` from a JSON string with
 * no "en" key — every section's title looked permanently blank the
 * moment an admin opened it to edit, and saving without noticing would
 * have overwritten the real label with an empty one.
 *
 * The seeder fix alone only helps fresh installs; this migration carries
 * existing installations' data forward the same way
 * 2026_08_16_000001_fix_legacy_store_currency_position_value did for its
 * own legacy-value gap. Only touches rows whose stored title is still a
 * bare JSON string — an admin who already re-saved a section's title
 * (correctly, as a translatable array) is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sections')->select('id', 'title')->orderBy('id')->each(function ($row) {
            $decoded = json_decode((string) $row->title, true);

            if (! is_string($decoded) || $decoded === '') {
                return;
            }

            DB::table('sections')->where('id', $row->id)->update([
                'title' => json_encode([
                    'en' => $decoded,
                    'de' => null,
                    'lt' => null,
                    'fr' => null,
                    'es' => null,
                ]),
            ]);
        });
    }

    public function down(): void
    {
        // Not reversible — a bare JSON string was always malformed data
        // for this column, never a legitimate prior state worth restoring.
    }
};
