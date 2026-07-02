<x-filament-panels::page>
    @include('filament.pages.reports.period-tabs')

    @livewire(\App\Filament\Widgets\Reports\CustomersStats::class, ['period' => $period], key('cust-stats-' . $period))

    @livewire(\App\Filament\Widgets\Reports\CustomersGrowthChart::class, ['period' => $period], key('cust-chart-' . $period))

    @livewire(\App\Filament\Widgets\Reports\CustomersTop::class, ['period' => $period], key('cust-top-' . $period))
</x-filament-panels::page>
