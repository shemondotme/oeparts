<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Filament\Resources\BlogPostResource\Pages\EditBlogPost;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Support\SeoFormFields;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Page;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The on-page analysis in the Page and Blog editors: the focus keyword saves, and the panel reacts to it. */
class ContentAnalysisEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function page(array $attrs = []): Page
    {
        return Page::create($attrs + [
            'title' => ['en' => 'Brake pads guide'], 'slug' => 'brake-pads-guide',
            'content' => ['en' => '<p>Choosing brake pads is easy.</p><h2>Brake pads and rotors</h2><p>See <a href="/en/brands">brands</a>.</p>'],
            'status' => ContentStatus::Published, 'published_at' => now()->subDay(), 'is_homepage' => false,
            'created_by' => Admin::factory()->create()->id,
        ]);
    }

    public function test_the_analysis_panel_is_on_the_page_and_blog_editors(): void
    {
        $page = $this->page();
        $post = BlogPost::create(['title' => ['en' => 'T'], 'slug' => 't', 'content' => ['en' => '<p>x</p>'], 'status' => ContentStatus::Draft, 'author_id' => Admin::factory()->create()->id]);

        Livewire::test(EditPage::class, ['record' => $page->id])->assertOk()->assertSee('Content analysis')->assertSee('Focus keyword');
        Livewire::test(EditBlogPost::class, ['record' => $post->id])->assertOk()->assertSee('Content analysis')->assertSee('Focus keyword');
        Livewire::test(CreatePage::class)->assertOk()->assertSee('Content analysis');
    }

    public function test_the_focus_keyword_is_saved_per_language(): void
    {
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->fillForm(['focus_keyword.en' => 'brake pads', 'focus_keyword.de' => 'Bremsbeläge'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('brake pads', $page->focus_keyword['en']);
        $this->assertSame('Bremsbeläge', $page->focus_keyword['de']);
    }

    public function test_the_analysis_reacts_to_the_focus_keyword(): void
    {
        $page = $this->page();

        $component = Livewire::test(EditPage::class, ['record' => $page->id]);
        $component->assertSee('No focus keyword yet');

        $component->fillForm(['focus_keyword.en' => 'brake pads', 'meta_title.en' => 'Brake pads: the guide'])
            ->assertSee('Focus keyword: &quot;brake pads&quot;', false)
            ->assertSee('The search title starts with the keyword.')
            ->assertSee('The keyword is in a subheading.');
    }

    public function test_editing_a_page_with_existing_content_never_breaks_on_the_rich_editor_state(): void
    {
        // The rich editor's state is HTML when stored but an array while the form is open; the
        // fallbacks and the analysis must cope with both without an "Array to string" failure.
        $page = $this->page();

        Livewire::test(EditPage::class, ['record' => $page->id])->assertOk()->assertHasNoFormErrors();
    }

    public function test_plain_text_handles_html_a_tiptap_document_and_nothing(): void
    {
        $this->assertSame('Hello world', SeoFormFields::plainText('<p>Hello</p><p>world</p>'));
        $this->assertSame('Hello world', SeoFormFields::plainText(['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'world']]],
        ]]));
        $this->assertSame('', SeoFormFields::plainText(null));
    }
}
