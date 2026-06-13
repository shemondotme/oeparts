<?php

namespace App\Filament\Pages\System;

use App\Filament\Clusters\System;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FailedJobsPage extends Page
{
    protected static ?string $cluster = System::class;

    protected static ?string $title = 'Failed Jobs';

    protected string $view = 'filament.pages.system.failed-jobs';

    protected static ?string $pollingInterval = '30s';

    public static function getNavigationGroup(): ?string
    {
        return System::getNavigationGroup();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = DB::table('failed_jobs')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationSort(): ?int
    {
        return 15;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-x-circle';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()->hasRole('super_admin');
    }

    public function getFailedJobs(): \Illuminate\Support\Collection
    {
        return DB::table('failed_jobs')
            ->orderBy('failed_at', 'desc')
            ->get();
    }

    public function getTotalCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    public function getCountByConnection(): array
    {
        return DB::table('failed_jobs')
            ->select('connection', DB::raw('count(*) as total'))
            ->groupBy('connection')
            ->pluck('total', 'connection')
            ->toArray();
    }

    public function getCountByQueue(): array
    {
        return DB::table('failed_jobs')
            ->select('queue', DB::raw('count(*) as total'))
            ->groupBy('queue')
            ->pluck('total', 'queue')
            ->toArray();
    }

    public function retryJob(string $id): void
    {
        Artisan::call('queue:retry', [$id]);

        Notification::make()
            ->title('Job retried')
            ->success()
            ->send();
    }

    public function deleteJob(string $id): void
    {
        Artisan::call('queue:forget', [$id]);

        Notification::make()
            ->title('Job deleted')
            ->success()
            ->send();
    }

    public function deleteAll(): void
    {
        Artisan::call('queue:flush');

        Notification::make()
            ->title('All failed jobs cleared')
            ->success()
            ->send();
    }
}
