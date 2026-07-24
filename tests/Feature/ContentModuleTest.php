<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function makeMediaFile(): MediaFile
    {
        return MediaFile::create([
            'uploaded_by' => Admin::first()->id,
            'file_name'   => 'test.png',
            'file_path'   => 'media/test.png',
            'file_url'    => '/storage/media/test.png',
            'mime_type'   => 'image/png',
            'size'        => 2048,
        ]);
    }

    public function test_media_list_renders_with_records_present(): void
    {
        // Regression: recordUrl built a 'view' route MediaFileResource never
        // registers -> RouteNotFoundException during table render, but ONLY
        // once the table had rows. Always test lists WITH data.
        $this->makeMediaFile();

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\ListMediaFiles::class)
            ->loadTable()
            ->assertOk()
            ->assertSee('test.png');
    }

    public function test_media_edit_page_renders(): void
    {
        // Regression: TextInput::copyMessage() doesn't exist -> 500.
        $file = $this->makeMediaFile();

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\EditMediaFile::class, ['record' => $file->id])
            ->assertOk();
    }

    private function makeNestedSection(): Section
    {
        // Locale-map shape matches what every real Section row (and the
        // SectionResource's structured Repeater/Tabs editor) actually
        // produces — every translatable leaf carries all 5 locale keys
        // (unset locales are null, not absent), so a no-op save round-trips
        // byte-for-byte instead of the editor "filling in" missing keys.
        $ml = fn (string $en) => ['en' => $en, 'de' => null, 'lt' => null, 'fr' => null, 'es' => null];

        return Section::create([
            'type'      => 'how_it_works',
            'location'  => 'homepage',
            'title'     => ['en' => 'How It Works'],
            // Real home sections carry nested structures — exactly what broke
            // both the view page (foreach on string) and the KeyValue editor.
            'content'   => [
                'eyebrow'     => $ml('Process'),
                'headline'    => $ml('Three steps'),
                'subheadline' => ['en' => null, 'de' => null, 'lt' => null, 'fr' => null, 'es' => null],
                'steps'    => [
                    ['icon' => 'magnifying-glass', 'step_number' => 1, 'title' => $ml('Search'), 'description' => $ml('Enter your OEM number')],
                    ['icon' => 'shopping-cart', 'step_number' => 2, 'title' => $ml('Order'), 'description' => $ml('Checkout securely')],
                ],
            ],
            'is_active'  => true,
            'status'     => 'published',
            'sort_order' => 1,
        ]);
    }

    public function test_section_view_and_edit_render_with_nested_content(): void
    {
        $section = $this->makeNestedSection();

        Livewire::test(\App\Filament\Resources\SectionResource\Pages\ViewSection::class, ['record' => $section->id])
            ->assertOk();
        Livewire::test(\App\Filament\Resources\SectionResource\Pages\EditSection::class, ['record' => $section->id])
            ->assertOk();
    }

    public function test_section_view_renders_legacy_string_title(): void
    {
        // Real seeded rows store title as a bare JSON string ("Hero"), not a
        // locale map — the view page must tolerate both shapes.
        $section = Section::create([
            'type'       => 'hero',
            'location'   => 'homepage',
            'title'      => 'Hero',
            'content'    => ['headline' => 'x'],
            'is_active'  => true,
            'status'     => 'published',
            'sort_order' => 0,
        ]);

        Livewire::test(\App\Filament\Resources\SectionResource\Pages\ViewSection::class, ['record' => $section->id])
            ->assertOk();
    }

    public function test_section_json_editor_round_trips_nested_content(): void
    {
        $section = $this->makeNestedSection();
        $original = $section->content;

        Livewire::test(\App\Filament\Resources\SectionResource\Pages\EditSection::class, ['record' => $section->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($original, $section->refresh()->content, 'saving without edits must not corrupt nested content');
    }

    public function test_section_types_match_storefront_components_exactly(): void
    {
        $bladeTypes = collect(glob(resource_path('views/components/sections/*.blade.php')))
            ->map(fn (string $path): string => basename($path, '.blade.php'))
            ->sort()
            ->values()
            ->all();

        // Blade filenames are kebab-case; Section::TYPES keys are the stored
        // snake_case DB values — home.blade.php converts one to the other at
        // include time, so the comparison must apply the same conversion.
        $adminTypes = collect(array_keys(Section::TYPES))
            ->map(fn (string $type): string => str_replace('_', '-', $type))
            ->sort()
            ->values()
            ->all();

        $this->assertSame($bladeTypes, $adminTypes,
            'Section::TYPES must mirror resources/views/components/sections/ one-to-one — the homepage silently skips unknown types');
    }

    public function test_blog_edit_renders_with_inline_tag_creation_select(): void
    {
        $post = \App\Models\BlogPost::create([
            'title'     => ['en' => 'Audit Post'],
            'slug'      => 'audit-post',
            'content'   => ['en' => 'Body'],
            'status'    => 'draft',
            'author_id' => Admin::first()->id,
        ]);

        Livewire::test(\App\Filament\Resources\BlogPostResource\Pages\EditBlogPost::class, ['record' => $post->id])
            ->assertOk();
    }

    public function test_upload_action_creates_a_media_record(): void
    {
        Storage::fake('public');

        Livewire::test(\App\Filament\Resources\MediaFileResource\Pages\ListMediaFiles::class)
            ->loadTable()
            ->callTableAction('upload', data: [
                'file'     => UploadedFile::fake()->image('brake-diagram.png', 200, 200),
                'alt_text' => 'Brake diagram',
            ]);

        $record = MediaFile::where('alt_text', 'Brake diagram')->first();
        $this->assertNotNull($record);
        $this->assertSame('image/png', $record->mime_type);
        Storage::disk('public')->assertExists($record->file_path);
    }

    /**
     * Regression: pages.created_by is a NOT NULL foreign key (migration
     * 2026_03_26_100033) with no form field for it anywhere and no
     * mutateFormDataBeforeCreate hook — every single CMS page creation
     * crashed with a raw SQLSTATE NOT NULL constraint failure instead of
     * saving, confirmed live (same bug class as CreateCoupon).
     */
    public function test_page_creation_sets_created_by_from_the_authenticated_admin(): void
    {
        $admin = auth('admin')->user();

        Livewire::test(\App\Filament\Resources\PageResource\Pages\CreatePage::class)
            ->fillForm([
                'slug' => 'test-cms-page',
                'title' => ['en' => 'Test CMS Page'],
                'content' => ['en' => 'Body'],
                'status' => 'draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = \App\Models\Page::where('slug', 'test-cms-page')->first();
        $this->assertNotNull($page, 'Page creation failed');
        $this->assertSame($admin->id, $page->created_by);
    }
}
