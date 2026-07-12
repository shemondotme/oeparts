<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SettingsActivityLog;
use App\Filament\Pages\Settings\SettingsPage;
use App\Filament\Pages\Settings\UISettings;
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

            if ($reflection->isAbstract() || $class === SettingsActivityLog::class) {
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
            $groupProperty = new \ReflectionProperty($pageClass, 'settingsGroup');
            $groupProperty->setAccessible(true);
            $group = $groupProperty->getValue();

            $expectedKeys = $seededGroups->get($group, []);

            if ($expectedKeys === []) {
                continue; // about/database: display-only, no seeded rows, no factory defaults expected
            }

            $actualKeys = collect(array_keys($this->callGetFactoryDefaults($pageClass)))->sort()->values()->all();

            $this->assertSame(
                $expectedKeys,
                $actualKeys,
                "{$pageClass}::getFactoryDefaults() keys don't match the seeded '{$group}' group keys."
            );
        }
    }

    #[Test]
    public function ui_settings_factory_defaults_includes_all_22_hero_keys(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\UISettings::class);

        $this->assertCount(22, $defaults);
        $this->assertArrayHasKey('hero_spec_r5_value', $defaults);
    }

    #[Test]
    public function menu_settings_factory_defaults_uses_the_real_footer_toggle_keys(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\MenuSettings::class);

        $this->assertSame(
            ['footer_show_about', 'footer_show_blog', 'footer_show_contact', 'footer_show_faq'],
            collect(array_keys($defaults))->sort()->values()->all()
        );
        $this->assertIsBool($defaults['footer_show_about']);
    }

    #[Test]
    public function stats_counter_factory_defaults_uses_the_real_keys(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\StatsCounterSettings::class);

        $this->assertSame(
            ['countries_count', 'customers_count', 'orders_count', 'parts_count', 'rating', 'show_section'],
            collect(array_keys($defaults))->sort()->values()->all()
        );
    }

    #[Test]
    public function checkout_factory_defaults_decodes_json_array_correctly(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\CheckoutSettings::class);

        $this->assertSame(['card', 'bank_transfer'], $defaults['allowed_payment_methods']);
        $this->assertIsArray($defaults['allowed_payment_methods']);
    }

    #[Test]
    public function auth_factory_defaults_matches_seeded_values_not_stale_hardcoded_ones(): void
    {
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\AuthSettings::class);

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
        $defaults = $this->callGetFactoryDefaults(\App\Filament\Pages\Settings\DashboardSettings::class);

        $this->assertArrayNotHasKey('pending_orders_attention', $defaults);
        $this->assertArrayNotHasKey('backup_stale_hours', $defaults);
        $this->assertArrayNotHasKey('pending_orders_warning', $defaults);
        $this->assertArrayHasKey('orders_threshold', $defaults);
        $this->assertArrayHasKey('pending_delayed_minutes', $defaults);
        $this->assertArrayHasKey('cart_abandoned_hours', $defaults);
    }
}
