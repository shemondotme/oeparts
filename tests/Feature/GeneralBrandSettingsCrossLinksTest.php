<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\GeneralBrandSettings;
use App\Filament\Resources\PageResource;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 8 hardening — re-verifying settings-reorg gap #2 (audit finding:
 * PageResource.is_homepage is a site-wide routing flag hiding inside a
 * content Resource's form, undiscoverable from Settings). The fix was a
 * cross-link Placeholder on Site Identity explaining homepage routing
 * lives on the Page record, not here — this confirms it actually renders
 * and points at the real Pages resource, not just that the code exists.
 */
class GeneralBrandSettingsCrossLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
    }

    #[Test]
    public function the_site_identity_tab_links_to_the_pages_resource_for_homepage_routing(): void
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        Livewire::test(GeneralBrandSettings::class)
            ->assertSee('Set as Homepage', false)
            ->assertSeeHtml('href="'.PageResource::getUrl().'"');
    }
}
