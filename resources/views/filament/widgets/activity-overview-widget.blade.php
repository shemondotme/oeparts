<x-filament-widgets::widget class="fi-wi-activity-overview op-fade-in" wire:poll.60s>
    <div class="p-5 h-full flex flex-col" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); border-radius: var(--radius-lg); box-shadow: var(--shadow-1);">
        <div class="flex items-center justify-between mb-4" style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--color-border-subtle);">
            <h2 class="text-[10px] font-bold uppercase tracking-widest" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                Activity Overview
            </h2>
            <a href="{{ route('filament.admin.resources.activity-logs.index') }}" wire:navigate class="text-[10px] font-semibold uppercase tracking-wider transition-colors" style="color: var(--color-accent-500);">
                View All &rarr;
            </a>
        </div>
        
        <div class="space-y-3.5 flex-1">
            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2.5" style="color: var(--color-text-secondary);">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-brand-600);"></span>
                    <span>Total Orders</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-bold font-mono" style="color: var(--color-text-primary);">{{ number_format($totalOrders) }}</span>
                    @if(!empty($orderSparkline))
                    <svg class="op-sparkline" width="48" height="16" viewBox="0 0 48 16">
                        @php
                            $max = max(array_merge($orderSparkline, [1]));
                            $points = [];
                            foreach ($orderSparkline as $i => $val) {
                                $x = ($i / max(count($orderSparkline) - 1, 1)) * 48;
                                $y = 15 - (($val / $max) * 13);
                                $points[] = "{$x},{$y}";
                            }
                        @endphp
                        <polyline fill="none" stroke="var(--color-brand-500)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" points="{{ implode(' ', $points) }}" />
                    </svg>
                    @endif
                </div>
            </div>

            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2.5" style="color: var(--color-text-secondary);">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-accent-500);"></span>
                    <span>Total Customers</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-bold font-mono" style="color: var(--color-text-primary);">{{ number_format($totalCustomers) }}</span>
                    @if(!empty($customerSparkline))
                    <svg class="op-sparkline" width="48" height="16" viewBox="0 0 48 16">
                        @php
                            $max = max(array_merge($customerSparkline, [1]));
                            $points = [];
                            foreach ($customerSparkline as $i => $val) {
                                $x = ($i / max(count($customerSparkline) - 1, 1)) * 48;
                                $y = 15 - (($val / $max) * 13);
                                $points[] = "{$x},{$y}";
                            }
                        @endphp
                        <polyline fill="none" stroke="var(--color-accent-500)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" points="{{ implode(' ', $points) }}" />
                    </svg>
                    @endif
                </div>
            </div>

            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2.5" style="color: var(--color-text-secondary);">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-accent-500);"></span>
                    <span>Active Products</span>
                </div>
                <span class="font-bold font-mono" style="color: var(--color-text-primary);">{{ number_format($activeProducts) }}</span>
            </div>

            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2.5" style="color: var(--color-text-secondary);">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-success-600);"></span>
                    <span>Month Revenue</span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="font-bold font-mono" style="color: var(--color-text-primary);">{{ format_money($monthRevenue) }}</span>
                    @if(!empty($revenueSparkline))
                    <svg class="op-sparkline" width="48" height="16" viewBox="0 0 48 16">
                        @php
                            $max = max(array_merge($revenueSparkline, [1]));
                            $points = [];
                            foreach ($revenueSparkline as $i => $val) {
                                $x = ($i / max(count($revenueSparkline) - 1, 1)) * 48;
                                $y = 15 - (($val / $max) * 13);
                                $points[] = "{$x},{$y}";
                            }
                        @endphp
                        <polyline fill="none" stroke="var(--color-success-500)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" points="{{ implode(' ', $points) }}" />
                    </svg>
                    @endif
                </div>
            </div>

            <div class="flex justify-between items-center text-sm">
                <div class="flex items-center gap-2.5" style="color: var(--color-text-secondary);">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: var(--color-danger-600);"></span>
                    <span>Low Stock Items</span>
                </div>
                <span class="font-bold font-mono @if($lowStock > 0) op-badge-pulse @endif" style="color: {{ $lowStock > 0 ? 'var(--color-danger-600)' : 'var(--color-text-primary)' }};">{{ number_format($lowStock) }}</span>
            </div>

            <div class="pt-3" style="border-top: 1px solid var(--color-border-subtle); margin-top: auto;">
                <div class="flex justify-between items-center text-xs mb-1.5">
                    <span style="color: var(--color-text-muted);">Order Completion Rate</span>
                    <span class="font-bold font-mono" style="color: var(--color-text-primary);">{{ $completionRate }}%</span>
                </div>
                <div class="w-full rounded-full h-2 overflow-hidden" style="background: var(--color-bg-inset);">
                    <div class="h-2 rounded-full transition-all duration-500" style="width: {{ $completionRate }}%; background: var(--color-success-500);"></div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
