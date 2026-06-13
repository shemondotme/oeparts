<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title'            => ['en' => $title, 'de' => $title],
            'slug'             => fake()->unique()->slug(),
            'content'          => ['en' => fake()->paragraphs(3, true), 'de' => fake()->paragraphs(3, true)],
            'featured_image_id' => null,
            'status'           => ContentStatus::Published,
            'meta_title'       => null,
            'meta_description' => null,
            'is_homepage'      => false,
            'is_header'        => false,
            'is_footer'        => false,
            'created_by'       => null,
            'published_at'     => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function homepage(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_homepage' => true,
        ]);
    }
}
