<?php

namespace App\Filament\Widgets\Seo;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeatureAdoptionWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $detailPagesEnabled = filter_var(settings('seo.detail_pages_enabled', false), FILTER_VALIDATE_BOOLEAN);

        $total = Product::query()->active()->count();
        $withOwnImage = $total > 0
            ? Product::query()->active()->whereHas('images', fn ($q) => $q->where('is_featured', true))->count()
            : 0;
        $ownImagePercent = $total > 0 ? round(100 * $withOwnImage / $total) : 0;

        return [
            Stat::make('Per-Product Detail Pages', $detailPagesEnabled ? 'Enabled' : 'Disabled')
                ->description($detailPagesEnabled
                    ? '/parts/{oem}/{id}-slug pages are live'
                    : 'Search results page handles everything — toggle on the Control Center\'s General tab')
                ->descriptionIcon($detailPagesEnabled ? 'heroicon-o-check-circle' : 'heroicon-o-minus-circle')
                ->color($detailPagesEnabled ? 'success' : 'gray'),
            Stat::make('Own Product Images', "{$ownImagePercent}%")
                ->description(number_format($withOwnImage).' of '.number_format($total).' active products — the rest fall back to the manufacturer logo or a placeholder')
                ->descriptionIcon('heroicon-o-photo')
                ->color($ownImagePercent < 10 ? 'warning' : 'success'),
        ];
    }
}
