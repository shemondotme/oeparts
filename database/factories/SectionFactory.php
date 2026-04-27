<?php

namespace Database\Factories;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'type'      => $this->faker->word(),
            'location'  => $this->faker->randomElement(SectionLocation::cases())->value,
            'title'     => [
                'en' => $this->faker->sentence(),
                'de' => $this->faker->sentence(),
                'lt' => $this->faker->sentence(),
                'fr' => $this->faker->sentence(),
                'es' => $this->faker->sentence(),
            ],
            'content'   => [
                'en' => ['headline' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                'de' => ['headline' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                'lt' => ['headline' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                'fr' => ['headline' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                'es' => ['headline' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
            ],
            'status'    => SectionStatus::Published,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
