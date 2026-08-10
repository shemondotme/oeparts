<?php

namespace App\Listeners;

use App\Enums\LogStatus;
use App\Models\CronLog;
use App\Support\ScheduleCommandName;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Str;

/**
 * CronLogResource has a full admin UI (list, view, "failed today" nav badge)
 * but nothing ever wrote a CronLog row — every scheduled command in
 * routes/console.php ran invisibly, so the page was permanently empty and
 * the "failed today" badge always read 0 whether or not anything failed.
 */
class LogScheduledTaskRun
{
    private static array $startedAt = [];

    public function starting(ScheduledTaskStarting $event): void
    {
        self::$startedAt[spl_object_id($event->task)] = microtime(true);
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        unset(self::$startedAt[spl_object_id($event->task)]);

        if ($event->task->exitCode !== 0) {
            // A non-zero exit throws right after this and triggers
            // ScheduledTaskFailed for the same Event instance — let that
            // handler write the one row for this run instead of double-logging.
            return;
        }

        $this->log($event->task, LogStatus::Success, (int) round($event->runtime * 1000));
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $startedAt = self::$startedAt[spl_object_id($event->task)] ?? null;
        unset(self::$startedAt[spl_object_id($event->task)]);

        $durationMs = $startedAt !== null ? (int) round((microtime(true) - $startedAt) * 1000) : 0;

        $this->log($event->task, LogStatus::Failed, $durationMs, Str::limit($event->exception->getMessage(), 2000));
    }

    private function log(Event $task, LogStatus $status, int $durationMs, ?string $output = null): void
    {
        try {
            CronLog::create([
                'job_name'    => Str::limit(ScheduleCommandName::for($task), 100, ''),
                'status'      => $status,
                'duration_ms' => max(0, $durationMs),
                'output'      => $output,
                'ran_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
