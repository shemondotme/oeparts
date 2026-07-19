<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\GeneralSettings;
use App\Filament\Pages\Settings\SEOSettings;
use App\Models\Admin;
use App\Models\Setting;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage: any settings page with an array-typed field
 * (FileUpload, CheckboxList, KeyValue) left untouched always reported a
 * phantom change, because SettingsPage::buildDiffBetween() compared the
 * stored empty-string default against json_encode([]) = the literal string
 * "[]" — confirmed live via the raw Livewire response payload before the
 * fix (pendingChanges was populated with a fake favicon_id/logo_id "change"
 * on GeneralSettings even with zero edits). Clicking Save on an untouched
 * page should always report "no changes", never a phantom diff — and never
 * write the literal string "[]" into a setting whoever else in the app
 * expects to be empty/null.
 */
class SettingsArrayFieldPhantomDiffTest extends TestCase
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
    public function untouched_file_upload_field_does_not_report_a_phantom_change_on_general_settings(): void
    {
        Setting::updateOrCreate(['group' => 'general', 'key' => 'logo_id'], ['value' => '', 'type' => 'string']);
        Setting::updateOrCreate(['group' => 'general', 'key' => 'favicon_id'], ['value' => '', 'type' => 'string']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(GeneralSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function untouched_file_upload_field_does_not_report_a_phantom_change_on_seo_settings(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'default_og_image'], ['value' => '', 'type' => 'string']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SEOSettings::class)
            ->call('save')
            ->assertSet('pendingChanges', null);
    }

    #[Test]
    public function a_real_change_alongside_an_untouched_file_field_is_still_detected_and_saved_correctly(): void
    {
        Setting::updateOrCreate(['group' => 'general', 'key' => 'logo_id'], ['value' => '', 'type' => 'string']);
        Setting::updateOrCreate(['group' => 'general', 'key' => 'favicon_id'], ['value' => '', 'type' => 'string']);
        Setting::updateOrCreate(['group' => 'general', 'key' => 'site_name'], ['value' => 'OeParts', 'type' => 'string']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(GeneralSettings::class)
            ->set('data.site_name', 'OeParts Renamed')
            ->call('save');

        $this->assertSame('OeParts Renamed', Setting::where('group', 'general')->where('key', 'site_name')->value('value'));
        // The untouched array field must be saved back as "" — never the
        // literal string "[]" that would previously have been written by
        // persistChanges()'s unconditional per-key json_encode(array).
        $this->assertSame('', Setting::where('group', 'general')->where('key', 'logo_id')->value('value'));
        $this->assertSame('', Setting::where('group', 'general')->where('key', 'favicon_id')->value('value'));
    }

    #[Test]
    public function a_genuine_boolean_toggle_change_is_still_detected_and_saved_correctly(): void
    {
        Setting::updateOrCreate(['group' => 'seo', 'key' => 'google_ping_enabled'], ['value' => '1', 'type' => 'boolean']);

        $this->actingAs($this->superAdmin(), 'admin');

        Livewire::test(SEOSettings::class)
            ->set('data.google_ping_enabled', false)
            ->call('save');

        $this->assertSame('false', Setting::where('group', 'seo')->where('key', 'google_ping_enabled')->value('value'));
    }

    #[Test]
    public function a_genuine_multilang_field_change_is_still_detected_and_saved_correctly(): void
    {
        Setting::updateOrCreate(
            ['group' => 'contact', 'key' => 'hours'],
            ['value' => json_encode(['en' => 'Mon–Fri 9:00–18:00', 'de' => '', 'lt' => '', 'fr' => '', 'es' => '']), 'type' => 'json']
        );

        $this->actingAs($this->superAdmin(), 'admin');

        $newHours = ['en' => 'Mon–Fri 8:00–17:00', 'de' => '', 'lt' => '', 'fr' => '', 'es' => ''];
        Livewire::test(\App\Filament\Pages\Settings\ContactSettings::class)
            ->set('data.hours', $newHours)
            ->call('save');

        $this->assertSame(
            $newHours,
            json_decode(Setting::where('group', 'contact')->where('key', 'hours')->value('value'), true)
        );
    }
}
