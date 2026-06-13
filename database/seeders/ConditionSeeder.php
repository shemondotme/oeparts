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
            'text_color' => '#16A34A',
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
