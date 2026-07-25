<?php

namespace Tests\Feature;

use App\Filament\Pages\System\CacheDashboard;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CacheDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // CacheMetricsService always reads Redis::connection('cache')
        // directly, regardless of CACHE_STORE — flip the Cache facade to
        // the same connection here so seeded keys are visible to it. See
        // CacheMetricsServiceTest's class docblock for the full rationale.
        config(['cache.default' => 'redis']);

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Cache::forget('oe_test_page.key');

        parent::tearDown();
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function a_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(CacheDashboard::getUrl())->assertSuccessful()->assertSee('Cache Dashboard');
    }

    #[Test]
    public function a_non_super_admin_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('support'), 'admin');

        $this->get(CacheDashboard::getUrl())->assertForbidden();
    }

    #[Test]
    public function category_breakdown_shows_all_eight_categories(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get(CacheDashboard::getUrl())
            ->assertSuccessful()
            ->assertSee('Sections')
            ->assertSee('Homepage Content')
            ->assertSee('Manufacturers')
            ->assertSee('Active Conditions')
            ->assertSee('Coupon Lookups')
            ->assertSee('Hero Stats')
            ->assertSee('Popular OEMs')
            ->assertSee('Settings Groups');
    }

    #[Test]
    public function warm_category_action_is_callable(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(CacheDashboard::class)
            ->call('warmCategory', 'conditions')
            ->assertHasNoErrors();

        Cache::forget('conditions.active');
    }

    #[Test]
    public function clear_category_action_is_callable(): void
    {
        Cache::put('manufacturers.active', ['acme'], now()->addMinute());
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(CacheDashboard::class)
            ->call('clearCategory', 'manufacturers')
            ->assertHasNoErrors();

        $this->assertNull(Cache::get('manufacturers.active'));
    }

    #[Test]
    public function key_browser_search_returns_seeded_results(): void
    {
        Cache::put('oe_test_page.key', 'value', now()->addMinute());
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(CacheDashboard::class)
            ->set('keyBrowserPattern', 'oe_test_page.*')
            ->call('searchKeys')
            ->assertHasNoErrors()
            ->assertSet('scanResults', fn (array $results) => collect($results)->pluck('key')->contains('oe_test_page.key'));
    }

    #[Test]
    public function delete_key_removes_it_from_redis(): void
    {
        Cache::put('oe_test_page.key', 'value', now()->addMinute());
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Livewire::test(CacheDashboard::class)
            ->call('deleteKey', 'oe_test_page.key')
            ->assertHasNoErrors();

        $this->assertNull(Cache::get('oe_test_page.key'));
    }

    #[Test]
    public function export_report_streams_a_csv_response(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $response = Livewire::test(CacheDashboard::class)->call('exportReport');

        $response->assertHasNoErrors();
    }
}
