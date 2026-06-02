<x-filament-panels::page>
    {{-- Header filter controls --}}
    <div class="flex justify-end mb-6">
        <x-filament::input.wrapper class="shadow-sm w-full max-w-xs">
            <x-filament::input.select wire:model.live="period" class="font-mono text-xs uppercase tracking-wider">
                <option value="7">Last 7 Days</option>
                <option value="30">Last 30 Days</option>
                <option value="90">Last 90 Days</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>


    {{-- Search metrics --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <x-filament::section class="border-s-4 border-primary-500 dark:border-primary-600 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest font-mono">Total Searches Logged</div>
                    <div class="text-3xl font-extrabold font-display text-gray-900 dark:text-white mt-2">{{ $this->getTotalSearches() }}</div>
                </div>
                <div class="p-3 rounded-xl bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400">
                    <x-heroicon-o-magnifying-glass class="w-6 h-6" />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="border-s-4 border-danger-500 dark:border-danger-600 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest font-mono">Unresolved Queries (No Inquiry)</div>
                    <div class="text-3xl font-extrabold font-display text-danger-600 dark:text-danger-500 mt-2">{{ $this->getUnresolvedSearches() }}</div>
                </div>
                <div class="p-3 rounded-xl bg-danger-50 dark:bg-danger-950/40 text-danger-600 dark:text-danger-400">
                    <x-heroicon-o-exclamation-triangle class="w-6 h-6" />
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Top Search Queries table --}}
    <x-filament::section heading="Top OEM & Part Search Queries" class="mb-6">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
            <table class="w-full text-sm text-left divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-900/60">
                    <tr class="text-xs font-bold tracking-wider text-gray-500 dark:text-gray-400 uppercase font-mono">
                        <th scope="col" class="px-6 py-4">OEM / Search Query</th>
                        <th scope="col" class="px-6 py-4 text-center">Execution Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-transparent">
                    @forelse($this->getTopSearches() as $search)
                        <tr class="text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors duration-150">
                            <td class="px-6 py-4 font-mono text-sm tracking-wider text-primary-600 dark:text-primary-400 font-bold uppercase">
                                {{ $search['search_query'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-gray-900 dark:text-white">
                                {{ number_format($search['count']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No search queries recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    {{-- Unresolved Queries table --}}
    <x-filament::section heading="Unresolved Failures (Zero Matches & No Inquiry Submitted)">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
            <table class="w-full text-sm text-left divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-900/60">
                    <tr class="text-xs font-bold tracking-wider text-gray-500 dark:text-gray-400 uppercase font-mono">
                        <th scope="col" class="px-6 py-4">Failed OEM Query</th>
                        <th scope="col" class="px-6 py-4 text-center">Unresolved Attempt Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-transparent">
                    @forelse($this->getFailedQueries() as $failed)
                        <tr class="text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors duration-150">
                            <td class="px-6 py-4 font-mono text-sm tracking-wider text-danger-600 dark:text-danger-400 font-bold uppercase">
                                {{ $failed['search_query'] }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-gray-900 dark:text-white">
                                {{ number_format($failed['count']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No unresolved failed queries recorded for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
