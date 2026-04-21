{{-- Section: hero
     content: headline(ml), subheadline(ml), placeholder(ml), button_text(ml), popular_oem[]
--}}
@php
    $lang = app()->getLocale();
    $placeholder = 'Enter OEM number, e.g. 1K0407271F';
    $searchHintId = 'search-hint';
@endphp
<section class="relative bg-navy text-white py-28 md:py-40 px-4 overflow-hidden"
         x-data="{ mouseX: 50, mouseY: 50 }"
         @mousemove="mouseX = ($event.clientX / window.innerWidth) * 100; mouseY = ($event.clientY / window.innerHeight) * 100">

    {{-- Animated gradient mesh background --}}
    <div class="absolute inset-0 opacity-20" aria-hidden="true">
        <div class="absolute top-0 -left-4 w-72 h-72 bg-amber rounded-full mix-blend-multiply filter blur-3xl animate-blob"></div>
        <div class="absolute top-0 -right-4 w-72 h-72 bg-blue-500 rounded-full mix-blend-multiply filter blur-3xl animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-purple-500 rounded-full mix-blend-multiply filter blur-3xl animate-blob animation-delay-4000"></div>
    </div>

    {{-- Cursor-following spotlight effect --}}
    <div class="absolute inset-0 opacity-30 pointer-events-none" aria-hidden="true"
         :style="`background: radial-gradient(600px circle at ${mouseX}% ${mouseY}%, rgba(245,158,11,0.15), transparent 40%)`">
    </div>

    {{-- Floating particles background --}}
    <div class="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute w-1.5 h-1.5 bg-amber/20 rounded-full animate-float" style="left: 10%; animation-delay: 0s; animation-duration: 8s;"></div>
        <div class="absolute w-1 h-1 bg-amber/15 rounded-full animate-float" style="left: 25%; animation-delay: 2s; animation-duration: 10s;"></div>
        <div class="absolute w-2 h-2 bg-amber/10 rounded-full animate-float" style="left: 40%; animation-delay: 4s; animation-duration: 12s;"></div>
        <div class="absolute w-1 h-1 bg-amber/20 rounded-full animate-float" style="left: 55%; animation-delay: 1s; animation-duration: 9s;"></div>
        <div class="absolute w-1.5 h-1.5 bg-amber/15 rounded-full animate-float" style="left: 70%; animation-delay: 3s; animation-duration: 11s;"></div>
        <div class="absolute w-1 h-1 bg-amber/10 rounded-full animate-float" style="left: 85%; animation-delay: 5s; animation-duration: 7s;"></div>
        <div class="absolute w-2 h-2 bg-amber/20 rounded-full animate-float" style="left: 15%; animation-delay: 6s; animation-duration: 13s;"></div>
        <div class="absolute w-1 h-1 bg-amber/15 rounded-full animate-float" style="left: 60%; animation-delay: 7s; animation-duration: 8s;"></div>
        <div class="absolute w-1.5 h-1.5 bg-amber/10 rounded-full animate-float" style="left: 35%; animation-delay: 2s; animation-duration: 10s;"></div>
        <div class="absolute w-1 h-1 bg-amber/20 rounded-full animate-float" style="left: 90%; animation-delay: 4s; animation-duration: 9s;"></div>
    </div>

    {{-- Subtle dot-grid texture overlay --}}
    <div class="absolute inset-0 bg-[radial-gradient(circle,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:24px_24px]" aria-hidden="true"></div>

    <div class="relative max-w-5xl mx-auto text-center z-10"
         x-data="{ heroLoaded: false }"
         x-init="
             await new Promise(resolve => setTimeout(resolve, 50));
             heroLoaded = true;
         "
         :class="heroLoaded ? '' : 'opacity-0'">

        {{-- Animated trust badge with pulse --}}
        <div
            class="inline-flex items-center gap-2 bg-amber/25 text-amber rounded-full px-6 py-2.5 text-sm font-semibold mb-10
                   border border-amber/30 backdrop-blur-sm
                   transform transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:transform-none
                   opacity-0 -translate-y-4
                   hover:bg-amber/30 hover:scale-105 transition-all duration-300"
            :class="heroLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 -translate-y-4'"
        >
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber"></span>
            </span>
            <x-heroicon-s-check-badge class="w-4 h-4 shrink-0" aria-hidden="true" />
            <span class="font-semibold">1M+ Genuine OEM Parts Verified</span>
        </div>

        {{-- Heading with gradient text --}}
        <h1
            class="font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold mb-6 leading-tight
                   transform transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:transform-none
                   opacity-0 translate-y-4"
            :class="heroLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-white via-white to-amber/90">
                {{ trans_field($section->content['headline'] ?? null) }}
            </span>
        </h1>

        @if(!empty($section->content['subheadline']))
        <p
            class="text-xl md:text-2xl text-white/70 mb-12 max-w-3xl mx-auto
                   transform transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:transform-none
                   opacity-0 translate-y-4"
            :class="heroLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
            {{ trans_field($section->content['subheadline']) }}
        </p>
        @endif

        {{-- Enhanced Glassmorphism OEM Search Form with 3D Tilt & Typing Placeholder --}}
        <div
            x-data="{
                q: '',
                placeholder: '',
                placeholders: ['1K0407271F', '3C0615301B', 'WHT005549A', '06H103405A', '5Q0412331B'],
                current: 0, charIndex: 0, isDeleting: false,
                type() {
                    const currentText = this.placeholders[this.current];
                    if (!this.isDeleting) {
                        this.placeholder = currentText.substring(0, this.charIndex++);
                        if (this.charIndex > currentText.length) {
                            this.isDeleting = true;
                            setTimeout(() => this.type(), 2000);
                            return;
                        }
                    } else {
                        this.placeholder = currentText.substring(0, this.charIndex--);
                        if (this.charIndex < 0) {
                            this.isDeleting = false;
                            this.current = (this.current + 1) % this.placeholders.length;
                        }
                    }
                    setTimeout(() => this.type(), this.isDeleting ? 50 : 100);
                },
            }"
            x-init="type()"
            class="relative max-w-3xl mx-auto
                   transform transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:transform-none
                   opacity-0 scale-95"
            :class="heroLoaded ? 'opacity-100 scale-100' : 'opacity-0 scale-95'"
        >
            {{-- Glowing border pulse effect --}}
            <div class="absolute -inset-1 bg-gradient-to-r from-amber via-orange-500 to-amber rounded-[2.5rem] opacity-0 blur-lg transition-opacity duration-500 animate-pulse group-hover:opacity-60"></div>

            <form
                @submit.prevent="
                    const oem = q.trim().replace(/[^a-zA-Z0-9]/g, '').toUpperCase();
                    if (oem.length >= {{ settings('search.min_chars', 3) }}) window.location.href = '/{{ $lang }}/parts/' + oem;
                "
                class="relative flex rounded-[2.5rem]
                       bg-white/15 backdrop-blur-xl
                       shadow-2xl shadow-navy/60
                       overflow-hidden
                       border border-white/30
                       hover:bg-white/20 hover:border-white/50 hover:shadow-amber/30
                       group"
            >
                {{-- Honeypot --}}
                <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                <div class="flex-1 flex items-center px-6 sm:px-8 min-w-0">
                    <x-heroicon-o-magnifying-glass class="w-7 h-7 text-white/70 shrink-0 mr-3 sm:mr-4" aria-hidden="true" />
                    <input
                        type="text"
                        x-model="q"
                        @focus="placeholder = ''"
                        @blur="if(!q) type()"
                        id="hero-oem-search"
                        :placeholder="placeholder || 'Enter OEM number...'"
                        aria-label="Search by OEM part number"
                        aria-describedby="{{ $searchHintId }}"
                        autocomplete="off"
                        autocapitalize="characters"
                        inputmode="text"
                        class="flex-1 py-6 sm:py-7 text-white font-mono text-lg sm:text-xl
                               uppercase placeholder:normal-case placeholder:font-sans placeholder:text-white/50 placeholder:font-medium
                               bg-transparent border-0 focus:outline-none focus:ring-0
                               min-w-0"
                    >
                </div>
                <button
                    type="submit"
                    class="group/btn relative px-10 sm:px-14 py-6 sm:py-7 shrink-0
                           bg-gradient-to-r from-amber via-amber/95 to-amber
                           bg-[length:200%_100%]
                           text-navy font-black text-base sm:text-lg tracking-wide
                           transition-transform duration-200 ease-out
                           hover:bg-right
                           hover:shadow-[0_0_50px_rgba(245,158,11,0.6)]
                           focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-white/50
                           overflow-hidden"
                >
                    {{-- Shimmer effect overlay --}}
                    <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent
                                 translate-x-[-100%] group-hover/btn:translate-x-[100%] transition-transform duration-700"></span>

                    <span class="relative flex items-center gap-2 sm:gap-3">
                        <span class="hidden sm:inline">{{ trans_field($section->content['button_text'] ?? null) ?: 'SEARCH' }}</span>
                        <span class="sm:hidden">SEARCH</span>
                        <x-heroicon-o-arrow-right class="w-6 h-6 sm:w-7 sm:h-7
                                 transform group-hover/btn:translate-x-2
                                 transition-all duration-300" aria-hidden="true" />
                    </span>
                </button>
            </form>
        </div>

        {{-- Search hint (screen reader only) --}}
        <p id="{{ $searchHintId }}" class="sr-only">Type at least 3 characters to see suggestions. Press Enter to search or select a suggestion.</p>

        {{-- Popular OEM numbers --}}
        @php
            $popularOems = $section->content['popular_oem'] ?? ['1K0698151E', '3C0615301B', 'WHT005549A'];
        @endphp
        @if(!empty($popularOems))
        <div
            class="mt-10
                   transform transition-all duration-700 ease-out motion-reduce:transition-none motion-reduce:transform-none
                   opacity-0 translate-y-4"
            :class="heroLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
        >
            <p class="text-sm text-white/40 mb-4 font-medium">
                <span class="font-sans">Popular searches:</span>
            </p>
            <div class="flex flex-wrap items-center justify-center gap-2">
                @foreach($popularOems as $oem)
                    <a href="/{{ $lang }}/parts/{{ $oem }}"
                       class="group inline-flex items-center gap-2 px-4 py-2
                              bg-white/5 hover:bg-white/10
                              border border-white/10 hover:border-amber/50
                              rounded-xl backdrop-blur-sm
                              transform transition-all duration-300
                              hover:-translate-y-1 hover:shadow-lg hover:shadow-amber/10"
                    >
                        <x-heroicon-o-cube class="w-3.5 h-3.5 text-white/50 group-hover:text-amber transition-colors" aria-hidden="true" />
                        <span class="font-mono text-xs sm:text-sm text-white/70 group-hover:text-white transition-colors">{{ $oem }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- Wave Divider — Navy to Light --}}
    <div class="absolute bottom-0 left-0 right-0">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto" preserveAspectRatio="none">
            <path d="M0 60L48 55C96 50 192 40 288 45C384 50 480 70 576 75C672 80 768 70 864 60C960 50 1056 40 1152 42C1248 44 1344 58 1392 65L1440 72V120H1392C1344 120 1248 120 1152 120C1056 120 960 120 864 120C768 120 672 120 576 120C480 120 384 120 288 120C192 120 96 120 48 120H0V60Z" fill="#FAFAF8"/>
        </svg>
    </div>
</section>
