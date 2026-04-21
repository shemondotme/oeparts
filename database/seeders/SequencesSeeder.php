<?php

namespace Database\Seeders;

use App\Enums\SequenceType;
use App\Models\Sequence;
use Illuminate\Database\Seeder;

class SequencesSeeder extends Seeder
{
    public function run(): void
    {
        $sequences = [
            [
                'type'             => SequenceType::Order,
                'current_value'    => 0,
                'resets_monthly'   => true,
                'last_reset_month' => now()->format('Y-m'),
            ],
            [
                'type'             => SequenceType::Invoice,
                'current_value'    => 0,
                'resets_monthly'   => true,
                'last_reset_month' => now()->format('Y-m'),
            ],
            [
                'type'             => SequenceType::Rma,
                'current_value'    => 0,
                'resets_monthly'   => false,
                'last_reset_month' => null,
            ],
        ];

        foreach ($sequences as $sequence) {
            Sequence::updateOrCreate(
                ['type' => $sequence['type']],
                $sequence
            );
        }
    }
}
