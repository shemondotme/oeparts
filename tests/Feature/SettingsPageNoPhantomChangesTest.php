<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SettingsPage;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Comprehensive regression coverage for two "always dirty" bugs found via a
 * live click-through of every settings page (2026-07-15): clicking Save
 * with zero edits should ALWAYS report "no changes" — never a phantom diff.
 *
 * Bug 1 (2 pages: GeneralSettings, SeoSettings): an untouched FileUpload/
 * CheckboxList/KeyValue field's Livewire state is [] — json_encode([]) is
 * the string "[]", compared against the stored "" default. Fixed by
 * treating an empty array as "" in both buildDiffBetween() and
 * confirmSave()'s per-key persistence.
 *
 * Bug 2 (far more widespread — every one of the 34 boolean settings in
 * SettingsSeeder is seeded "1"/"0", never "true"/"false"): a Toggle field's
 * live state always re-serializes to the literal strings "true"/"false",
 * naively string-compared against the stored "1"/"0" — always different,
 * so EVERY settings page containing at least one Toggle field phantom-
 * diffed on every single load, confirmed live (27 of 34 concrete pages
 * were affected). Fixed by comparing via filter_var(..., FILTER_VALIDATE_
 * BOOLEAN) for boolean fields, matching how the rest of the app already
 * reads these settings (settings('x.y', $default) consumers use the same
 * permissive parsing) instead of a literal string match.
 *
 * This test sweeps every concrete SettingsPage subclass rather than
 * spot-checking a few, since the boolean bug's blast radius was "almost
 * every page" and a partial sweep would have missed most instances.
 */
class SettingsPageNoPhantomChangesTest extends TestCase
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
    public function every_settings_page_reports_no_changes_when_nothing_was_edited(): void
    {
        $classes = $this->discoverConcreteSettingsPageClasses();
        $this->assertNotEmpty($classes, 'Settings page discovery found nothing — check the glob path.');

        $failures = [];

        foreach ($classes as $class) {
            $component = Livewire::test($class)->call('save');
            $pendingChanges = $component->get('pendingChanges');

            if ($pendingChanges !== null) {
                $failures[] = $class . ': ' . json_encode(array_keys($pendingChanges['changed'] ?? []));
            }
        }

        $this->assertSame([], $failures, "These settings pages reported a phantom change with zero edits:\n" . implode("\n", $failures));
    }
}
