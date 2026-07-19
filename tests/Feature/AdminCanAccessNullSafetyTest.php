<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage: 13 Filament cluster/page canAccess() methods called
 * `auth('admin')->user()->hasRole(...)` / `->hasAnyRole(...)` without a
 * null-safe operator, so any context where auth('admin')->user() is null
 * (e.g. Filament resolving navigation for an unauthenticated/guest request,
 * or a stale session right after an in-app update rebuilds framework
 * caches) threw "Call to a member function hasRole() on null" — a real 500,
 * confirmed live on a real install immediately after applying an update.
 * canAccess() must always degrade to false for a guest, never throw.
 */
class AdminCanAccessNullSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int,class-string> */
    public static function classes(): array
    {
        return [
            \App\Filament\Clusters\Settings::class,
            \App\Filament\Clusters\Reports::class,
            \App\Filament\Clusters\Content::class,
            \App\Filament\Pages\System\SetupAssistant::class,
            \App\Filament\Pages\System\CacheDashboard::class,
            \App\Filament\Pages\System\ScheduledTasksPage::class,
            \App\Filament\Pages\System\PermissionMatrix::class,
            \App\Filament\Pages\System\LogViewerPage::class,
            \App\Filament\Pages\System\HealthCheckDashboard::class,
            \App\Filament\Pages\System\FailedJobsPage::class,
            \App\Filament\Pages\Content\ContentRevisionPage::class,
            \App\Filament\Pages\Catalog\InventoryLogPage::class,
            \App\Filament\Pages\Catalog\BulkUpdateLogPage::class,
        ];
    }

    #[Test]
    public function canAccess_never_throws_and_returns_false_for_a_guest(): void
    {
        $this->assertGuest('admin');

        $failures = [];

        foreach (self::classes() as $class) {
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
