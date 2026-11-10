<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SeoControlCenter;
use App\Models\ActivityLog;
use App\Models\Admin;
use App\Models\Setting;
use App\Services\SeoSettingsTransfer;
use App\Services\SettingsService;
use Database\Seeders\AdminSeeder;
use Database\Seeders\LanguagesSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/** Export / import of the SEO Control Center settings between installs. */
class SeoSettingsTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class, LanguagesSeeder::class, RolesSeeder::class, AdminSeeder::class]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');
    }

    private function transfer(): SeoSettingsTransfer
    {
        return app(SeoSettingsTransfer::class);
    }

    public function test_the_export_has_typed_values_and_never_secrets_or_site_specific_entries(): void
    {
        app(SettingsService::class)->set('seo.indexnow_api_key', 'super-secret-key');
        app(SettingsService::class)->set('seo.canonical_host', 'www.example.org');
        app(SettingsService::class)->set('seo.gsc_property_url', 'sc-domain:example.org');

        $export = $this->transfer()->export();

        $this->assertSame('oeparts-seo-settings', $export['format']);
        $this->assertTrue($export['settings']['audit_alerts_enabled']);
        $this->assertSame(100, $export['settings']['thin_content_min_words']);
        $this->assertIsArray($export['settings']['ai_bot_rules']);
        foreach (['indexnow_api_key', 'gsc_client_secret', 'gsc_refresh_token', 'crux_api_key', 'canonical_host', 'gsc_property_url', 'gsc_client_id', 'google_verification'] as $key) {
            $this->assertArrayNotHasKey($key, $export['settings'], $key);
        }
        $this->assertStringNotContainsString('super-secret-key', json_encode($export));
    }

    public function test_a_round_trip_is_a_no_op(): void
    {
        $export = $this->transfer()->export();

        $preview = $this->transfer()->preview($export);

        $this->assertSame([], $preview['changes']);
        $this->assertSame([], $preview['refused']);
        $this->assertSame(0, $this->transfer()->apply($export));
    }

    public function test_changes_are_listed_and_applied_with_an_activity_log_entry(): void
    {
        $payload = $this->transfer()->export();
        $payload['settings']['thin_content_noindex'] = true;
        $payload['settings']['thin_content_min_words'] = 250;
        $payload['settings']['default_description'] = 'A new default description.';

        $preview = $this->transfer()->preview($payload);
        $this->assertEqualsCanonicalizing(['thin_content_noindex', 'thin_content_min_words', 'default_description'], array_column($preview['changes'], 'key'));

        $this->assertSame(3, $this->transfer()->apply($payload));

        $this->assertTrue(filter_var(settings('seo.thin_content_noindex'), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame('250', (string) settings('seo.thin_content_min_words'));
        $this->assertSame('A new default description.', settings('seo.default_description'));

        $log = ActivityLog::where('action', 'settings_imported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('100', (string) $log->old_values['thin_content_min_words']);
    }

    public function test_unknown_secret_site_specific_and_badly_typed_entries_are_refused(): void
    {
        $payload = [
            'format' => 'oeparts-seo-settings', 'version' => 1,
            'settings' => [
                'not_a_real_setting' => 'x',
                'indexnow_api_key' => 'planted',
                'canonical_host' => 'evil.example',
                'thin_content_min_words' => 'lots',
                'audit_alerts_enabled' => 'maybe',
                'ai_bot_rules' => '{not json',
            ],
        ];

        $preview = $this->transfer()->preview($payload);

        $this->assertSame([], $preview['changes']);
        $this->assertEqualsCanonicalizing(
            ['not_a_real_setting', 'indexnow_api_key', 'canonical_host', 'thin_content_min_words', 'audit_alerts_enabled', 'ai_bot_rules'],
            array_column($preview['refused'], 'key')
        );
        $this->assertSame(0, $this->transfer()->apply($payload));
        $this->assertSame('', (string) settings('seo.canonical_host'));
    }

    public function test_a_foreign_or_newer_file_is_rejected(): void
    {
        $this->assertNotNull($this->transfer()->preview(['hello' => 'world'])['error']);
        $this->assertNotNull($this->transfer()->preview(null)['error']);
        $this->assertNotNull($this->transfer()->preview(['format' => 'oeparts-seo-settings', 'version' => 99, 'settings' => []])['error']);
    }

    public function test_the_page_imports_an_uploaded_file(): void
    {
        $payload = $this->transfer()->export();
        $payload['settings']['thin_content_min_words'] = 333;
        $file = UploadedFile::fake()->createWithContent('seo.json', json_encode($payload));

        Livewire::test(SeoControlCenter::class)
            ->callAction('importSeoSettings', ['file' => $file])
            ->assertHasNoActionErrors();

        $this->assertSame('333', (string) settings('seo.thin_content_min_words'));
    }
}
