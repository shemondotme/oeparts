<x-filament-panels::page>
    @include('filament.pages.reports.period-tabs')

    @livewire(\App\Filament\Widgets\Reports\CheckoutStats::class, ['period' => $period], key('checkout-stats-' . $period))

    @livewire(\App\Filament\Widgets\Reports\CheckoutFunnelChart::class, ['period' => $period], key('checkout-funnel-' . $period))
</x-filament-panels::page>
