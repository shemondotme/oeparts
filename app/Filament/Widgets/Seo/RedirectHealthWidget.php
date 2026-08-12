<?php

namespace App\Filament\Widgets\Seo;

use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RedirectHealthWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeRedirects = Redirect::query()->active()->count();
        $unresolved404s = NotFoundLog::query()->where('resolved', false)->count();

        $loopCount = count(app(RedirectLoopDetector::class)->findAllLoops());

        return [
            Stat::make('Active Redirects', number_format($activeRedirects))
                ->description($loopCount > 0
                    ? "{$loopCount} chain(s) loop back on themselves — needs attention"
                    : 'No loops detected in a full sweep')
                ->descriptionIcon($loopCount > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->color($loopCount > 0 ? 'danger' : 'success'),
            Stat::make('Unresolved 404s', number_format($unresolved404s))
                ->description('Distinct dead-link paths without a redirect yet — candidates for a new Redirect row')
                ->descriptionIcon('heroicon-o-link-slash')
                ->color($unresolved404s > 0 ? 'warning' : 'success'),
        ];
    }
}
