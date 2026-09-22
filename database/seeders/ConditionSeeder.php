<?php

namespace Database\Seeders;

use App\Models\Condition;
use Illuminate\Database\Seeder;

class ConditionSeeder extends Seeder
{
    public function run(): void
    {
        Condition::create([
            'name' => 'New',
            'slug' => 'new',
            'bg_color' => '#DCFCE7',
            // #16A34A measured 3.0:1 against this bg (axe-core, WCAG AA
            // needs 4.5:1 for this badge's small bold text) — #166534
            // (Tailwind green-800) clears 6.5:1.
            'text_color' => '#166534',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Condition::create([
            'name' => 'Used',
            'slug' => 'used',
            'bg_color' => '#DBEAFE',
            'text_color' => '#1D4ED8',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
