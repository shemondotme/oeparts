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


    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left 2 Columns: Checkout Funnel Chart --}}
        <div class="lg:col-span-2">
            <x-filament::section icon="heroicon-o-funnel" icon-color="primary">
                <x-slot name="heading">
                    <span class="font-display font-semibold text-gray-900 dark:text-white">Checkout Funnel Overview</span>
                </x-slot>

                <div class="space-y-6 py-4">
                    @foreach($this->getCheckoutSteps() as $index => $step)
                        <div class="space-y-2">
                            <div class="flex justify-between items-baseline text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-primary-50 dark:bg-primary-950 text-[10px] font-bold font-mono text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-800">
                                        0{{ $index + 1 }}
                                    </span>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $step['step'] }}</span>
                                </div>
                                <div class="flex items-baseline gap-2">
                                    <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $step['count'] }}</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">sessions</span>
                                </div>
                            </div>
                            <div class="relative w-full bg-gray-100 dark:bg-gray-800/80 rounded-lg h-9 overflow-hidden border border-gray-200/40 dark:border-gray-700/40">
                                <div
                                    class="h-full bg-gradient-to-r from-primary-500 to-primary-600 dark:from-primary-600 dark:to-primary-700 rounded-lg flex items-center justify-end pr-3 transition-all duration-500 shadow-inner"
                                    style="width: {{ max($step['percent'], 4) }}%"
                                >
                                    @if($step['percent'] > 12)
                                        <span class="text-white text-[11px] font-bold font-mono tracking-tight drop-shadow-sm">
                                            {{ $step['percent'] }}%
                                        </span>
                                    @endif
                                </div>
                                @if($step['percent'] <= 12)
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-600 dark:text-gray-400 text-[11px] font-bold font-mono">
                                        {{ $step['percent'] }}%
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        </div>

        {{-- Right 1 Column: Key Metrics / Drop-off Rate --}}
        <div class="space-y-6">
            <x-filament::section class="border-s-4 border-danger-500 dark:border-danger-600 bg-gradient-to-b from-white to-gray-50/50 dark:from-gray-900 dark:to-gray-900/50 shadow-md">
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-danger-500 dark:text-danger-400" />
                        <span class="font-display font-semibold text-gray-900 dark:text-white">Overall Drop-off</span>
                    </div>
                </x-slot>

                <div class="py-2 text-center lg:text-left">
                    <div class="flex items-baseline justify-center lg:justify-start gap-1">
                        <span class="text-6xl font-black font-display text-danger-600 dark:text-danger-500 tracking-tight">
                            {{ $this->getDropoffRate() }}
                        </span>
                        <span class="text-2xl font-bold text-danger-600 dark:text-danger-500">%</span>
                    </div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-300 mt-3 leading-relaxed">
                        of potential buyers who initiated checkout dropped off before completing payment.
                    </p>
                    <div class="mt-6 pt-4 border-t border-gray-100 dark:border-gray-800 text-left">
                        <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider block font-mono">Industry Benchmarks:</span>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-success-500"></span>
                            <span class="text-xs text-gray-600 dark:text-gray-400"><strong class="text-gray-800 dark:text-gray-200">&lt; 30%</strong> - Excellent</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-warning-500"></span>
                            <span class="text-xs text-gray-600 dark:text-gray-400"><strong class="text-gray-800 dark:text-gray-200">30% - 50%</strong> - Normal</span>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-2.5 h-2.5 rounded-full bg-danger-500"></span>
                            <span class="text-xs text-gray-600 dark:text-gray-400"><strong class="text-gray-800 dark:text-gray-200">&gt; 50%</strong> - Action Needed</span>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    <span class="font-display font-semibold text-gray-900 dark:text-white">Optimization Tips</span>
                </x-slot>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-3 leading-relaxed">
                    <div class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
                        <span>Simplify checkout steps and remove non-essential fields to reduce user friction.</span>
                    </div>
                    <div class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
                        <span>Ensure shipping zone rules offer transparent, flat pricing to avoid cart shock.</span>
                    </div>
                    <div class="flex gap-2">
                        <x-heroicon-o-check-circle class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
                        <span>Verify payment credentials for Airwallex in setting options to avoid integration errors.</span>
                    </div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
