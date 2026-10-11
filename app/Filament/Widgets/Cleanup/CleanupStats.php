<?php

namespace App\Filament\Widgets\Cleanup;

use App\Filament\Pages\System\CleanupDashboard;
use App\Models\CleanupDiskSample;
use App\Models\CleanupRun;
use App\Services\Cleanup\CleanupOverview;
use App\Services\Cleanup\CleanupText;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

/**
 * The four headline numbers of the Cleanup page: disk, what a cleanup could give back, whether the schedule is on,
 * and how the last real cleanup ended. Reads cached tables only (no counting, no directory walk). Only the Cleanup
 * page embeds it; it is not a Dashboard widget.
 */
class CleanupStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return CleanupDashboard::canAccess();
    }

    /** Re-render when the page starts, finishes or changes something (the widget is a separate component). */
    #[On('cleanup-changed')]
    public function refreshStats(): void {}

    protected function getStats(): array
    {
        $overview = app(CleanupOverview::class);
        $disk = $overview->disk();
        $reclaimable = $overview->reclaimableBytes();
        $last = CleanupRun::query()->where('mode', CleanupRun::MODE_EXECUTE)->orderByDesc('id')->first();

        $trend = CleanupDiskSample::query()->orderByDesc('measured_at')->limit(14)->pluck('used_bytes')
            ->reverse()->map(fn ($bytes) => round($bytes / 1_073_741_824, 2))->values()->all();

        $level = $disk['level'];
        $usedPct = $disk['used_pct'];

        $growth = $disk['growth_bytes'] === null
            ? null
            : ($disk['growth_bytes'] >= 0 ? '+' : '−').CleanupText::bytes(abs($disk['growth_bytes'])).' in '.$disk['growth_days'].' day(s)';

        $diskStat = Stat::make('Disk used', $usedPct === null ? 'Unknown' : round($usedPct, 1).'%')
            ->description($disk['free'] === null ? 'Disk size not readable' : CleanupText::bytes($disk['free']).' free of '.CleanupText::bytes($disk['total']).($growth ? ' · '.$growth : ''))
            ->descriptionIcon($level === 'ok' ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color(match ($level) {
                'ok' => 'success',
                'warn' => 'warning',
                'fail' => 'danger',
                default => 'gray',
            });
        if (count($trend) >= 2) {
            $diskStat->chart($trend);
        }

        $auto = filter_var(settings('cleanup.auto_enabled', config('cleanup.auto_enabled')), FILTER_VALIDATE_BOOLEAN);

        return [
            $diskStat,

            Stat::make('Can be cleaned', $reclaimable > 0 ? '~'.CleanupText::bytes($reclaimable) : 'Not measured yet')
                ->description($reclaimable > 0 ? 'Estimate across everything eligible now' : 'Use "Refresh numbers" to measure')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('primary'),

            Stat::make('Automatic cleaning', $auto ? 'On' : 'Off')
                ->description('Runs '.settings('cleanup.window_start', config('cleanup.window_start')).' – '.settings('cleanup.window_end', config('cleanup.window_end')))
                ->descriptionIcon('heroicon-m-clock')
                ->color($auto ? 'success' : 'gray'),

            Stat::make('Last cleanup', $last ? CleanupText::status($last->status) : 'Never')
                ->description($last ? ($last->finished_at ?? $last->created_at)->diffForHumans() : 'Nothing has been cleaned yet')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($last === null ? 'gray' : CleanupText::color($last->status)),
        ];
    }
}
