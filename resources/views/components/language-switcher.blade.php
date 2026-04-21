@props(['align' => 'right', 'theme' => 'light'])

{{--
    ═══════════════════════════════════════════════════════════════════
    INDUSTRIAL BLUEPRINT — Language Switcher
    ═══════════════════════════════════════════════════════════════════
    Usage:
        <x-language-switcher />
        <x-language-switcher theme="dark" />
--}}

@php
    $languages = [
        'en' => ['name' => 'English',     'fi' => 'gb', 'native' => 'English'],
        'de' => ['name' => 'German',      'fi' => 'de', 'native' => 'Deutsch'],
        'lt' => ['name' => 'Lithuanian',  'fi' => 'lt', 'native' => 'Lietuvių'],
        'fr' => ['name' => 'French',      'fi' => 'fr', 'native' => 'Français'],
        'es' => ['name' => 'Spanish',     'fi' => 'es', 'native' => 'Español'],
    ];

    $currentLocale = app()->getLocale();
    $currentLanguage = $languages[$currentLocale] ?? $languages['en'];

    $availableLanguages = collect($languages)->map(function($data, $code) {
        return array_merge(['code' => $code], $data);
    })->values()->toArray();

    $getLanguageUrl = function($newLocale) {
        $current = request()->route();
        if (!$current || !$current->getName()) {
            return "/{$newLocale}/";
        }
        $params = request()->route()->parameters();
        $params['lang'] = $newLocale;
        $query = request()->query();
        unset($query['lang']);
        try {
            $url = route($current->getName(), $params);
            return $url . (empty($query) ? '' : '?' . http_build_query($query));
        } catch (\Exception $e) {
            $path = request()->path();
            $newPath = preg_replace('#^(en|de|lt|fr|es)(/|$)#', $newLocale . '$2', $path);
            return '/' . $newPath;
        }
    };

    $isDark = $theme === 'dark';
    $alignPosition = $align === 'right' ? 'right-0' : 'left-0';
@endphp

<div class="relative" x-data="{ open: false }" @click.away="open = false">
    {{-- ── Trigger ── --}}
    <button
        @click="open = !open"
        type="button"
        class="group inline-flex items-center gap-2.5 h-10 px-3 border
               font-mono text-[11px] font-bold tracking-[0.18em] uppercase transition-colors
               {{ $isDark
                    ? 'border-white/20 text-ivory/80 hover:text-amber hover:border-amber/60'
                    : 'border-rule text-ink hover:border-ink hover:bg-ivory-alt' }}
               focus-visible:outline-none focus-visible:border-amber"
        aria-haspopup="true"
        :aria-expanded="open"
    >
        <img src="{{ asset('flags/' . $currentLanguage['fi'] . '.svg') }}"
             alt="{{ $currentLanguage['name'] }}"
             class="w-5 h-[14px] object-cover border {{ $isDark ? 'border-white/20' : 'border-rule' }}">
        <span class="tabular-nums">{{ strtoupper($currentLocale) }}</span>
        <svg class="w-3 h-3 transition-transform duration-200" :class="{ 'rotate-180': open }"
             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="square" stroke-linejoin="miter" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- ── Dropdown ── --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="absolute {{ $alignPosition }} mt-2 w-56 z-50
               {{ $isDark ? 'border border-amber/40 bg-ink text-ivory' : 'border border-ink bg-paper text-ink' }}
               shadow-[6px_6px_0_0_rgba(11,26,41,0.12)]"
        style="display: none;"
        role="menu"
    >
        {{-- Header tab --}}
        <div class="flex items-center justify-between px-4 py-2.5 border-b
                    {{ $isDark ? 'border-white/15 bg-ink' : 'border-ink bg-ivory-alt' }}">
            <span class="font-mono text-[10px] font-bold tracking-[0.24em] uppercase
                         {{ $isDark ? 'text-amber' : 'text-amber-ink' }}">§ Locale</span>
            <span class="font-mono text-[10px] tracking-[0.22em] uppercase
                         {{ $isDark ? 'text-ivory/50' : 'text-ink-muted' }}">5 options</span>
        </div>

        {{-- Ledger of languages --}}
        <ul class="divide-y {{ $isDark ? 'divide-white/10' : 'divide-rule' }}">
            @foreach($availableLanguages as $lang)
                @php
                    $active = $currentLocale === $lang['code'];
                    $num = str_pad((string)($loop->iteration), 2, '0', STR_PAD_LEFT);
                @endphp
                <li>
                    <a
                        href="{{ $getLanguageUrl($lang['code']) }}"
                        role="menuitem"
                        class="group flex items-center gap-3 px-4 py-3 transition-colors
                               @if($active)
                                   {{ $isDark ? 'bg-amber text-ink' : 'bg-ink text-ivory' }}
                               @else
                                   {{ $isDark ? 'hover:bg-white/5' : 'hover:bg-ivory-alt' }}
                               @endif"
                    >
                        <span class="font-mono text-[10px] font-bold tabular-nums tracking-[0.14em]
                                     @if($active)
                                         {{ $isDark ? 'text-ink/70' : 'text-amber' }}
                                     @else
                                         {{ $isDark ? 'text-ivory/40' : 'text-ink-muted' }}
                                     @endif">
                            {{ $num }}
                        </span>
                        <img src="{{ asset('flags/' . $lang['fi'] . '.svg') }}"
                             alt="{{ $lang['name'] }}"
                             class="w-5 h-[14px] object-cover border
                                    @if($active)
                                        {{ $isDark ? 'border-ink/30' : 'border-ivory/30' }}
                                    @else
                                        {{ $isDark ? 'border-white/20' : 'border-rule' }}
                                    @endif">
                        <div class="flex-1 min-w-0">
                            <p class="font-sans text-[13px] font-bold tracking-tight leading-tight
                                      @if(!$active) {{ $isDark ? 'text-ivory' : 'text-ink' }} @endif">
                                {{ $lang['native'] }}
                            </p>
                            <p class="font-mono text-[9px] tracking-[0.2em] uppercase mt-0.5
                                      @if($active)
                                          {{ $isDark ? 'text-ink/60' : 'text-ivory/60' }}
                                      @else
                                          {{ $isDark ? 'text-ivory/40' : 'text-ink-muted' }}
                                      @endif">
                                {{ strtoupper($lang['code']) }}
                            </p>
                        </div>
                        @if($active)
                            <x-heroicon-s-check class="w-4 h-4 {{ $isDark ? 'text-ink' : 'text-amber' }}" />
                        @else
                            <span class="font-mono text-[10px] tracking-[0.18em] opacity-0 group-hover:opacity-100 transition-opacity
                                         {{ $isDark ? 'text-amber' : 'text-amber-ink' }}">→</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
</div>
