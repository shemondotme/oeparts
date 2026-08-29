<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Category;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BlogPosting JSON-LD had three gaps: a flat image URL instead of a sized
 * ImageObject (Google's Article rich-result guidance wants dimensions), no
 * articleSection/keywords despite BlogPost having category/tags relations,
 * and a publisher.logo hardcoded to a literal "/logo.svg" that was never
 * guaranteed to actually exist (every other consumer of the site logo
 * resolves it from general.logo_id).
 */
class BlogPostJsonLdTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): BlogPost
    {
        $admin = Admin::factory()->create();

        return BlogPost::create(array_merge([
            'title' => ['en' => 'How To Choose Brake Pads'],
            'slug' => 'how-to-choose-brake-pads-'.uniqid(),
            'content' => ['en' => 'Content body with enough words to be realistic.'],
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'author_id' => $admin->id,
        ], $overrides));
    }

    #[Test]
    public function json_ld_includes_a_sized_image_object_category_and_tags(): void
    {
        $admin = Admin::factory()->create();
        $image = MediaFile::create([
            'uploaded_by' => $admin->id, 'file_name' => 'brakes.jpg', 'file_path' => 'media/brakes.jpg',
            'file_url' => 'https://oeparts.test/storage/media/brakes.jpg', 'mime_type' => 'image/jpeg', 'size' => 1024,
        ]);
        $category = Category::create(['name' => ['en' => 'Maintenance Guides'], 'slug' => 'maintenance-guides-'.uniqid(), 'sort_order' => 0]);
        $tagA = BlogTag::create(['name' => ['en' => 'Brakes'], 'slug' => 'brakes-'.uniqid()]);
        $tagB = BlogTag::create(['name' => ['en' => 'Maintenance'], 'slug' => 'maintenance-'.uniqid()]);

        $post = $this->makePost(['featured_image_id' => $image->id, 'category_id' => $category->id]);
        $post->tags()->attach([$tagA->id, $tagB->id]);

        $response = $this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]));

        $response->assertOk();
        $response->assertSee('"@type": "ImageObject"', false);
        $response->assertSee('"width": 1200', false);
        $response->assertSee('"height": 630', false);
        $response->assertSee('"articleSection": "Maintenance Guides"', false);
        $response->assertSee('"keywords": "Brakes, Maintenance"', false);
    }

    #[Test]
    public function json_ld_publisher_logo_resolves_the_configured_site_logo_not_a_hardcoded_path(): void
    {
        \App\Models\Setting::updateOrCreate(
            ['group' => 'general', 'key' => 'logo_id'],
            ['value' => 'branding/logo.png', 'type' => 'string', 'is_encrypted' => false]
        );

        $post = $this->makePost();

        $response = $this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]));

        $response->assertSee(
            '"url": "'.\Illuminate\Support\Facades\Storage::disk('public')->url('branding/logo.png').'"',
            false
        );
        $response->assertDontSee('/logo.svg', false);
    }

    #[Test]
    public function json_ld_without_a_category_or_tags_omits_those_keys_without_error(): void
    {
        $post = $this->makePost();

        $response = $this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]));

        $response->assertOk();
        $response->assertDontSee('"articleSection"', false);
        $response->assertDontSee('"keywords"', false);
    }
}
