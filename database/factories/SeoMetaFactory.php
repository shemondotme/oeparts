<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    protected $model = SeoMeta::class;

    public function definition(): array
    {
        return [
            'metable_type'    => Page::class,
            'metable_id'      => Page::factory(),
            'meta_title'      => fake()->sentence(5),
            'meta_description' => fake()->paragraph(),
            'canonical_url'   => null,
            'og_title'        => null,
            'og_description'  => null,
            'og_image_id'     => null,
            'robots'          => 'index, follow',
        ];
    }
}
