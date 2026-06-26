@include('filament.components.admin-styles')
<x-filament-panels::page>
    <div class="space-y-6" x-data="{ search: '' }">
        {{-- Settings Header --}}
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="font-family: var(--font-display); color: var(--color-text-on-accent);">
                        <x-heroicon-o-adjustments-horizontal class="w-5 h-5" style="color: var(--color-warning-500);" />
                        System Settings Engine
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Configure your B2B/B2C auto parts platform. Settings apply in real-time across storefront routing, pricing, and API integrations.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                    ACTIVE
                </div>
            </div>
        </div>

        @include('filament.components.cluster-search', ['placeholder' => 'Search settings... (e.g. SMTP, payment, cache)'])

        @php
            $sections = \App\Filament\Support\SettingsRegistry::sections();
        @endphp

        @foreach ($sections as $heading => $sectionData)
            <div
                x-show="search === '' || '{{ strtolower($heading) . ' ' . $sectionData['keywords'] }}'.includes(search.toLowerCase()) || {{ collect($sectionData['items'])->map(fn($i) => strtolower($i[0] . ' ' . $i[2]))->implode(' ') }}.includes(search.toLowerCase())"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="op-card overflow-hidden"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);"
            >
                {{-- Group Header --}}
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                        @svg($sectionData['icon'], 'w-4 h-4')
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                        {{ $heading }}
                    </h3>
                </div>
                {{-- Group Content --}}
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($sectionData['items'] as $item)
                            <a href="{{ url($item[1]) }}"
                               x-show="search === '' || '{{ strtolower($item[0] . ' ' . $item[2]) }}'.includes(search.toLowerCase())"
                               class="settings-link op-focus-ring op-press flex items-start gap-4 p-4 rounded-md transition-all duration-200 no-underline group"
                               style="border: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-all duration-200 group-hover:scale-110"
                                    style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default);">
                                    @svg($item[3], 'w-5 h-5 transition-colors duration-200', ['style' => 'color: var(--color-warning-600);'])
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold transition-colors duration-200" style="color: var(--color-text-primary); font-family: var(--font-display);">
                                        {{ $item[0] }}
                                    </div>
                                    <div class="mt-1 text-xs leading-normal" style="color: var(--color-text-muted);">
                                        {{ $item[2] }}
                                    </div>
                                </div>
                                <div class="flex items-center self-center opacity-0 group-hover:opacity-100 translate-x-[-4px] group-hover:translate-x-0 transition-all duration-300">
                                    <x-heroicon-o-arrow-right class="w-4 h-4" style="color: var(--color-accent-500);" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        {{-- No Results --}}
        @php
            $allItemKeywords = collect($sections)->flatMap(function ($section) {
                return collect($section['items'])->map(fn($item) => strtolower($item[0] . ' ' . $item[2]));
            })->values()->implode(' ');
            $sectionKeywords = collect($sections)->map(fn($s, $k) => strtolower($k . ' ' . $s['keywords']))->values()->implode(' ');
        @endphp
        <div x-show="search.length > 0 && !'{{ $sectionKeywords }}'.includes(search.toLowerCase()) && !'{{ $allItemKeywords }}'.includes(search.toLowerCase())"
            class="op-card p-8 text-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No settings match your search.</p>
        </div>
    </div>
</x-filament-panels::page>
