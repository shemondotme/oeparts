@props(['align' => 'right', 'theme' => 'light'])

{{--
    Language Switcher Component
    Usage: <x-language-switcher />
           <x-language-switcher theme="dark" />

    Displays current language with flag emoji and allows switching between available languages.
--}}

@php
    // Available languages with flag emojis
    $languages = [
        'en' => ['name' => 'English', 'fi' => 'gb', 'native' => 'English'],
        'de' => ['name' => 'German',  'fi' => 'de', 'native' => 'Deutsch'],
        'lt' => ['name' => 'Lithuanian', 'fi' => 'lt', 'native' => 'Lietuvių'],
        'fr' => ['name' => 'French',  'fi' => 'fr', 'native' => 'Français'],
        'es' => ['name' => 'Spanish', 'fi' => 'es', 'native' => 'Español'],
    ];

    // Get current language info
    $currentLocale = app()->getLocale();
    $currentLanguage = $languages[$currentLocale] ?? $languages['en'];

    // Get all available languages
    $availableLanguages = collect($languages)->map(function($data, $code) {
        return array_merge(['code' => $code], $data);
    })->values()->toArray();

    // Generate URL for language switch — preserves current page
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
            // Fallback: replace lang segment in current path
            $path = request()->path();
            $newPath = preg_replace('#^(en|de|lt|fr|es)(/|$)#', $newLocale . '$2', $path);
            return '/' . $newPath;
        }
    };

    // Theme classes
    $isDark = $theme === 'dark';
    $alignPosition = $align === 'right' ? 'right-0' : 'left-0';
    $buttonClasses = $isDark
        ? 'flex items-center gap-2 px-3 py-2 text-sm text-white/80 hover:text-white hover:bg-white/10 rounded-xl transition-all duration-200'
        : 'flex items-center gap-2 px-3 py-2 text-sm text-body hover:text-navy hover:bg-gray-100 rounded-lg transition-colors';
    $dropdownClasses = $isDark
        ? "absolute {$alignPosition} mt-2 w-48 bg-navy rounded-xl shadow-xl border border-white/10 py-1 z-50"
        : "absolute {$alignPosition} mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50";
    $itemActiveClasses = $isDark
        ? 'bg-gradient-to-r from-amber to-orange-500 text-navy font-bold shadow-lg shadow-amber/20'
        : 'bg-amber/20 text-amber font-semibold';
    $itemBaseClasses = $isDark
        ? 'flex items-center gap-3 px-4 py-2 text-sm hover:bg-white/10 transition-all duration-200 text-white/80'
        : 'flex items-center gap-3 px-4 py-2 text-sm hover:bg-gray-100 transition-colors text-body';
    $checkIconClasses = $isDark
        ? 'w-4 h-4 ml-auto text-navy'
        : 'w-4 h-4 ml-auto text-amber';
@endphp

<div class="relative" x-data="{ open: false }">
    {{-- Current Language Button --}}
    <button
        @click="open = !open"
        @click.away="open = false"
        type="button"
        class="{{ $buttonClasses }}"
        aria-haspopup="true"
        :aria-expanded="open"
    >
        {{-- Flag --}}
        <img src="{{ asset('flags/' . $currentLanguage['fi'] . '.svg') }}"
             alt="{{ $currentLanguage['name'] }}"
             class="w-5 h-3.5 rounded-sm object-cover">

        {{-- Language Code --}}
        <span class="hidden sm:inline font-medium text-xs tracking-wide">{{ strtoupper(app()->getLocale()) }}</span>

        {{-- Dropdown Arrow --}}
        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="{{ $dropdownClasses }}"
        style="display: none;"
    >
        @foreach($availableLanguages as $lang)
            <a
                href="{{ $getLanguageUrl($lang['code']) }}"
                class="{{ app()->getLocale() === $lang['code'] ? $itemBaseClasses . ' ' . $itemActiveClasses : $itemBaseClasses }}"
            >
                <img src="{{ asset('flags/' . $lang['fi'] . '.svg') }}"
                     alt="{{ $lang['name'] }}"
                     class="w-5 h-3.5 rounded-sm object-cover">
                <span class="font-medium">{{ $lang['name'] }}</span>
                @if(app()->getLocale() === $lang['code'])
                    <svg class="{{ $checkIconClasses }}" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                @endif
            </a>
        @endforeach
    </div>
</div>
