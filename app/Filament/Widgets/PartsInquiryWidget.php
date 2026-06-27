<?php

namespace App\Filament\Widgets;

use App\Enums\PartInquiryStatus;
use App\Filament\Resources\PartInquiryResource;
use Filament\Widgets\Widget;

class PartsInquiryWidget extends Widget
{
    use \App\Filament\Widgets\Concerns\HasWidgetRoles;
    use \App\Filament\Widgets\Concerns\InteractsWithDashboardCache;

    public function getDescription(): ?string
    {
        return 'Incoming part requests from customers';
    }

    protected string $view = 'filament.widgets.parts-inquiry';

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = -19;

    protected int|string|array $columnSpan = ['md' => 1, 'xl' => 1];

    protected function getViewData(): array
    {
        $d = $this->cachedWidgetData(fn (): array => [
            'today'    => \App\Models\PartInquiry::whereDate('created_at', now())->count(),
            'pending'  => \App\Models\PartInquiry::where('status', PartInquiryStatus::New->value)->count(),
            'thisWeek' => \App\Models\PartInquiry::where('created_at', '>=', now()->startOfWeek())->count(),
            'responded' => \App\Models\PartInquiry::whereNotNull('admin_note')
                ->where('created_at', '>=', now()->startOfWeek())->count(),
            'totalThisWeek' => \App\Models\PartInquiry::where('created_at', '>=', now()->startOfWeek())->count(),
            'avgResponseHours' => \App\Models\PartInquiry::whereNotNull('updated_at')
                ->where('updated_at', '>', \Illuminate\Support\Facades\DB::raw('created_at'))
                ->selectRaw(
                    \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite'
                        ? "COALESCE(AVG((julianday(updated_at) - julianday(created_at)) * 24), 0) as avg_hours"
                        : 'COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)), 0) as avg_hours'
                )
                ->value('avg_hours'),
        ]);

        $isEmpty = $d['today'] === 0 && $d['pending'] === 0 && $d['thisWeek'] === 0;

        $responseRate = (!$isEmpty && $d['totalThisWeek'] > 0)
            ? round(($d['responded'] / max($d['totalThisWeek'], 1)) * 100)
            : 0;

        $avgHours = round((float) ($d['avgResponseHours'] ?? 0));

        if ($avgHours <= 0) {
            $avgHoursLabel = 'N/A';
        } elseif ($avgHours < 24) {
            $avgHoursLabel = $avgHours . 'h';
        } else {
            $days = intdiv($avgHours, 24);
            $hours = $avgHours % 24;
            $avgHoursLabel = $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
        }

        $avgHoursColor = match (true) {
            $avgHours <= 0 => 'var(--color-text-muted)',
            $avgHours <= 4 => 'var(--color-success-600)',
            $avgHours <= 72 => 'var(--color-warning-600)',
            default => 'var(--color-danger-600)',
        };

        return [
            'today'          => $d['today'],
            'pending'        => $d['pending'],
            'thisWeek'       => $d['thisWeek'],
            'responseRate'   => $responseRate,
            'belowThreshold' => $responseRate > 0 && $responseRate < 90,
            'avgHours'       => $avgHours,
            'avgHoursLabel'  => $avgHoursLabel,
            'avgHoursColor'  => $avgHoursColor,
            'isEmpty'        => $isEmpty,
            'newUrl'         => PartInquiryResource::getUrl('index', [
                'tableFilters' => ['status' => ['value' => PartInquiryStatus::New->value]],
            ]),
            'indexUrl'       => PartInquiryResource::getUrl('index'),
        ];
    }
}
