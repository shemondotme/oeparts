<x-filament-panels::page>
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

    {{-- Financial KPIs --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
        {{-- Revenue --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-success-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-success-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Gross Revenue</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">&euro;{{ number_format($this->getRevenue(), 2) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-success-50); color: var(--color-success-600); border: 1px solid var(--color-success-100);">
                    <x-heroicon-o-banknotes class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- Orders --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-brand-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-brand-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Total Orders</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">{{ number_format($this->getOrderCount()) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-brand-50); color: var(--color-brand-600); border: 1px solid var(--color-brand-100);">
                    <x-heroicon-o-shopping-bag class="w-5 h-5" />
                </div>
            </div>
        </div>

        {{-- AOV --}}
        <div class="op-card op-hover-lift p-6 relative overflow-hidden" style="border-left: 4px solid var(--color-accent-500);">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 rounded-full blur-2xl" style="background: var(--color-accent-500); opacity: 0.05;"></div>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Avg Order Value</div>
                    <div class="text-2xl font-black mt-1.5 font-mono" style="color: var(--color-text-primary);">&euro;{{ number_format($this->getAvgOrderValue(), 2) }}</div>
                </div>
                <div class="p-2.5 rounded-xl" style="background: var(--color-accent-50); color: var(--color-accent-600); border: 1px solid var(--color-accent-100);">
                    <x-heroicon-o-calculator class="w-5 h-5" />
                </div>
            </div>
        </div>
    </div>

    {{-- Revenue Over Time Chart --}}
    <div class="op-card p-6 mb-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="flex items-center gap-2 mb-4">
            <x-heroicon-o-chart-bar class="w-4 h-4" style="color: var(--color-brand-500);" />
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Revenue Trend</h3>
        </div>
        @livewire(\App\Filament\Widgets\RevenueChart::class, ['period' => $this->period])
    </div>

    {{-- Top Products table --}}
    <div class="op-card overflow-hidden" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
        <div class="px-6 py-4" style="border-bottom: 1px solid var(--color-border-subtle);">
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Top Selling Products</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-[10px] font-bold tracking-wider uppercase font-mono" style="color: var(--color-text-muted); border-bottom: 1px solid var(--color-border-subtle);">
                        <th scope="col" class="px-6 py-3.5">Product</th>
                        <th scope="col" class="px-6 py-3.5 text-center">Qty Sold</th>
                        <th scope="col" class="px-6 py-3.5 text-right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->getTopProducts() as $product)
                        <tr style="border-bottom: 1px solid var(--color-border-subtle);" class="op-table-row">
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center font-mono tracking-wider text-xs font-semibold px-2.5 py-1 rounded-md"
                                    style="background: var(--color-bg-inset); color: var(--color-text-primary); border: 1px solid var(--color-border-default);">
                                    {{ $product['name'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold" style="color: var(--color-text-primary);">
                                {{ number_format($product['total_qty']) }}
                            </td>
                            <td class="px-6 py-4 text-right font-mono font-bold" style="color: var(--color-success-600);">
                                &euro;{{ number_format($product['total_revenue'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-sm font-medium" style="color: var(--color-text-muted);">
                                No sales data found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
