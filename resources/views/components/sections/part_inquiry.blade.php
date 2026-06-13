{{-- Section: part_inquiry (Industrial Blueprint)
     content: eyebrow(ml), headline(ml), subheadline(ml), button_text(ml)
     Inline quick-inquiry form posting to /api/inquiry.
--}}
@php
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $buttonText = trans_field($section->content['button_text'] ?? null) ?: 'Submit Inquiry';
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-ink text-ivory border-b border-rule-dark overflow-hidden">
    <div class="absolute inset-0 bg-grid-navy bg-grid-lg opacity-60 pointer-events-none" aria-hidden="true"></div>

    {{-- Amber tick strip --}}
    <div class="relative h-[3px] bg-amber"></div>

    <div class="relative max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        {{-- Grid: headline left, form right --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10">

            {{-- Left column: intro --}}
            <div class="col-span-12 lg:col-span-5">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="font-mono text-[10px] tracking-[0.28em] uppercase text-amber">{{ $eyebrow }}</span>
                </div>
                @endif

                @if($headline)
                <h2 class="font-display font-extrabold text-ivory leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[16ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif

                @if($subheadline)
                <p class="mt-8 text-base text-ivory/75 leading-relaxed max-w-md">
                    {{ $subheadline }}
                </p>
                @endif

                {{-- Spec panel --}}
                <dl class="mt-10 border-t border-white/15">
                    @foreach([
                        ['SLA · Response', '24 h'],
                        ['Channel', 'Email · Secure'],
                        ['Coverage', '27 EU Countries'],
                    ] as $row)
                    <div class="flex items-baseline justify-between gap-4 py-3 border-b border-white/15">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ivory/55 shrink-0">
                            {{ $row[0] }}
                        </dt>
                        <span class="flex-1 border-b border-dotted border-white/25 translate-y-[-4px]"></span>
                        <dd class="font-mono text-sm font-semibold tabular-nums text-ivory shrink-0">{{ $row[1] }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>

            {{-- Right column: form --}}
            <div class="col-span-12 lg:col-span-7"
                 x-data="{
                    oem: '',
                    email: '',
                    vehicle_make: '',
                    vehicle_model: '',
                    vehicle_year: '',
                    notes: '',
                    state: 'idle',
                    error: '',
                    async submit() {
                        if (!this.oem || !this.email || this.state === 'loading') return;
                        this.state = 'loading';
                        this.error = '';
                        try {
                            const res = await fetch('{{ route('api.inquiry.store') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    oem_number: this.oem,
                                    email: this.email,
                                    vehicle_make: this.vehicle_make,
                                    vehicle_model: this.vehicle_model,
                                    vehicle_year: this.vehicle_year,
                                    notes: this.notes,
                                    lang: '{{ app()->getLocale() }}'
                                }),
                            });
                            const json = await res.json();
                            if (json.success) {
                                this.state = 'success';
                            } else {
                                this.state = 'error';
                                this.error = json.message || 'Something went wrong.';
                            }
                        } catch (e) {
                            this.state = 'error';
                            this.error = 'Network error. Please try again.';
                        }
                    },
                    reset() {
                        this.oem = '';
                        this.email = '';
                        this.vehicle_make = '';
                        this.vehicle_model = '';
                        this.vehicle_year = '';
                        this.notes = '';
                        this.state = 'idle';
                        this.error = '';
                    }
                 }">

                {{-- Form card on paper background --}}
                <div class="relative bg-paper text-ink border border-amber/50">
                    {{-- Corner register marks --}}
                    <span class="absolute -top-px -left-px w-3 h-3 border-l-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -top-px -right-px w-3 h-3 border-r-2 border-t-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-px -left-px w-3 h-3 border-l-2 border-b-2 border-amber" aria-hidden="true"></span>
                    <span class="absolute -bottom-px -right-px w-3 h-3 border-r-2 border-b-2 border-amber" aria-hidden="true"></span>

                    {{-- Form header bar --}}
                    <div class="flex items-center justify-between px-6 py-3 border-b border-rule bg-ivory-alt">
                        <span class="font-mono text-[10px] tracking-[0.22em] uppercase font-bold text-ink">
                            Form · Quick Inquiry
                        </span>
                        <span class="bp-spec-mono">
                            REV · {{ now()->format('Y.m') }}
                        </span>
                    </div>

                    {{-- Idle / Error state: form --}}
                    <div x-show="state !== 'success'" class="p-6 sm:p-8">
                        <form @submit.prevent="submit" class="space-y-6" novalidate>
                            {{-- Honeypot --}}
                            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                            {{-- OEM Part Number --}}
                            <div>
                                <label for="inquiry-oem" class="bp-spec mb-2 inline-block">
                                    {{ __('OEM Part Number') }} <span class="text-red-600">*</span>
                                </label>
                                <input type="text"
                                       x-model="oem"
                                       id="inquiry-oem"
                                       inputmode="text"
                                       autocapitalize="characters"
                                       placeholder="e.g. 1K0407271F"
                                       class="bp-input-mono"
                                       required
                                       :disabled="state === 'loading'"
                                       :aria-invalid="state === 'error'"
                                       aria-describedby="inquiry-oem-error">
                                <p x-show="state === 'error' && !oem" id="inquiry-oem-error" role="alert"
                                   class="mt-2 font-mono text-[11px] uppercase tracking-wider text-red-600">
                                    {{ __('OEM part number is required.') }}
                                </p>
                            </div>

                            {{-- Email --}}
                            <div>
                                <label for="inquiry-email" class="bp-spec mb-2 inline-block">
                                    {{ __('Email address') }} <span class="text-red-600">*</span>
                                </label>
                                <input type="email"
                                       inputmode="email"
                                       x-model="email"
                                       id="inquiry-email"
                                       placeholder="you@company.com"
                                       class="bp-input"
                                       required
                                       :disabled="state === 'loading'"
                                       :aria-invalid="state === 'error'"
                                       aria-describedby="inquiry-email-error">
                                <p x-show="state === 'error' && !email" id="inquiry-email-error" role="alert"
                                   class="mt-2 font-mono text-[11px] uppercase tracking-wider text-red-600">
                                    {{ __('Email address is required.') }}
                                </p>
                            </div>

                            {{-- Optional vehicle details --}}
                            <div x-data="{ expanded: false }" class="border-t border-rule pt-5">
                                <button type="button"
                                        @click="expanded = !expanded"
                                        :aria-expanded="expanded"
                                        aria-controls="vehicle-details-panel"
                                        class="flex items-center gap-2 font-mono text-[11px] font-bold uppercase tracking-[0.22em] text-ink hover:text-amber-ink transition-colors">
                                    <x-heroicon-o-plus-small class="w-4 h-4" x-show="!expanded" />
                                    <x-heroicon-o-minus-small class="w-4 h-4" x-show="expanded" x-cloak />
                                    <span x-text="expanded ? 'Hide Vehicle Details' : 'Add Vehicle Details (optional)'"></span>
                                </button>

                                <div id="vehicle-details-panel" x-show="expanded" x-collapse x-cloak class="mt-5 space-y-4">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label for="vehicle-make" class="bp-spec mb-2 inline-block">Make</label>
                                            <input type="text" x-model="vehicle_make" id="vehicle-make"
                                                   placeholder="VW" class="bp-input" :disabled="state === 'loading'">
                                        </div>
                                        <div>
                                            <label for="vehicle-model" class="bp-spec mb-2 inline-block">Model</label>
                                            <input type="text" x-model="vehicle_model" id="vehicle-model"
                                                   placeholder="Golf" class="bp-input" :disabled="state === 'loading'">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="vehicle-year" class="bp-spec mb-2 inline-block">Year</label>
                                        <input type="text" x-model="vehicle_year" id="vehicle-year"
                                               placeholder="2015" class="bp-input-mono" :disabled="state === 'loading'">
                                    </div>
                                    <div>
                                        <label for="inquiry-notes" class="bp-spec mb-2 inline-block">Notes</label>
                                        <textarea x-model="notes" id="inquiry-notes" rows="2"
                                                  placeholder="Any additional notes..."
                                                  class="bp-input resize-none" :disabled="state === 'loading'"></textarea>
                                    </div>
                                </div>
                            </div>

                            {{-- Error banner --}}
                            <div x-show="state === 'error'" x-cloak
                                 class="flex items-center gap-3 px-4 py-3 border border-red-600 bg-red-50
                                        font-mono text-[11px] uppercase tracking-wider text-red-700">
                                <x-heroicon-s-exclamation-triangle class="w-4 h-4 shrink-0" />
                                <span x-text="error"></span>
                            </div>

                            {{-- Submit --}}
                            <button type="submit"
                                    :disabled="state === 'loading'"
                                    class="bp-btn-primary w-full justify-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="state !== 'loading'" class="inline-flex items-center gap-2">
                                    <x-heroicon-s-paper-airplane class="w-5 h-5" />
                                    {{ $buttonText }}
                                </span>
                                <span x-show="state === 'loading'" x-cloak class="inline-flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Transmitting...') }}
                                </span>
                            </button>

                            <p class="flex items-center gap-2 bp-spec-mono">
                                <x-heroicon-s-lock-closed class="w-3 h-3 text-amber-ink" />
                                {{ __('Secure · TLS 1.3 · Response within 24 h') }}
                            </p>
                        </form>
                    </div>

                    {{-- Success state --}}
                    <div x-show="state === 'success'" x-cloak class="p-10 text-center">
                        <div class="inline-flex items-center justify-center w-12 h-12 border-2 border-amber mb-6">
                            <x-heroicon-s-check class="w-6 h-6 text-amber" />
                        </div>
                        <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink mb-3">
                            {{ __('Status · Received') }}
                        </p>
                        <h3 class="font-display text-3xl font-extrabold text-ink mb-3 tracking-tight">
                            {{ __('Inquiry logged') }}<span class="text-amber">.</span>
                        </h3>
                        <p class="text-body max-w-sm mx-auto mb-8">
                            {{ __('We will review your request and respond within 24 hours.') }}
                        </p>
                        <button @click="reset()"
                                class="inline-flex items-center gap-2 bp-spec text-ink hover:text-amber-ink transition-colors">
                            <x-heroicon-o-arrow-path class="w-4 h-4" />
                            {{ __('Submit another inquiry') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
