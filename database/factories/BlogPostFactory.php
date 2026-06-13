<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id'    => Category::factory(),
            'title'          => ['en' => $title, 'de' => $title],
            'slug'           => fake()->unique()->slug(),
            'excerpt'        => ['en' => fake()->sentence(), 'de' => fake()->sentence()],
            'content'        => ['en' => fake()->paragraphs(3, true), 'de' => fake()->paragraphs(3, true)],
            'author_id'      => Admin::factory(),
            'status'         => ContentStatus::Draft,
            'meta_title'     => null,
            'meta_description' => null,
            'published_at'   => null,
            'last_reviewed_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => ContentStatus::Published,
            'published_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ContentStatus::Draft,
        ]);
    }
}
