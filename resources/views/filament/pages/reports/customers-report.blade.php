<x-filament-panels::page>
    {{-- Header filter controls --}}
    <div class="flex justify-end mb-6">
        <x-filament::input.wrapper class="shadow-sm w-full max-w-xs">
            <x-filament::input.select wire:model.live="period" class="font-mono text-xs uppercase tracking-wider">
                <option value="7">Last 7 Days</option>
                <option value="30">Last 30 Days</option>
                <option value="90">Last 90 Days</option>
                <option value="365">Last 12 Months</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>


    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <x-filament::section class="border-s-4 border-primary-500 dark:border-primary-600 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest font-mono">Total Customers</div>
                    <div class="text-3xl font-extrabold font-display text-gray-900 dark:text-white mt-2">{{ $this->getTotalCustomers() }}</div>
                </div>
                <div class="p-3 rounded-xl bg-primary-50 dark:bg-primary-950/40 text-primary-600 dark:text-primary-400">
                    <x-heroicon-o-users class="w-6 h-6" />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="border-s-4 border-success-500 dark:border-success-600 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest font-mono">New Registered</div>
                    <div class="text-3xl font-extrabold font-display text-gray-900 dark:text-white mt-2">{{ $this->getNewCustomers() }}</div>
                </div>
                <div class="p-3 rounded-xl bg-success-50 dark:bg-success-950/40 text-success-600 dark:text-success-400">
                    <x-heroicon-o-user-plus class="w-6 h-6" />
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="border-s-4 border-amber-500 dark:border-amber-600 bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest font-mono">Repeat Purchases</div>
                    <div class="text-3xl font-extrabold font-display text-gray-900 dark:text-white mt-2">{{ $this->getRepeatCustomers() }}</div>
                </div>
                <div class="p-3 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-arrow-path class="w-6 h-6" />
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Customer Growth chart widget --}}
    <x-filament::section heading="Acquisition Trend Over Time" class="mb-6">
        <div class="h-68">
            @livewire(\App\Filament\Widgets\CustomerGrowthChart::class, ['period' => $this->period])
        </div>
    </x-filament::section>

    {{-- Top Customers by Spend table --}}
    <x-filament::section heading="Top Customers by Spend">
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-950">
            <table class="w-full text-sm text-left divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-900/60">
                    <tr class="text-xs font-bold tracking-wider text-gray-500 dark:text-gray-400 uppercase font-mono">
                        <th scope="col" class="px-6 py-4">Name</th>
                        <th scope="col" class="px-6 py-4">Email Address</th>
                        <th scope="col" class="px-6 py-4 text-center">Completed Orders</th>
                        <th scope="col" class="px-6 py-4 text-right">Total Investment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-transparent">
                    @forelse($this->getTopCustomers() as $customer)
                        <tr class="text-gray-700 dark:text-gray-300 hover:bg-gray-50/50 dark:hover:bg-gray-800/20 transition-colors duration-150">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                {{ $customer['name'] ?? 'Guest Customer' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $customer['email'] ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-gray-900 dark:text-white">
                                {{ $customer['order_count'] }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold text-primary-600 dark:text-primary-400">
                                &euro;{{ number_format($customer['total_spent'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                No customer spending data found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
