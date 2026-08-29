<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The IndexNow key-verification route (/{key}.txt) only matches
 * [a-zA-Z0-9]+ — a hyphenated key saved here without a matching restriction
 * would pass validation but could then never actually be served, silently
 * and permanently failing Bing's key-verification crawl with no error
 * surfaced anywhere.
 */
class SeoControlCenterValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function a_hyphenated_indexnow_key_is_rejected(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->set('data.indexnow_api_key', 'my-generated-key-1234')
            ->call('save')
            ->assertHasErrors(['data.indexnow_api_key']);
    }

    #[Test]
    public function an_alphanumeric_indexnow_key_is_accepted(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->set('data.indexnow_api_key', 'abc123XYZ')
            ->call('save')
            ->assertHasNoErrors();
    }
}
