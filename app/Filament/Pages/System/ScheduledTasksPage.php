<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use App\Models\CronLog;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

class ScheduledTasksPage extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Scheduled Tasks';

    protected string $view = 'filament.pages.system.scheduled-tasks';

    protected static ?string $pollingInterval = '60s';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return 40;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getScheduledTasks(): array
    {
        $events = Schedule::events();

        $tasks = [];
        foreach ($events as $event) {
            $command = $event->command;
            if ($command === null) {
                continue;
            }

            $description = Artisan::all()[$command]->description ?? $command;

            $frequency = $this->inferFrequencyTag($event->getExpression());

            $tasks[] = [
                'command' => $command,
                'description' => $description,
                'schedule' => $this->getScheduleDescription($event->command, $frequency),
                'frequency' => $frequency,
            ];
        }

        return $tasks;
    }

    /**
     * Illuminate\Console\Scheduling\Event has no public method exposing which
     * frequency helper (->daily(), ->hourly(), etc.) built the event — only
     * the resulting cron expression. Map known cron patterns back to a tag.
     */
    private function inferFrequencyTag(string $expression): ?string
    {
        return match ($expression) {
            '* * * * *' => 'everyMinute',
            '*/5 * * * *' => 'everyFiveMinutes',
            '*/10 * * * *' => 'everyTenMinutes',
            '*/15 * * * *' => 'everyFifteenMinutes',
            '*/30 * * * *' => 'everyThirtyMinutes',
            '0 * * * *' => 'hourly',
            '0 0 * * *' => 'daily',
            '0 0 * * 0' => 'weekly',
            '0 0 1 * *' => 'monthly',
            '0 0 1 1 *' => 'yearly',
            default => null,
        };
    }

    private function getScheduleDescription(?string $command, ?string $frequency): string
    {
        if ($command === null) {
            return 'Unknown';
        }

        if ($frequency === null) {
            return 'Custom';
        }

        $frequencyMap = [
            'everyMinute' => 'Every minute',
            'everyFiveMinutes' => 'Every 5 minutes',
            'everyTenMinutes' => 'Every 10 minutes',
            'everyFifteenMinutes' => 'Every 15 minutes',
            'everyThirtyMinutes' => 'Every 30 minutes',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
        ];

        return $frequencyMap[$frequency] ?? ucfirst(str_replace('_', ' ', $frequency));
    }

    public function getRecentLogs(): \Illuminate\Support\Collection
    {
        return CronLog::orderByDesc('ran_at')
            ->limit(20)
            ->get();
    }

    public function runTask(string $command): void
    {
        try {
            $exitCode = Artisan::call($command);

            if ($exitCode === 0) {
                Notification::make()
                    ->title('Task executed successfully')
                    ->body("Command: {$command}")
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title('Task failed')
                    ->body("Command: {$command} returned exit code {$exitCode}")
                    ->danger()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Task error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
