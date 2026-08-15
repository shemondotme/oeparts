<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SettingsPage;
use App\Models\Admin;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Regression coverage for the bug class this refactor exists to prevent:
 * SettingsPage::getFactoryDefaults() used to be a hand-maintained match()
 * duplicating SettingsSeeder's data, which drifted for 18 of 30 groups
 * (some down to a completely empty array — see ADMIN_PANEL_MASTER_WORKFLOW.md
 * Option TT). It now derives directly from SettingsSeeder::definitions(),
 * so this test can never go stale the same way: it discovers groups from
 * the seeder itself rather than a hand-maintained list.
 */
class SettingsFactoryDefaultsTest extends TestCase
{
    /**
     * @return array<class-string<SettingsPage>>
     */
    private function discoverConcreteSettingsPageClasses(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->in(app_path('Filament/Pages/Settings'))->name('*.php') as $file) {
            $class = 'App\\Filament\\Pages\\Settings\\' . $file->getBasename('.php');

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            if ($reflection->isSubclassOf(SettingsPage::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function callGetFactoryDefaults(string $pageClass): array
    {
        $method = new \ReflectionMethod($pageClass, 'getFactoryDefaults');
        $method->setAccessible(true);

        return $method->invoke(new $pageClass());
    }

    #[Test]
    public function every_settings_page_factory_defaults_matches_its_seeded_keys(): void
    {
        $seededGroups = collect(SettingsSeeder::definitions())
            ->groupBy('group')
            ->map(fn ($rows) => $rows->pluck('key')->sort()->values()->all());

        foreach ($this->discoverConcreteSettingsPageClasses() as $pageClass) {
            $reflection = new ReflectionClass($pageClass);

            // Multi-group pages (e.g. SeoControlCenter, which spans 'seo'
            // and 'crawlers') declare their own $settingsGroups array
            // instead of relying on the single-group $settingsGroup the
            // base class assumes — check for that override first.
            if ($reflection->hasProperty('settingsGroups')) {
                $groupsProperty = $reflection->getProperty('settingsGroups');
                $groupsProperty->setAccessible(true);
                $groups = $groupsProperty->getValue();
            } else {
                $groupProperty = $reflection->getProperty('settingsGroup');
                $groupProperty->setAccessible(true);
                $groups = [$groupProperty->getValue()];
            }

            $expectedKeys = collect($groups)
                ->flatMap(fn (string $group) => $seededGroups->get($group, []))
                ->sort()
                ->values()
                ->all();

            if ($expectedKeys === []) {
                continue; // about/database: display-only, no seeded rows, no factory defaults expected
            }

            $actualKeys = collect(array_keys($this->callGetFactoryDefaults($pageClass)))->sort()->values()->all();

            $this->assertSame(
                $expectedKeys,
                $actualKeys,
                "{$pageClass}::getFactoryDefaults() keys don't match the seeded '".implode(',', $groups)."' group keys."
            );
        }
    }

    #[Test]
    public function ui_settings_factory_defaults_includes_all_22_hero_keys(): void
    {
        // CustomizationSettings spans 7 groups (ui/navbar/footer/announcement/
        // sections/menu/social_links) so its defaults are the union of all of
        // them now — filter to the hero_* prefix to isolate what was
        // previously UiSettings' entire (single-group) default set.
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\CustomizationSettings::class);
        $heroKeys = collect($defaults)->keys()->filter(fn (string $k) => str_starts_with($k, 'hero_'));

        $this->assertCount(22, $heroKeys);
        $this->assertArrayHasKey('hero_spec_r5_value', $defaults);
    }

    #[Test]
    public function menu_settings_factory_defaults_uses_the_real_footer_toggle_keys(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\CustomizationSettings::class);
        $footerToggleKeys = collect(array_keys($defaults))->filter(fn (string $k) => str_starts_with($k, 'footer_show_'));

        $this->assertSame(
            ['footer_show_about', 'footer_show_blog', 'footer_show_contact', 'footer_show_faq'],
            $footerToggleKeys->sort()->values()->all()
        );
        $this->assertIsBool($defaults['footer_show_about']);
    }

    #[Test]
    public function stats_counter_factory_defaults_uses_the_real_keys(): void
    {
        // StatsCounterSettings merged into AppearanceSettings (appearance/
        // preloader/stats_counter groups) — intersect down to just the
        // stats-counter keys rather than asserting the full combined set.
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\AppearanceSettings::class);
        $statsCounterKeys = ['countries_count', 'customers_count', 'orders_count', 'parts_count', 'rating', 'show_section'];

        $this->assertSame(
            $statsCounterKeys,
            collect(array_keys($defaults))->intersect($statsCounterKeys)->sort()->values()->all()
        );
    }

    #[Test]
    public function checkout_factory_defaults_decodes_json_array_correctly(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\StoreOperationsSettings::class);

        $this->assertSame(['card', 'bank_transfer'], $defaults['allowed_payment_methods']);
        $this->assertIsArray($defaults['allowed_payment_methods']);
    }

    #[Test]
    public function auth_factory_defaults_matches_seeded_values_not_stale_hardcoded_ones(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\SecurityAccessSettings::class);

        $this->assertSame(3, $defaults['otp_max_attempts']);
        $this->assertSame(60, $defaults['otp_resend_cooldown']);
    }

    #[Test]
    public function performance_factory_defaults_includes_cache_ttl_manufacturers(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\PerformanceSettings::class);

        $this->assertArrayHasKey('cache_ttl_manufacturers', $defaults);
        $this->assertSame(60, $defaults['cache_ttl_manufacturers']);
    }

    #[Test]
    public function dashboard_factory_defaults_excludes_removed_dead_keys(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\StoreOperationsSettings::class);

        $this->assertArrayNotHasKey('pending_orders_attention', $defaults);
        $this->assertArrayNotHasKey('pending_orders_warning', $defaults);
        $this->assertArrayHasKey('orders_threshold', $defaults);
        $this->assertArrayHasKey('pending_delayed_minutes', $defaults);
        $this->assertArrayHasKey('cart_abandoned_hours', $defaults);
    }

    #[Test]
    public function dashboard_factory_defaults_includes_health_check_thresholds(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\StoreOperationsSettings::class);

        // backup_stale_hours used to be a phantom setting — read via
        // settings() with a code-only fallback, but never seeded/editable.
        // It's now a real, DB-backed setting (see HealthCheckService rework).
        $this->assertArrayHasKey('backup_stale_hours', $defaults);
        $this->assertSame(26, $defaults['backup_stale_hours']);

        $this->assertArrayHasKey('scheduler_stale_minutes', $defaults);
        $this->assertSame(3, $defaults['scheduler_stale_minutes']);
    }
}
