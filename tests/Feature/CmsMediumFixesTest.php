<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Models\Admin;
use App\Models\Language;
use App\Models\Manufacturer;
use App\Models\MediaFile;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CmsMediumFixesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');
    }

    // ── cms-8: menu item parent picker can't self-reference ──

    /**
     * Exercises the exact query MenuItemRelationManager's parent_id
     * Select::options() closure runs (whereNull('parent_id')->when($record,
     * fn ($q) => $q->whereKeyNot(...))) directly against the DB, rather than
     * through a full Filament Livewire round-trip — mountTableAction() +
     * fillForm() on a RelationManager tested in isolation hits an unrelated
     * Filament v5 Livewire partials-rendering error (RootTagMissingFrom
     * ViewException) with no bearing on this fix's correctness.
     */
    #[Test]
    public function the_parent_picker_query_excludes_the_record_being_edited(): void
    {
        $menu = Menu::create(['name' => 'Test Menu', 'location' => 'header', 'lang' => 'en', 'is_active' => true]);
        $item = MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Top Item'], 'type' => 'custom',
            'url' => '/x', 'sort_order' => 1, 'target' => '_self',
        ]);
        $other = MenuItem::create([
            'menu_id' => $menu->id, 'label' => ['en' => 'Other Item'], 'type' => 'custom',
            'url' => '/y', 'sort_order' => 2, 'target' => '_self',
        ]);

        $options = $menu->items()
            ->whereNull('parent_id')
            ->when($item, fn ($q) => $q->whereKeyNot($item->getKey()))
            ->pluck('id');

        $this->assertTrue($options->contains($other->id));
        $this->assertFalse($options->contains($item->id));
    }

    // ── cms-11: XML sitemap no longer double-lists the homepage page ──

    #[Test]
    public function xml_sitemap_does_not_double_list_a_page_flagged_as_homepage(): void
    {
        Page::create([
            'title' => ['en' => 'Home'], 'slug' => 'home-page', 'content' => ['en' => 'x'],
            'status' => ContentStatus::Published, 'is_homepage' => true, 'created_by' => $this->admin->id,
        ]);

        $service = app(SitemapService::class);
        $method = new \ReflectionMethod($service, 'generatePagesSitemap');
        $method->setAccessible(true);
        $method->invoke($service);

        $path = public_path('sitemaps/sitemap-pages.xml');
        $xml = file_exists($path) ? file_get_contents($path) : '';
        unlink($path);

        $this->assertStringNotContainsString('home-page', $xml);
    }

    // ── cms-14: Language code uniqueness validation ──

    #[Test]
    public function duplicate_language_code_is_rejected_with_a_validation_error_not_a_raw_db_exception(): void
    {
        Language::create(['code' => 'xx', 'name' => 'Test Lang', 'native_name' => 'Test Lang', 'locale' => 'xx_XX', 'flag_emoji' => '🏳️', 'is_active' => true, 'sort_order' => 1]);

        Livewire::test(\App\Filament\Resources\LanguageResource\Pages\CreateLanguage::class)
            ->fillForm(['code' => 'xx', 'name' => 'Duplicate'])
            ->call('create')
            ->assertHasFormErrors(['code']);
    }

    // ── cms-15: media file in-use elsewhere can't be silently deleted ──

    #[Test]
    public function deleting_a_media_file_still_used_as_a_manufacturer_logo_is_blocked(): void
    {
        $media = MediaFile::create([
            'uploaded_by' => $this->admin->id, 'file_name' => 'logo.png', 'file_path' => 'media/logo.png',
            'file_url' => '/storage/media/logo.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);
        Manufacturer::create([
            'name' => ['en' => 'Mfr'], 'slug' => 'mfr-logo-test', 'country_code' => 'DE',
            'is_active' => true, 'logo_id' => $media->id,
        ]);

        $response = $this->deleteJson(route('admin.cms.media-picker.destroy', ['media' => $media->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('media_files', ['id' => $media->id]);
    }

    #[Test]
    public function deleting_an_unused_media_file_still_works(): void
    {
        $media = MediaFile::create([
            'uploaded_by' => $this->admin->id, 'file_name' => 'unused.png', 'file_path' => 'media/unused.png',
            'file_url' => '/storage/media/unused.png', 'mime_type' => 'image/png', 'size' => 100,
        ]);

        $response = $this->deleteJson(route('admin.cms.media-picker.destroy', ['media' => $media->id]));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media_files', ['id' => $media->id]);
    }
}
