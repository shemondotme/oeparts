<?php

namespace App\Filament\Widgets\Reports\Concerns;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shared period handling for report widgets. The page passes $period on mount
 * ('1','7','30','90','365'); '1' means "Today" (since midnight), matching the
 * dashboard's HasDashboardPeriod semantics, not the last 24 hours.
 */
trait HasReportPeriod
{
    public ?string $period = '30';

    protected function periodStart(): CarbonInterface
    {
        return $this->period === '1'
            ? Carbon::today()
            : Carbon::now()->subDays((int) $this->period);
    }
}
