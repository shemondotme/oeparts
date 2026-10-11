<?php

namespace Tests\Unit\Cleanup;

use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\Cleanup\Archiver;
use App\Services\Cleanup\CleanupProgress;
use App\Services\Cleanup\DiskUsageService;
use App\Services\Cleanup\OverDeleteGuard;
use App\Services\Cleanup\TargetRegistry;
use App\Services\Cleanup\VerificationReconciler;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CleanupPrimitivesTest extends TestCase
{
    /* ---- VerificationReconciler ---------------------------------------- */

    #[Test]
    public function a_clean_run_is_a_success(): void
    {
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 100, failed: 0, remaining: 0);

        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $r['status']);
        $this->assertSame([], $r['reasons']);
        $this->assertTrue($r['equation_holds']);
    }

    #[Test]
    public function leftover_or_failed_rows_make_it_partial_with_the_reason(): void
    {
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 90, failed: 10, remaining: 10);

        $this->assertSame(CleanupRunItem::STATUS_PARTIAL, $r['status']);
        $this->assertContains(VerificationReconciler::REASON_REMAINING, $r['reasons']);
        $this->assertContains(VerificationReconciler::REASON_FAILED_ITEMS, $r['reasons']);
    }

    #[Test]
    public function rows_removed_by_someone_else_are_reported_but_do_not_fail_the_run(): void
    {
        // 100 targeted, we deleted 70, 0 remain: another process removed the other 30.
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 70, failed: 0, remaining: 0);

        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $r['status']);
        $this->assertSame(30, $r['external_gone']);
        $this->assertContains(VerificationReconciler::REASON_EXTERNAL, $r['reasons']);
    }

    #[Test]
    public function deleting_more_than_was_targeted_is_a_defect_never_a_success(): void
    {
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 150, failed: 0, remaining: 0);

        $this->assertSame(CleanupRunItem::STATUS_FAILED, $r['status']);
        $this->assertContains(VerificationReconciler::REASON_OVER_DELETE, $r['reasons']);
        $this->assertFalse($r['equation_holds']);
    }

    #[Test]
    public function an_archive_smaller_than_what_was_deleted_fails_but_a_bigger_one_is_fine(): void
    {
        $short = (new VerificationReconciler)->reconcile(100, 100, 0, 0, archiveRows: 90);
        $this->assertSame(CleanupRunItem::STATUS_FAILED, $short['status']);
        $this->assertContains(VerificationReconciler::REASON_ARCHIVE_MISMATCH, $short['reasons']);

        // At-least-once delivery: a crash can write a batch twice, never fewer rows.
        $dup = (new VerificationReconciler)->reconcile(100, 100, 0, 0, archiveRows: 120);
        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $dup['status']);
    }

    #[Test]
    public function cancelling_is_reported_as_cancelled_with_the_true_numbers(): void
    {
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 40, failed: 0, remaining: 60, cancelled: true);

        $this->assertSame(CleanupRunItem::STATUS_CANCELLED, $r['status']);
        $this->assertContains(VerificationReconciler::REASON_CANCELLED, $r['reasons']);
    }

    #[Test]
    public function late_rows_are_informational_only(): void
    {
        $r = (new VerificationReconciler)->reconcile(targeted: 100, deleted: 100, failed: 0, remaining: 0, lateRows: 12);

        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $r['status']);
        $this->assertContains(VerificationReconciler::REASON_LATE_ROWS, $r['reasons']);
    }

    #[Test]
    public function a_dry_run_is_a_success_that_says_so(): void
    {
        $r = (new VerificationReconciler)->dryRun();

        $this->assertSame(CleanupRunItem::STATUS_SUCCESS, $r['status']);
        $this->assertSame([VerificationReconciler::REASON_DRY_RUN], $r['reasons']);
    }

    #[Test]
    public function the_run_verdict_is_the_worst_item_verdict(): void
    {
        $v = new VerificationReconciler;

        $this->assertSame('success', $v->runStatus([]));
        $this->assertSame('success', $v->runStatus(['success', 'success']));
        $this->assertSame('partial', $v->runStatus(['success', 'partial']));
        $this->assertSame('partial', $v->runStatus(['success', 'skipped_guard']), 'a skipped target means the run did not clean everything');
        $this->assertSame('partial', $v->runStatus(['success', 'blocked_busy']));
        $this->assertSame('cancelled', $v->runStatus(['success', 'cancelled', 'partial']));
        $this->assertSame('failed', $v->runStatus(['partial', 'cancelled', 'failed', 'success']));
    }

    /* ---- OverDeleteGuard ----------------------------------------------- */

    #[Test]
    public function the_guard_trips_only_above_the_percentage_and_the_minimum_size(): void
    {
        $guard = new OverDeleteGuard;

        $this->assertNull($guard->evaluate(targeted: 4000, totalRows: 10000, maxPct: 50));
        $this->assertSame('over_delete_pct', $guard->evaluate(targeted: 6000, totalRows: 10000, maxPct: 50));
        $this->assertNull($guard->evaluate(targeted: 499, totalRows: 500, maxPct: 50), 'tiny deletes are exempt');
        $this->assertNull($guard->evaluate(targeted: 90000, totalRows: null, maxPct: 50), 'unknown size cannot be judged');
        $this->assertNull($guard->evaluate(targeted: 90000, totalRows: 0, maxPct: 50));
    }

    #[Test]
    public function effective_retention_never_drops_below_the_floor_or_one_day(): void
    {
        $guard = new OverDeleteGuard;

        $this->assertSame(30, $guard->effectiveDays(configuredDays: 3, floorDays: 30, defaultDays: 365));
        $this->assertSame(1, $guard->effectiveDays(configuredDays: 0, floorDays: 0, defaultDays: 90));
        $this->assertSame(1, $guard->effectiveDays(configuredDays: -7, floorDays: 0, defaultDays: 90));
        $this->assertSame(90, $guard->effectiveDays(configuredDays: 90, floorDays: 7, defaultDays: 90));
        $this->assertSame(0, $guard->effectiveDays(configuredDays: 5, floorDays: 0, defaultDays: 0), 'not age-based');
    }

    /* ---- TargetRegistry ------------------------------------------------ */

    #[Test]
    #[DataProvider('businessTables')]
    public function business_tables_can_never_be_cleanup_targets(string $table): void
    {
        $this->expectException(InvalidArgumentException::class);

        TargetRegistry::assertTableAllowed($table);
    }

    /** @return array<string, array{string}> */
    public static function businessTables(): array
    {
        return array_combine(
            TargetRegistry::NEVER_PRUNE,
            array_map(fn (string $t) => [$t], TargetRegistry::NEVER_PRUNE),
        );
    }

    #[Test]
    public function log_tables_are_allowed_and_an_unknown_key_is_refused(): void
    {
        TargetRegistry::assertTableAllowed('search_logs');
        TargetRegistry::assertTableAllowed('cron_logs');
        $this->assertTrue(true);

        $registry = new TargetRegistry([]);
        $this->assertFalse($registry->has('anything'));
        $this->expectException(InvalidArgumentException::class);
        $registry->get('../../etc/passwd');
    }

    /* ---- Archiver ------------------------------------------------------ */

    #[Test]
    public function the_archive_is_a_readable_csv_gz_with_one_header_across_appends(): void
    {
        Storage::fake('local');
        $archiver = Archiver::forRun(7, 'Search Logs');

        $this->assertSame('cleanup-archives/7/search_logs.csv.gz', $archiver->path());

        $a = $archiver->append([['id' => 1, 'query' => 'brake pad'], ['id' => 2, 'query' => 'oil, filter']]);
        $b = $archiver->append([(object) ['id' => 3, 'query' => 'a "quoted" one']]);
        $this->assertSame(2, $a);
        $this->assertSame(1, $b);
        $this->assertSame(0, $archiver->append([]));

        $gz = gzopen(Storage::disk('local')->path($archiver->path()), 'rb');
        $csv = '';
        while (! gzeof($gz)) {
            $csv .= gzread($gz, 8192);
        }
        gzclose($gz);

        $lines = array_values(array_filter(explode("\n", $csv)));
        $this->assertSame('id,query', $lines[0]);
        $this->assertCount(4, $lines, 'one header + three rows, header not repeated by the second append');
        $this->assertStringContainsString('"oil, filter"', $csv);
    }

    #[Test]
    public function spreadsheet_formulas_in_archived_cells_are_neutralised(): void
    {
        Storage::fake('local');
        $archiver = Archiver::forRun(8, 'x');
        $archiver->append([['q' => '=HYPERLINK("http://evil")', 'n' => -5, 'p' => '+cmd', 'ok' => 'plain']]);

        $gz = gzopen(Storage::disk('local')->path($archiver->path()), 'rb');
        $csv = (string) gzread($gz, 8192);
        gzclose($gz);

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringContainsString(',-5,', $csv, 'a plain negative number must stay a number');
    }

    #[Test]
    public function archiving_is_refused_for_huge_or_disk_filling_exports(): void
    {
        $this->assertNull(Archiver::refusalReason(1_000, 10_000_000_000));
        $this->assertSame('too_many_rows_to_archive', Archiver::refusalReason(Archiver::MAX_ROWS + 1, null));
        $this->assertSame('archive_would_fill_disk', Archiver::refusalReason(1_000_000, 100_000_000), '300 MB of a 100 MB disk');
        $this->assertNull(Archiver::refusalReason(1_000_000, null), 'unknown free space cannot refuse');
    }

    /* ---- DiskUsageService ---------------------------------------------- */

    #[Test]
    public function disk_numbers_and_levels_follow_the_thresholds(): void
    {
        $disk = fn (float $total, float $free) => new DiskUsageService('/x', fn () => $total, fn () => $free);

        $d = $disk(1000, 250);
        $this->assertSame(1000, $d->totalBytes());
        $this->assertSame(250, $d->freeBytes());
        $this->assertSame(750, $d->usedBytes());
        $this->assertSame(75.0, $d->usedPercent());
        $this->assertSame('ok', $d->level(80, 95));

        $this->assertSame('warn', $disk(1000, 150)->level(80, 95));   // 85%
        $this->assertSame('fail', $disk(1000, 20)->level(80, 95));    // 98%
    }

    #[Test]
    public function an_unreadable_disk_reports_unknown_never_zero(): void
    {
        $d = new DiskUsageService('/x', fn () => false, fn () => false);

        $this->assertNull($d->totalBytes());
        $this->assertNull($d->freeBytes());
        $this->assertNull($d->usedPercent());
        $this->assertSame('unknown', $d->level());
        $this->assertSame(['total' => null, 'free' => null, 'used' => null, 'used_pct' => null], $d->snapshot());
    }

    /* ---- CleanupProgress ----------------------------------------------- */

    #[Test]
    public function a_running_run_never_shows_100_percent_but_a_finished_success_does(): void
    {
        $running = new CleanupRun(['status' => CleanupRun::STATUS_RUNNING, 'percent' => 100]);
        $running->id = 1;
        $this->assertSame(99, CleanupProgress::fromRun($running)->percent);
        $this->assertFalse(CleanupProgress::fromRun($running)->done);

        $ok = new CleanupRun(['status' => CleanupRun::STATUS_SUCCESS, 'percent' => 87]);
        $ok->id = 2;
        $this->assertSame(100, CleanupProgress::fromRun($ok)->percent);
        $this->assertTrue(CleanupProgress::fromRun($ok)->done);

        $partial = new CleanupRun(['status' => CleanupRun::STATUS_PARTIAL, 'percent' => 88]);
        $partial->id = 3;
        $this->assertSame(88, CleanupProgress::fromRun($partial)->percent, 'a partial run must not claim 100%');
    }

    #[Test]
    public function the_eta_is_hidden_until_there_is_enough_signal(): void
    {
        $this->assertNull(CleanupProgress::eta(2, 60));
        $this->assertNull(CleanupProgress::eta(50, 5));
        $this->assertNull(CleanupProgress::eta(100, 600));
        $this->assertSame(60, CleanupProgress::eta(50, 60));
        $this->assertSame(270, CleanupProgress::eta(10, 30));
    }
}
