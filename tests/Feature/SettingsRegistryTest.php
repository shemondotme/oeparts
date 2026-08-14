<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\SettingsPage;
use App\Filament\Support\SettingsRegistry;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression coverage for the bug class this registry exists to prevent:
 * a settings page that exists on disk but has no SettingsRegistry::PAGES
 * entry is unreachable from the Settings cluster grid with zero error
 * anywhere (this happened to UiSettings before the registry existed).
 */
class SettingsRegistryTest extends TestCase
{
    /**
     * @return array<class-string>
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

    #[Test]
    public function every_concrete_settings_page_has_exactly_one_registry_entry(): void
    {
        $onDisk = $this->discoverConcreteSettingsPageClasses();
        $registered = SettingsRegistry::pageClasses();

        sort($onDisk);
        sort($registered);

        $this->assertSame(
            $onDisk,
            $registered,
            'A settings page exists on disk with no (or a duplicate) SettingsRegistry::PAGES entry — '
            . 'it would be unreachable from /admin/settings with no error, the exact bug this registry prevents.'
        );
    }

    #[Test]
    public function every_registry_entry_class_exists_and_resolves_to_its_own_url(): void
    {
        foreach (SettingsRegistry::PAGES as $key => $page) {
            $this->assertTrue(
                class_exists($page['class']),
                "SettingsRegistry::PAGES['{$key}']['class'] does not exist."
            );

            $this->assertTrue(
                method_exists($page['class'], 'getUrl') || method_exists($page['class'], 'getSlug'),
                "SettingsRegistry::PAGES['{$key}']['class'] ({$page['class']}) does not resolve to a Filament page URL."
            );

            if ($page['type'] === 'page') {
                // 'page' entries are App\Filament\Pages\Settings\SettingsPage
                // subclasses and must live at the strict /admin/settings/{slug}
                // URL this registry's cluster grid links to — the exact
                // missing-slug-override mismatch that previously made
                // UiSettings unreachable.
                $this->assertSame(
                    $page['url'],
                    '/admin/settings/' . $page['class']::getSlug(),
                    "SettingsRegistry::PAGES['{$key}']['url'] does not match {$page['class']}::getSlug()."
                );
            } else {
                // 'tool' entries (Backup, Updates, read-only system monitors,
                // the Site Copy Library, ...) are not SettingsPage subclasses
                // and are not required to live under /admin/settings — just
                // required to resolve to their own declared URL. getUrl()
                // returns an absolute URL (scheme+host); the registry stores
                // a host-agnostic path (what the cluster grid's url($item[1])
                // call expects), so compare path components only.
                $this->assertSame(
                    $page['url'],
                    parse_url($page['class']::getUrl(), PHP_URL_PATH),
                    "SettingsRegistry::PAGES['{$key}']['url'] does not match {$page['class']}::getUrl()."
                );
            }
        }
    }

    #[Test]
    public function sections_builds_the_blade_shape_with_every_page_present(): void
    {
        $superGroups = SettingsRegistry::sections();

        $urls = collect($superGroups)
            ->flatMap(fn (array $superGroup) => $superGroup['sections'])
            ->flatMap(fn (array $section) => $section['items'])
            ->map(fn (array $item) => $item[1])
            ->all();

        sort($urls);
        $expected = collect(SettingsRegistry::PAGES)->pluck('url')->sort()->values()->all();

        $this->assertSame($expected, $urls);
    }

    #[Test]
    public function every_section_declares_a_valid_super_group(): void
    {
        foreach (SettingsRegistry::SECTIONS as $sectionKey => $section) {
            $this->assertArrayHasKey(
                $section['super_group'],
                SettingsRegistry::SUPER_GROUPS,
                "SettingsRegistry::SECTIONS['{$sectionKey}']['super_group'] does not exist in SUPER_GROUPS."
            );
        }
    }

    #[Test]
    public function every_page_declares_a_valid_section_and_type(): void
    {
        foreach (SettingsRegistry::PAGES as $key => $page) {
            $this->assertArrayHasKey(
                $page['section'],
                SettingsRegistry::SECTIONS,
                "SettingsRegistry::PAGES['{$key}']['section'] does not exist in SECTIONS."
            );

            $this->assertContains(
                $page['type'],
                ['page', 'tool'],
                "SettingsRegistry::PAGES['{$key}']['type'] must be 'page' or 'tool'."
            );
        }
    }
}
