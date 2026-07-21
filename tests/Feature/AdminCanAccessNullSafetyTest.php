<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Regression coverage for a bug class that slipped through TWICE: a
 * canAccess() calling auth('admin')->user()->hasRole(...) (or ->hasAnyRole/
 * ->hasPermissionTo) with no null-safe operator crashes with "Call to a
 * member function hasRole() on null" whenever auth('admin')->user() is null
 * while Filament resolves navigation — confirmed live, twice, as a real 500
 * on a real install (once right after applying an update, once at the very
 * last step of a multi-hop update). The first fix (v1.0.9) manually grepped
 * for the exact one-liner `auth('admin')->user()->hasRole(` and missed 5
 * classes that used the equally-common two-line `$admin = auth('admin')->
 * user(); ... $admin->hasRole(...)` shape instead (App\Filament\Clusters\
 * System, ErrorMonitor, ServerMonitor, QueueMonitor, HelpPage) — a hand-
 * maintained class list is exactly how that happened, so this test instead
 * discovers every canAccess() override under app/Filament/ automatically
 * and calls each one as a guest.
 */
class AdminCanAccessNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int,class-string> every app\Filament\... class that
     *         DECLARES its own canAccess() — including an abstract base like
     *         SettingsPage (a static method can be called on an abstract
     *         class directly; only `new` is disallowed), so the ~30 settings
     *         pages that merely inherit it are deliberately excluded (they'd
     *         just re-run the identical inherited check 30 times) while the
     *         one real implementation still gets tested exactly once.
     */
    public static function discoverClassesWithCanAccess(): array
    {
        $classes = [];

        foreach (Finder::create()->files()->name('*.php')->in(app_path('Filament')) as $file) {
            $relative = str_replace(
                [app_path('Filament').DIRECTORY_SEPARATOR, '/', '.php'],
                ['', '\\', ''],
                $file->getPathname()
            );
            $class = 'App\\Filament\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if (! $reflection->hasMethod('canAccess')) {
                continue;
            }

            $method = $reflection->getMethod('canAccess');

            // Only classes that DECLARE their own canAccess() — not ones that
            // merely inherit a base implementation (already covered once via
            // the declaring class itself).
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            if (! $method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue; // not Filament's canAccess(): bool contract — skip
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
    }

    #[Test]
    public function canAccess_never_throws_and_returns_false_for_a_guest(): void
    {
        $this->assertGuest('admin');

        $classes = self::discoverClassesWithCanAccess();
        $this->assertNotEmpty($classes, 'Discovery found no canAccess() overrides — check the Finder path/namespace mapping.');

        $failures = [];

        foreach ($classes as $class) {
            try {
                $result = $class::canAccess();
            } catch (\Throwable $e) {
                $failures[] = $class.': threw '.$e::class.' — '.$e->getMessage();

                continue;
            }

            if ($result !== false) {
                $failures[] = $class.': expected false for a guest, got '.var_export($result, true);
            }
        }

        $this->assertSame([], $failures, "canAccess() must degrade to false for a guest, never throw:\n".implode("\n", $failures));
    }
}
