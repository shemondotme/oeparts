<?php

namespace Tests\Feature;

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Filament\Pages\Settings\SettingsPage;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Every settings page previously had only Save/Discard/Reset in the form
 * footer, with no way back to the Settings overview except the small
 * cluster breadcrumb link above the header — reported as "no back option"
 * after a live click-through. SettingsPage::getHeaderActions() now adds a
 * "Back to Settings" link; this sweeps every concrete subclass (mirroring
 * SettingsPageNoPhantomChangesTest's discovery approach) rather than
 * spot-checking a few, since the 3 pages with their own header actions
 * (EmailSettings, PaymentSettings, PerformanceSettings) must merge it in
 * via `...parent::getHeaderActions()`, not silently drop it by overriding.
 */
class SettingsPageBackActionTest extends TestCase
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

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(SettingsPage::class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    #[Test]
    public function every_settings_page_has_a_back_to_settings_action(): void
    {
        $classes = $this->discoverConcreteSettingsPageClasses();
        $this->assertNotEmpty($classes, 'Settings page discovery found nothing — check the glob path.');

        $expectedUrl = SettingsCluster::getUrl();
        $failures = [];

        foreach ($classes as $class) {
            try {
                Livewire::test($class)
                    ->assertActionExists(
                        'backToSettings',
                        fn (Action $action): bool => $action->getUrl() === $expectedUrl
                    );
            } catch (\Throwable $e) {
                $failures[] = $class . ': ' . $e->getMessage();
            }
        }

        $this->assertSame([], $failures, "These settings pages are missing a working backToSettings action:\n" . implode("\n", $failures));
    }
}
