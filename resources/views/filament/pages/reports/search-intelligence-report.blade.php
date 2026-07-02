<x-filament-panels::page>
    @include('filament.pages.reports.period-tabs')

    @livewire(\App\Filament\Widgets\Reports\SearchStats::class, ['period' => $period], key('search-stats-' . $period))

    @livewire(\App\Filament\Widgets\Reports\SearchTopSearches::class, ['period' => $period], key('search-top-' . $period))

    @livewire(\App\Filament\Widgets\Reports\SearchFailedQueries::class, ['period' => $period], key('search-failed-' . $period))
</x-filament-panels::page>
