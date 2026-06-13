<x-filament-panels::page>
    {{-- Header filter controls --}}
    <div class="flex justify-end mb-6">
        <div class="flex items-center gap-2 p-1 rounded-xl" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle); box-shadow: var(--shadow-1);">
            @foreach(['7' => '7d', '30' => '30d', '90' => '90d'] as $value => $label)
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 Columns: Checkout Funnel --}}
        <div class="lg:col-span-2 space-y-6">
            <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="flex items-center gap-2 mb-6">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-brand-50); color: var(--color-brand-600);">
                        <x-heroicon-o-funnel class="w-5 h-5" />
                    </div>
                    <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">Checkout Funnel Visualizer</h3>
                </div>

                @php
                    $steps = $this->getCheckoutSteps();
                    $started = $steps[0]['count'] ?? 0;
                    $completed = $steps[1]['count'] ?? 0;
                    $paid = $steps[2]['count'] ?? 0;
                    $cancelled = $steps[3]['count'] ?? 0;
                    $abandoned = $steps[4]['count'] ?? 0;
                    $abandonedPercent = $started > 0 ? round(($abandoned / $started) * 100, 1) : 0;
                    $unpaid = $completed - $paid;
                    $unpaidPercent = $completed > 0 ? round(($unpaid / $completed) * 100, 1) : 0;
                @endphp

                {{-- Funnel Pipeline --}}
                <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-4 md:gap-2 py-4">
                    <!-- Step 1: Started -->
                    <div class="flex-1 w-full flex flex-col items-center rounded-xl p-5 relative overflow-hidden" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <div class="absolute inset-x-0 top-0 h-1" style="background: var(--color-brand-500);"></div>
                        <span class="text-[10px] font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted);">01. Initiation</span>
                        <span class="text-sm font-semibold" style="color: var(--color-text-secondary);">Started Checkout</span>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="text-2xl font-black font-mono" style="color: var(--color-text-primary);">{{ number_format($started) }}</span>
                            <span class="text-xs font-mono" style="color: var(--color-text-muted);">sessions</span>
                        </div>
                        <span class="text-xs font-mono font-bold mt-2 px-2.5 py-0.5 rounded-full" style="background: var(--color-brand-50); color: var(--color-brand-600); border: 1px solid var(--color-brand-100);">100%</span>
                    </div>

                    <!-- Drop-off 1 -->
                    <div class="flex flex-col items-center justify-center py-2 px-1 relative min-w-[70px] text-center">
                        <div class="hidden md:block w-12 h-0.5 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" style="background: var(--color-border-default);"></div>
                        <div class="relative z-10 rounded-full px-2.5 py-1 flex flex-col items-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-danger-200); box-shadow: var(--shadow-1);">
                            <div class="flex items-center gap-0.5 text-[10px] font-mono font-black" style="color: var(--color-danger-600);">
                                <x-heroicon-s-arrow-down class="w-2.5 h-2.5" />
                                <span>{{ $abandonedPercent }}%</span>
                            </div>
                            <span class="text-[8px] uppercase font-mono tracking-tight" style="color: var(--color-text-muted);">Abandoned</span>
                        </div>
                    </div>

                    <!-- Step 2: Completed -->
                    <div class="flex-1 w-full flex flex-col items-center rounded-xl p-5 relative overflow-hidden" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <div class="absolute inset-x-0 top-0 h-1" style="background: var(--color-accent-500);"></div>
                        <span class="text-[10px] font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted);">02. Submission</span>
                        <span class="text-sm font-semibold" style="color: var(--color-text-secondary);">Completed Order</span>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="text-2xl font-black font-mono" style="color: var(--color-text-primary);">{{ number_format($completed) }}</span>
                            <span class="text-xs font-mono" style="color: var(--color-text-muted);">orders</span>
                        </div>
                        @php $compRate = $started > 0 ? round(($completed / $started) * 100, 1) : 0; @endphp
                        <span class="text-xs font-mono font-bold mt-2 px-2.5 py-0.5 rounded-full" style="background: var(--color-accent-50); color: var(--color-accent-600); border: 1px solid var(--color-accent-100);">{{ $compRate }}%</span>
                    </div>

                    <!-- Drop-off 2 -->
                    <div class="flex flex-col items-center justify-center py-2 px-1 relative min-w-[70px] text-center">
                        <div class="hidden md:block w-12 h-0.5 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2" style="background: var(--color-border-default);"></div>
                        <div class="relative z-10 rounded-full px-2.5 py-1 flex flex-col items-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-danger-200); box-shadow: var(--shadow-1);">
                            <div class="flex items-center gap-0.5 text-[10px] font-mono font-black" style="color: var(--color-danger-600);">
                                <x-heroicon-s-arrow-down class="w-2.5 h-2.5" />
                                <span>{{ $unpaidPercent }}%</span>
                            </div>
                            <span class="text-[8px] uppercase font-mono tracking-tight" style="color: var(--color-text-muted);">Unpaid</span>
                        </div>
                    </div>

                    <!-- Step 3: Paid -->
                    <div class="flex-1 w-full flex flex-col items-center rounded-xl p-5 relative overflow-hidden" style="background: var(--color-bg-inset); border: 1px solid var(--color-border-subtle);">
                        <div class="absolute inset-x-0 top-0 h-1" style="background: var(--color-success-500);"></div>
                        <span class="text-[10px] font-bold uppercase tracking-widest font-mono mb-1" style="color: var(--color-text-muted);">03. Conversion</span>
                        <span class="text-sm font-semibold" style="color: var(--color-text-secondary);">Paid & Settled</span>
                        <div class="flex items-baseline gap-1.5 mt-3">
                            <span class="text-2xl font-black font-mono" style="color: var(--color-text-primary);">{{ number_format($paid) }}</span>
                            <span class="text-xs font-mono" style="color: var(--color-text-muted);">payments</span>
                        </div>
                        @php $paidRate = $started > 0 ? round(($paid / $started) * 100, 1) : 0; @endphp
                        <span class="text-xs font-mono font-bold mt-2 px-2.5 py-0.5 rounded-full" style="background: var(--color-success-50); color: var(--color-success-600); border: 1px solid var(--color-success-100);">{{ $paidRate }}%</span>
                    </div>
                </div>
            </div>

            {{-- Leakage breakdown cards --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Abandoned Carts</span>
                        <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background: var(--color-danger-50); color: var(--color-danger-600); border: 1px solid var(--color-danger-100);">{{ $abandonedPercent }}% leakage</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-xl font-black font-mono" style="color: var(--color-text-primary);">{{ number_format($abandoned) }}</span>
                        <span class="text-xs font-mono" style="color: var(--color-text-muted);">carts left incomplete</span>
                    </div>
                    <p class="text-xs mt-2.5 leading-relaxed" style="color: var(--color-text-muted);">
                        Users who began checkout but left before placing the order. Consider email retargeting.
                    </p>
                </div>

                <div class="op-card p-5" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                    @php $cancelPercent = $started > 0 ? round(($cancelled / $started) * 100, 1) : 0; @endphp
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">Cancelled Orders</span>
                        <span class="text-xs font-mono font-bold px-2 py-0.5 rounded" style="background: var(--color-danger-50); color: var(--color-danger-600); border: 1px solid var(--color-danger-100);">{{ $cancelPercent }}% cancellation</span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-xl font-black font-mono" style="color: var(--color-text-primary);">{{ number_format($cancelled) }}</span>
                        <span class="text-xs font-mono" style="color: var(--color-text-muted);">orders cancelled</span>
                    </div>
                    <p class="text-xs mt-2.5 leading-relaxed" style="color: var(--color-text-muted);">
                        Orders finalized at checkout but cancelled before shipping.
                    </p>
                </div>
            </div>
        </div>

        {{-- Right 1 Column: Drop-off Rate --}}
        <div class="space-y-6">
            @php
                $dropoff = (float) $this->getDropoffRate();
                if ($dropoff < 30.0) {
                    $borderColor = 'var(--color-success-500)';
                    $textColor = 'var(--color-success-600)';
                } elseif ($dropoff <= 50.0) {
                    $borderColor = 'var(--color-warning-500)';
                    $textColor = 'var(--color-warning-600)';
                } else {
                    $borderColor = 'var(--color-danger-500)';
                    $textColor = 'var(--color-danger-600)';
                }
            @endphp

            <div class="op-card p-6 relative overflow-hidden" style="border-left: 4px solid {{ $borderColor }}; background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <div class="flex items-center gap-2 mb-4">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5" style="color: {{ $textColor }};" />
                    <span class="text-sm font-semibold" style="color: var(--color-text-primary);">Overall Drop-off</span>
                </div>

                <div class="py-2 text-center lg:text-left">
                    <div class="flex items-baseline justify-center lg:justify-start gap-1">
                        <span class="text-6xl font-black font-mono tracking-tight" style="color: {{ $textColor }};">{{ $this->getDropoffRate() }}</span>
                        <span class="text-2xl font-bold" style="color: {{ $textColor }};">%</span>
                    </div>
                    <p class="text-xs font-medium mt-3 leading-relaxed" style="color: var(--color-text-secondary);">
                        of potential buyers who initiated checkout dropped off before completing payment.
                    </p>
                    <div class="mt-6 pt-4 text-left" style="border-top: 1px solid var(--color-border-subtle);">
                        <span class="text-[10px] font-semibold uppercase tracking-widest block font-mono" style="color: var(--color-text-muted);">Industry Benchmarks:</span>
                        <div class="flex items-center gap-2 mt-3">
                            <span class="w-2 h-2 rounded-full" style="background: var(--color-success-500);"></span>
                            <span class="text-xs" style="color: var(--color-text-secondary);"><strong class="font-mono" style="color: var(--color-text-primary);">&lt; 30%</strong> - Excellent</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="w-2 h-2 rounded-full" style="background: var(--color-warning-500);"></span>
                            <span class="text-xs" style="color: var(--color-text-secondary);"><strong class="font-mono" style="color: var(--color-text-primary);">30% - 50%</strong> - Normal</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1.5">
                            <span class="w-2 h-2 rounded-full" style="background: var(--color-danger-500);"></span>
                            <span class="text-xs" style="color: var(--color-text-secondary);"><strong class="font-mono" style="color: var(--color-text-primary);">&gt; 50%</strong> - Action Needed</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="op-card p-6" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
                <h4 class="text-sm font-semibold mb-4" style="color: var(--color-text-primary);">Optimization Tips</h4>
                <div class="text-xs space-y-3.5 leading-relaxed" style="color: var(--color-text-secondary);">
                    <div class="flex gap-2.5">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--color-brand-500);" />
                        <span>Simplify checkout steps and remove non-essential fields.</span>
                    </div>
                    <div class="flex gap-2.5">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--color-brand-500);" />
                        <span>Ensure shipping zones offer transparent, flat pricing.</span>
                    </div>
                    <div class="flex gap-2.5">
                        <x-heroicon-o-check-circle class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--color-brand-500);" />
                        <span>Verify Airwallex payment credentials in settings.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
