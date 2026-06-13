<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SavedView;
use App\Filament\Support\HasSavedViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasSavedViewsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private HasSavedViewsTestable $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\LanguagesSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);

        $this->admin = Admin::where('email', 'admin@oeparts.test')->firstOrFail();
        $this->actingAs($this->admin, 'admin');

        $this->instance = new HasSavedViewsTestable;
    }

    // ── SavedView Model CRUD ───────────────────────────────────────────────

    #[Test]
    public function saved_view_can_be_created(): void
    {
        $view = SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Low Stock Products',
            'filters' => ['stock' => ['value' => 'low']],
            'sort_column' => 'price',
            'sort_direction' => 'desc',
            'search' => 'brake',
        ]);

        $this->assertDatabaseHas('saved_views', [
            'id' => $view->id,
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Low Stock Products',
            'sort_column' => 'price',
            'sort_direction' => 'desc',
            'search' => 'brake',
        ]);

        $this->assertEquals(['stock' => ['value' => 'low']], $view->filters);
    }

    #[Test]
    public function saved_view_is_scoped_to_admin(): void
    {
        SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Test View',
            'filters' => [],
        ]);

        $otherAdmin = Admin::factory()->create([
            'email' => 'other@oeparts.test',
        ]);

        $this->assertEquals(1, SavedView::where('admin_id', $this->admin->id)->count());
        $this->assertEquals(0, SavedView::where('admin_id', $otherAdmin->id)->count());
    }

    #[Test]
    public function saved_view_is_scoped_to_resource(): void
    {
        SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Product View',
            'filters' => [],
        ]);

        SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Order',
            'name' => 'Order View',
            'filters' => [],
        ]);

        $this->assertEquals(1, SavedView::where('admin_id', $this->admin->id)
            ->where('resource', 'Product')->count());
        $this->assertEquals(1, SavedView::where('admin_id', $this->admin->id)
            ->where('resource', 'Order')->count());
    }

    #[Test]
    public function saved_view_can_be_deleted(): void
    {
        $view = SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Delete Me',
            'filters' => [],
        ]);

        $this->assertDatabaseHas('saved_views', ['id' => $view->id]);

        $view->delete();

        $this->assertDatabaseMissing('saved_views', ['id' => $view->id]);
    }

    #[Test]
    public function saved_view_belongs_to_admin(): void
    {
        $view = SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Product',
            'name' => 'Test',
            'filters' => [],
        ]);

        $this->assertInstanceOf(Admin::class, $view->admin);
        $this->assertEquals($this->admin->id, $view->admin->id);
    }

    // ── HasSavedViews Trait Logic ──────────────────────────────────────────

    #[Test]
    public function extract_sort_column_returns_null_when_sort_is_empty(): void
    {
        $this->instance->tableSort = null;
        $this->assertNull($this->instance->callExtractSortColumn());

        $this->instance->tableSort = '';
        $this->assertNull($this->instance->callExtractSortColumn());
    }

    #[Test]
    public function extract_sort_column_parses_column_from_sort_string(): void
    {
        $this->instance->tableSort = 'price';
        $this->assertEquals('price', $this->instance->callExtractSortColumn());

        $this->instance->tableSort = 'price:asc';
        $this->assertEquals('price', $this->instance->callExtractSortColumn());

        $this->instance->tableSort = 'created_at:desc';
        $this->assertEquals('created_at', $this->instance->callExtractSortColumn());
    }

    #[Test]
    public function extract_sort_direction_returns_null_when_sort_is_empty(): void
    {
        $this->instance->tableSort = null;
        $this->assertNull($this->instance->callExtractSortDirection());

        $this->instance->tableSort = '';
        $this->assertNull($this->instance->callExtractSortDirection());
    }

    #[Test]
    public function extract_sort_direction_defaults_to_asc_when_only_column(): void
    {
        $this->instance->tableSort = 'price';
        $this->assertEquals('asc', $this->instance->callExtractSortDirection());
    }

    #[Test]
    public function extract_sort_direction_parses_from_sort_string(): void
    {
        $this->instance->tableSort = 'price:asc';
        $this->assertEquals('asc', $this->instance->callExtractSortDirection());

        $this->instance->tableSort = 'price:desc';
        $this->assertEquals('desc', $this->instance->callExtractSortDirection());
    }

    #[Test]
    public function extract_sort_direction_returns_null_for_invalid_direction(): void
    {
        $this->instance->tableSort = 'price:invalid';
        $this->assertNull($this->instance->callExtractSortDirection());
    }

    #[Test]
    public function get_resource_name_from_class_basename(): void
    {
        $this->assertEquals('HasSavedViewsTestable', $this->instance->callGetResourceName());
    }

    #[Test]
    public function get_saved_view_options_returns_empty_when_no_views(): void
    {
        $options = $this->instance->callGetSavedViewOptions();
        $this->assertIsArray($options);
        $this->assertEmpty($options);
    }

    #[Test]
    public function get_saved_view_options_returns_only_matching_views(): void
    {
        SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'HasSavedViewsTestable',
            'name' => 'View A',
            'filters' => [],
        ]);

        SavedView::create([
            'admin_id' => $this->admin->id,
            'resource' => 'Order',
            'name' => 'View B',
            'filters' => [],
        ]);

        // The trait returns class_basename(static::class) = 'HasSavedViewsTestable'
        $options = $this->instance->callGetSavedViewOptions();
        $this->assertCount(1, $options);
        $this->assertEquals('View A', reset($options));
    }
}

// ── Test Helper: Minimal class using HasSavedViews trait ─────────────────

class HasSavedViewsTestable
{
    use HasSavedViews;

    protected static $resource = \App\Filament\Resources\ProductResource::class;

    public $tableSort = null;

    public $tableFilters = [];

    public $tableSearch = '';

    public function callExtractSortColumn(): ?string
    {
        return $this->extractSortColumn();
    }

    public function callExtractSortDirection(): ?string
    {
        return $this->extractSortDirection();
    }

    public function callGetResourceName(): string
    {
        return $this->getResourceName();
    }

    public function callGetSavedViewOptions(): array
    {
        return $this->getSavedViewOptions();
    }
}
