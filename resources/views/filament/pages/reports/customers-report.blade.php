<x-filament-panels::page>
    @include('filament.components.admin-styles')
    {{-- Header filter controls --}}
    <div class="flex justify-end mb-6">
        <div class="flex items-center gap-2 p-1 rounded-xl" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); box-shadow: var(--shadow-1);">
            @foreach(['7' => '7d', '30' => '30d', '90' => '90d', '365' => '1y'] as $value => $label)
                <button
                    wire:click="$set('period', '{{ $value }}')"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all duration-200"
                    style="{{ $this->period === $value
                        ? 'background: var(--color-brand-600); color: white; box-shadow: var(--shadow-1);'
                        : 'color: var(--color-text-secondary);' }}"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        {{-- Total Customers --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-brand-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-brand-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Customers</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">{{ number_format($this->getTotalCustomers()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-brand-50); color: var(--color-brand-600); border: 1px solid var(--color-brand-100);">
                    <x-heroicon-o-users class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- New Registered --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-success-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-success-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">New Registered</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">{{ number_format($this->getNewCustomers()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-success-50); color: var(--color-success-600); border: 1px solid var(--color-success-100);">
                    <x-heroicon-o-user-plus class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- Repeat Purchases --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-accent-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-accent-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Repeat Purchases</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">{{ number_format($this->getRepeatCustomers()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-accent-50); color: var(--color-accent-600); border: 1px solid var(--color-accent-100);">
                    <x-heroicon-o-arrow-path class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Customer Growth Chart --}}
    <div class="op-card p-6 mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="flex items-center gap-2 mb-4">
            <x-heroicon-o-chart-bar class="w-4 h-4" style="color: var(--color-brand-500);" />
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Customer Growth</h3>
        </div>
        @livewire(\App\Filament\Widgets\CustomerGrowthChart::class, ['period' => $this->period])
    </div>

    {{-- Top Customers table --}}
    <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Top Customers by Spend</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-[10px] font-bold tracking-wider uppercase font-mono" style="color: var(--color-text-muted); border-bottom: 1px solid var(--color-border-subtle);">
                        <th scope="col" class="px-6 py-3.5">Name</th>
                        <th scope="col" class="px-6 py-3.5">Email</th>
                        <th scope="col" class="px-6 py-3.5 text-center">Orders</th>
                        <th scope="col" class="px-6 py-3.5 text-right">Total Spent</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->getTopCustomers() as $customer)
                        <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                            <td class="px-6 py-4 font-medium" style="color: var(--color-text-primary);">
                                {{ $customer['name'] ?? 'Guest Customer' }}
                            </td>
                            <td class="px-6 py-4 font-mono text-xs" style="color: var(--color-text-muted);">
                                {{ $customer['email'] ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-mono font-bold text-xs"
                                    style="background: var(--color-bg-inset); color: var(--color-text-primary); border: 1px solid var(--color-border-default);">
                                    {{ $customer['order_count'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold" style="color: var(--color-brand-600);">
                                &euro;{{ number_format($customer['total_spent'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-sm font-medium" style="color: var(--color-text-muted);">
                                No customer spending data found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
