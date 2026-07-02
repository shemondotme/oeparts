<x-filament-panels::page>
    {{-- Period selector (native Filament select) --}}
    @include('filament.pages.reports.period-tabs')

    {{-- All analytics are native Filament widgets, re-mounted when the period
         changes (the key includes $period). --}}
    @livewire(\App\Filament\Widgets\Reports\SalesStats::class, ['period' => $period], key('sales-stats-' . $period))

    @livewire(\App\Filament\Widgets\Reports\SalesRevenueChart::class, ['period' => $period], key('sales-chart-' . $period))

    @livewire(\App\Filament\Widgets\Reports\SalesTopProducts::class, ['period' => $period], key('sales-top-' . $period))
</x-filament-panels::page>
