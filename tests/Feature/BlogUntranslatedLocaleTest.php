<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A blog post written only in English is still served under /de/blog/..., /fr/blog/... with the English text
 * (trans_field() falls back). Those pages used to advertise themselves as German, French and so on in hreflang
 * and canonicalised to themselves, so Google could index five near-identical copies. They must not claim to be
 * a translation, and must point their canonical at the default-language version.
 */
class BlogUntranslatedLocaleTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $content): BlogPost
    {
        return BlogPost::create([
            'title' => ['en' => 'English only guide'],
            'slug' => 'english-only-guide-'.uniqid(),
            'content' => $content,
            'status' => ContentStatus::Published,
            'published_at' => now()->subDay(),
            'author_id' => Admin::factory()->create()->id,
        ]);
    }

    #[Test]
    public function an_untranslated_language_page_canonicalises_to_english_and_claims_no_translation(): void
    {
        $post = $this->makePost(['en' => '<p>Only the English text exists.</p>']);

        $de = $this->get(route('frontend.blog.show', ['lang' => 'de', 'slug' => $post->slug]));
        $de->assertOk();
        $en = route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]);
        $de->assertSee('<link rel="canonical" href="'.$en.'">', false);
        $de->assertSee('hreflang="en"', false);
        $de->assertSee('hreflang="x-default"', false);
        $de->assertDontSee('hreflang="de"', false);
        $de->assertDontSee('hreflang="fr"', false);
    }

    #[Test]
    public function a_real_translation_is_advertised_and_keeps_its_own_canonical(): void
    {
        $post = $this->makePost(['en' => '<p>English text.</p>', 'de' => '<p>Deutscher Text.</p>']);

        $de = $this->get(route('frontend.blog.show', ['lang' => 'de', 'slug' => $post->slug]));
        $de->assertOk();
        $own = route('frontend.blog.show', ['lang' => 'de', 'slug' => $post->slug]);
        $de->assertSee('<link rel="canonical" href="'.$own.'">', false);
        $de->assertSee('hreflang="de"', false);
        $de->assertSee('hreflang="en"', false);
        $de->assertDontSee('hreflang="fr"', false);
    }

    #[Test]
    public function the_featured_image_is_sized_and_prioritised_for_the_largest_contentful_paint(): void
    {
        $post = $this->makePost(['en' => '<p>English text.</p>']);
        $post->update(['featured_image_id' => \App\Models\MediaFile::create([
            'uploaded_by' => Admin::factory()->create()->id, 'file_name' => 'a.png', 'file_path' => 'media/a.png',
            'file_url' => 'https://oeparts.test/storage/media/a.png', 'mime_type' => 'image/png', 'size' => 10,
        ])->id]);

        $this->get(route('frontend.blog.show', ['lang' => 'en', 'slug' => $post->slug]))
            ->assertOk()
            ->assertSee('width="1200" height="630" fetchpriority="high"', false);
    }
}
