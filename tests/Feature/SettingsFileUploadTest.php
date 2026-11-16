<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\GeneralBrandSettings;
use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A picture uploaded on a settings page (share image, logo, favicon) used to be saved as
 * "{"<id>":{}}": the upload was never stored, and the page then printed a broken og:image address.
 */
class SettingsFileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
        Storage::fake('public');

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    #[Test]
    public function the_share_image_uploaded_in_the_seo_control_center_is_stored_and_saved_as_a_path(): void
    {
        Livewire::test(SeoControlCenter::class)
            ->fillForm(['default_og_image' => UploadedFile::fake()->image('share.png', 1200, 630)])
            ->call('save')
            ->assertHasNoFormErrors();

        $path = (string) Setting::where('key', 'default_og_image')->value('value');

        $this->assertStringStartsWith('og-images/', $path);
        $this->assertStringNotContainsString('{', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function saving_again_without_a_new_upload_keeps_the_stored_path(): void
    {
        $component = Livewire::test(SeoControlCenter::class)
            ->fillForm(['default_og_image' => UploadedFile::fake()->image('share.png', 1200, 630)])
            ->call('save');

        $first = (string) Setting::where('key', 'default_og_image')->value('value');

        $component->fillForm(['twitter_handle' => '@oeparts'])->call('save');

        $this->assertSame($first, (string) Setting::where('key', 'default_og_image')->value('value'));
    }
}
