<?php

namespace Database\Factories;

use App\Models\SectionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionVersionFactory extends Factory
{
    protected $model = SectionVersion::class;

    public function definition(): array
    {
        return [
            'section_id'     => null,
            'created_by'     => null,
            'action'         => 'updated',
            'snapshot'       => [
                'title'     => ['en' => $this->faker->sentence()],
                'content'   => ['en' => ['headline' => $this->faker->sentence()]],
                'is_active' => true,
            ],
            'change_summary' => $this->faker->sentence(),
            'created_at'     => now(),
        ];
    }
}
