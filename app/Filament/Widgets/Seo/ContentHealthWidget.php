<?php

namespace App\Filament\Widgets\Seo;

use App\Models\Product;
use App\Support\LocaleRegistry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Translation completeness and manual-vs-auto description coverage —
 * computed via driver-aware JSON_EXTRACT counts (never loads whole rows
 * into PHP, unlike Product::hasRealTranslation(), which is only cheap
 * per-model) so this stays fast at 100k+ products. Cached briefly since a
 * full-table COUNT on a JSON_EXTRACT expression can't use an index.
 */
class ContentHealthWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $data = Cache::remember('admin:seo-health:content', 300, fn () => $this->compute());

        return [
            Stat::make('Translation Coverage', $data['translationSummary'])
                ->description('Active products with a genuine (non-fallback) name per locale')
                ->descriptionIcon('heroicon-o-language')
                ->color('primary'),
            Stat::make('Manual Descriptions', "{$data['manualPercent']}%")
                ->description(number_format($data['manualCount']).' of '.number_format($data['total']).' active products — the rest auto-compose from fitment/condition/OEM facts')
                ->descriptionIcon('heroicon-o-pencil-square')
                ->color($data['manualPercent'] < 20 ? 'warning' : 'success'),
        ];
    }

    private function compute(): array
    {
        $total = Product::query()->active()->count();

        if ($total === 0) {
            return ['translationSummary' => 'No active products yet', 'manualPercent' => 0, 'manualCount' => 0, 'total' => 0];
        }

        $default = LocaleRegistry::defaultCode();
        $parts = [];

        foreach (LocaleRegistry::codes() as $code) {
            if ($code === $default) {
                continue;
            }

            $count = Product::query()->active()->where(fn ($q) => $q->whereRaw($this->jsonNotEmpty('name', $code)))->count();
            $parts[] = $code.' '.round(100 * $count / $total).'%';
        }

        $manualCount = Product::query()->active()->where(fn ($q) => $q->whereRaw($this->jsonNotEmpty('description', $default)))->count();

        return [
            'translationSummary' => $parts === [] ? 'Only one active locale configured' : implode(' / ', $parts),
            'manualPercent' => round(100 * $manualCount / $total),
            'manualCount' => $manualCount,
            'total' => $total,
        ];
    }

    private function jsonNotEmpty(string $column, string $locale): string
    {
        $path = DB::connection()->getDriverName() === 'sqlite'
            ? "json_extract({$column}, '$.\"{$locale}\"')"
            : "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.\"{$locale}\"'))";

        return "{$path} IS NOT NULL AND TRIM({$path}) != ''";
    }
}
