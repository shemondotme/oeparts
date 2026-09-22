<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ConditionSeeder's "New" condition row (and the ConditionResource form's
 * default, and CartController's fallback) all seeded text_color as
 * #16A34A on bg_color #DCFCE7 — 3.0:1 contrast, below WCAG AA's 4.5:1
 * minimum for this badge's small bold text. Found live via an axe-core
 * accessibility sweep (Phase 6, bulletproof-testing initiative) across
 * search results, manufacturer pages, and the cart/checkout item summary
 * — every page rendering this badge inherited the same failing contrast.
 *
 * The seeder/resource/controller fixes alone only help fresh installs;
 * this carries existing installations' already-seeded row forward the
 * same way 2026_08_16_000001_fix_legacy_store_currency_position_value did
 * for its own legacy-value gap. Guarded on the exact old value so an
 * admin who already customized this condition's colors is left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conditions')
            ->where('slug', 'new')
            ->where('text_color', '#16A34A')
            ->update(['text_color' => '#166534']);
    }

    public function down(): void
    {
        DB::table('conditions')
            ->where('slug', 'new')
            ->where('text_color', '#166534')
            ->update(['text_color' => '#16A34A']);
    }
};
