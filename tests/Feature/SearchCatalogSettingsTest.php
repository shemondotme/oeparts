<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SearchCatalogSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SearchCatalogSettings merges the old SearchSettings ('search' group) and
 * PdpSettings ('pdp' group) pages into one 2-tab page, following
 * SeoControlCenter's $settingsGroups multi-group override pattern.
 */
class SearchCatalogSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
    }

    private function superAdmin(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');

        return $admin;
    }

    #[Test]
    public function saving_edits_both_tabs_writes_each_field_to_its_own_group(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SearchCatalogSettings::class)
            ->set('data.min_chars', 4)
            ->set('data.buy_now_enabled', true)
            ->call('save');

        $this->assertSame('4', Setting::where('group', 'search')->where('key', 'min_chars')->value('value'));
        $this->assertSame('true', Setting::where('group', 'pdp')->where('key', 'buy_now_enabled')->value('value'));
    }

    #[Test]
    public function untouched_page_reports_no_changes(): void
    {
        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SearchCatalogSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }
}
