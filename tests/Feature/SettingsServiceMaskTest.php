<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Cleanup\TargetRegistry;
use App\Services\Instance\SafeMode;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.2: what a copy of the shop SEES of its settings. The stored rows are never edited and the mask is
 * applied after the cache, so changing the mode needs no cache flush and going live shows the real values at once.
 */
class SettingsServiceMaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        $this->app->forgetInstance(SafeMode::class);
        SafeMode::resetLogMemory();

        $this->store('seo', 'indexnow_enabled', '1', 'boolean');
        $this->store('seo', 'indexnow_api_key', Crypt::encryptString('real-indexnow-key'), 'string', true);
        $this->store('seo', 'canonical_host', 'shop.example', 'string');
        $this->store('seo', 'gsc_property_url', 'https://shop.example/', 'string');
        $this->store('seo', 'site_tagline', 'Parts for everyone', 'string');
        $this->store('integrations', 'gtm_id', 'GTM-REAL123', 'string');
        $this->store('general', 'header_scripts', '<script>real()</script>', 'string');
        $this->store('push', 'enabled', '1', 'boolean');
        $this->store('cleanup', 'auto_enabled', '1', 'boolean');
    }

    private function store(string $group, string $key, string $value, string $type, bool $encrypted = false): void
    {
        Setting::create(['group' => $group, 'key' => $key, 'value' => $value, 'type' => $type, 'is_encrypted' => $encrypted]);
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    private function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    #[Test]
    public function the_real_shop_reads_exactly_what_is_stored(): void
    {
        $this->mode('production');

        $this->assertSame('1', $this->settings()->get('seo.indexnow_enabled'));
        $this->assertSame('real-indexnow-key', $this->settings()->get('seo.indexnow_api_key'));
        $this->assertSame('shop.example', $this->settings()->get('seo.canonical_host'));
        $this->assertSame('GTM-REAL123', $this->settings()->get('integrations.gtm_id'));
        $this->assertSame('1', $this->settings()->get('cleanup.auto_enabled'));
        $this->assertSame('1', $this->settings()->getGroup('push')['enabled']);
    }

    #[Test]
    public function a_copy_sees_the_switched_off_values_through_get_and_get_group_alike(): void
    {
        $this->mode('staging');

        $this->assertFalse($this->settings()->get('seo.indexnow_enabled'));
        $this->assertSame('', $this->settings()->get('seo.indexnow_api_key'), 'an encrypted key is hidden too');
        $this->assertSame('', $this->settings()->get('seo.canonical_host'));
        $this->assertSame('', $this->settings()->get('seo.gsc_property_url'), 'the gsc_* family is covered');
        $this->assertSame('', $this->settings()->get('integrations.gtm_id'));
        $this->assertSame('', $this->settings()->get('general.header_scripts'));
        $this->assertFalse($this->settings()->getGroup('push')['enabled']);
        $this->assertFalse($this->settings()->getGroup('seo')['indexnow_enabled']);
    }

    #[Test]
    public function a_setting_with_no_override_reads_normally_on_a_copy(): void
    {
        $this->mode('staging');

        $this->assertSame('Parts for everyone', $this->settings()->get('seo.site_tagline'));
    }

    #[Test]
    public function a_named_switch_that_was_never_stored_still_reads_as_off_on_a_copy(): void
    {
        $this->mode('quarantine');

        $this->assertFalse($this->settings()->get('seo.google_ping_enabled', true), 'the code asked with a default of "on"');
        $this->assertFalse($this->settings()->get('updates.auto_apply_security', true));
        $this->assertFalse($this->settings()->get('backup.schedule_enabled', true));
        $this->assertFalse($this->settings()->get('performance.cloudflare_enabled', true));
        $this->assertSame('sandbox', $this->settings()->get('payment.airwallex_environment', 'live'));
    }

    #[Test]
    public function the_stored_rows_are_never_changed_by_reading_on_a_copy(): void
    {
        $this->mode('staging');

        $this->settings()->get('seo.indexnow_enabled');
        $this->settings()->getGroup('seo');
        $this->settings()->getGroup('cleanup');

        $this->assertSame('1', Setting::where('group', 'seo')->where('key', 'indexnow_enabled')->value('value'));
        $this->assertSame('shop.example', Setting::where('group', 'seo')->where('key', 'canonical_host')->value('value'));
        $this->assertSame('1', Setting::where('group', 'cleanup')->where('key', 'auto_enabled')->value('value'));
    }

    #[Test]
    public function changing_the_mode_needs_no_cache_flush_in_either_direction(): void
    {
        $this->mode('production');
        $this->assertSame('shop.example', $this->settings()->get('seo.canonical_host'), 'the group is now in the 5-minute cache');

        $this->mode('staging');
        $this->assertSame('', $this->settings()->get('seo.canonical_host'), 'masked at once, straight from the cache');

        $this->mode('production');
        $this->assertSame('shop.example', $this->settings()->get('seo.canonical_host'), 'going live shows the real value at once');
    }

    #[Test]
    public function the_cache_itself_only_ever_holds_the_real_values(): void
    {
        $this->mode('staging');
        $this->settings()->getGroup('seo');

        $cached = Cache::get('settings.seo');

        $this->assertSame('shop.example', $cached['canonical_host'], 'a mask in the cache would survive the end of the mode');
    }

    // ---- automatic cleanup ---------------------------------------------------------------------------------

    #[Test]
    public function automatic_cleanup_reads_as_off_for_the_master_switch_and_for_every_registered_target(): void
    {
        $this->mode('staging');

        $this->assertFalse($this->settings()->get('cleanup.auto_enabled'));

        $registry = app(TargetRegistry::class);
        $this->assertNotEmpty($registry->all());

        foreach ($registry->all() as $target) {
            $this->assertFalse($this->settings()->get("cleanup.auto_{$target->key()}", true), "auto_{$target->key()} must read as off");
            $this->assertFalse($registry->autoEnabled($target), "{$target->key()} must not be part of a scheduled run on a copy");
        }
    }

    #[Test]
    public function on_the_real_shop_the_cleanup_defaults_are_untouched(): void
    {
        $this->mode('production');

        $registry = app(TargetRegistry::class);
        $defaults = array_map(fn ($t): bool => $registry->autoEnabled($t), $registry->all());

        $this->assertContains(true, $defaults, 'some targets are automatic by default; production must keep that');
    }

    #[Test]
    public function the_active_overrides_include_every_cleanup_target_for_the_admin_notice(): void
    {
        $this->mode('staging');

        $keys = array_column(array_filter(app(SafeMode::class)->activeOverrides(), fn (array $o): bool => $o['group'] === 'cleanup'), 'key');

        $this->assertContains('auto_enabled', $keys);

        foreach (array_keys(app(TargetRegistry::class)->all()) as $target) {
            $this->assertContains("auto_{$target}", $keys);
        }
    }

    // ---- robustness -------------------------------------------------------------------------------------------

    #[Test]
    public function a_missing_settings_table_still_degrades_to_an_empty_group_on_every_mode(): void
    {
        Schema::drop('settings');
        Cache::flush();

        foreach (['production', 'staging', 'quarantine'] as $mode) {
            $this->mode($mode);
            $group = $this->settings()->getGroup('seo');
            $this->assertIsArray($group);
        }

        $this->mode('production');
        $this->assertSame([], $this->settings()->getGroup('seo'));
        $this->assertNull($this->settings()->get('seo.canonical_host'));
    }
}
