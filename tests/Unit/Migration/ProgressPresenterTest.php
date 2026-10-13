<?php

namespace Tests\Unit\Migration;

use App\Models\BackupRun;
use App\Services\Backup\BackupProgress;
use App\Services\Migration\Export\ProgressPresenter;
use App\Services\Migration\MigrationProgress;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.11b: an export shown the way every migration run is shown.
 */
class ProgressPresenterTest extends TestCase
{
    private function run_(array $meta = [], string $status = BackupRun::STATUS_RUNNING): BackupRun
    {
        $run = new BackupRun;
        $run->id = 12;
        $run->status = $status;
        $run->started_at = Carbon::parse('2026-10-14 10:00:00');
        $run->meta = $meta;

        return $run;
    }

    private function meta(): array
    {
        return [
            'export_plan' => ['estimate' => ['total_rows' => 1000, 'files' => ['count' => 50]], 'warnings' => [['id' => 'site_logo'], ['id' => 'product_pictures']]],
            'export_report' => ['db' => ['rows_total' => 400], 'files' => ['archived' => 12]],
        ];
    }

    #[Test]
    public function each_stage_has_its_own_plain_phase_in_order(): void
    {
        $presenter = new ProgressPresenter;
        $seen = [];

        foreach (['plan', 'db', 'files', 'verify', 'pack'] as $stage) {
            $shown = $presenter->present(BackupProgress::running($this->run_($this->meta()), $stage, null, 40), $this->run_($this->meta()));
            $seen[$stage] = [$shown->phase, $shown->phaseIndex, $shown->phaseCount];
        }

        $this->assertSame([
            'plan' => ['preparing', 1, 5], 'db' => ['database', 2, 5], 'files' => ['files', 3, 5], 'verify' => ['checking', 4, 5], 'pack' => ['packaging', 5, 5],
        ], $seen);
    }

    #[Test]
    public function a_running_export_carries_its_counters_warnings_and_is_resumable(): void
    {
        $run = $this->run_($this->meta());

        $shown = (new ProgressPresenter)->present(BackupProgress::running($run, 'db', 'orders: 40 rows', 37), $run);

        $this->assertSame(MigrationProgress::RUNNING, $shown->status);
        $this->assertSame('export', $shown->kind);
        $this->assertSame('12', $shown->runId);
        $this->assertSame(37, $shown->percent());
        $this->assertSame('orders: 40 rows', $shown->message);
        $this->assertSame(400, $shown->counters['rows']);
        $this->assertSame(1000, $shown->counters['rowsTotal']);
        $this->assertSame(12, $shown->counters['files']);
        $this->assertSame(50, $shown->counters['filesTotal']);
        $this->assertSame(['site_logo', 'product_pictures'], $shown->warnings);
        $this->assertTrue($shown->resumable);
        $this->assertNull($shown->error);
    }

    #[Test]
    public function without_a_message_the_phase_label_is_shown(): void
    {
        $run = $this->run_($this->meta());

        $shown = (new ProgressPresenter)->present(BackupProgress::running($run, 'files', null, 50), $run);

        $this->assertSame('Copying the files', $shown->message);
    }

    #[Test]
    public function a_finished_export_says_the_package_is_ready_at_one_hundred_percent(): void
    {
        $run = $this->run_($this->meta(), BackupRun::STATUS_SUCCESS);
        $run->finished_at = Carbon::parse('2026-10-14 10:05:00');

        $shown = (new ProgressPresenter)->present(BackupProgress::success($run), $run);

        $this->assertSame(MigrationProgress::SUCCESS, $shown->status);
        $this->assertSame(100, $shown->percent());
        $this->assertSame('The package is ready.', $shown->message);
        $this->assertSame(300, $shown->elapsedSeconds);
        $this->assertFalse($shown->resumable);
        $this->assertTrue($shown->isTerminal());
    }

    #[Test]
    public function a_failed_export_carries_the_catalogue_code_of_why(): void
    {
        $run = $this->run_(array_merge($this->meta(), ['error_code' => 'lock_lost']), BackupRun::STATUS_FAILED);

        $shown = (new ProgressPresenter)->present(BackupProgress::failed($run, 'internal detail'), $run);

        $this->assertSame(MigrationProgress::FAILED, $shown->status);
        $this->assertSame(['code' => 'lock_lost', 'params' => []], $shown->error);
        $this->assertTrue($shown->isTerminal());
    }

    #[Test]
    public function a_failure_with_no_recorded_code_still_has_one_so_the_screen_can_show_words(): void
    {
        $run = $this->run_($this->meta(), BackupRun::STATUS_FAILED);

        $shown = (new ProgressPresenter)->present(BackupProgress::failed($run, 'x'), $run);

        $this->assertNotNull($shown->error);
        $this->assertSame('import_failed_step', $shown->error['code']);
    }

    #[Test]
    public function an_unknown_stage_does_not_break_the_presenter(): void
    {
        $run = $this->run_();

        $shown = (new ProgressPresenter)->present(BackupProgress::running($run, 'mystery', 'hmm', 10), $run);

        $this->assertSame('', $shown->phase);
        $this->assertSame(0, $shown->phaseIndex);
        $this->assertSame('hmm', $shown->message);
    }
}
