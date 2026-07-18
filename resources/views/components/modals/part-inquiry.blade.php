@props(['normalizedQuery' => '', 'failedSearchLogId' => null])

@php
    $lang = app()->getLocale();
    $inquiryHours = (int) settings('part_inquiry.response_hours', 24);
@endphp

{{-- Part Inquiry Modal — Industrial Blueprint --}}
<div
    x-data="{
        open: false,
        state: 'idle',
        step: 1,
        successMsg: '',
        errGeneric: @js(__('part_inquiry.error_generic')),
        errNetwork: @js(__('part_inquiry.error_network')),
        validationFallback: @js(__('part_inquiry.validation_fallback')),
        successFallback: @js(__('part_inquiry.success_fallback', ['hours' => $inquiryHours])),
        expandedVehicle: false,
        expandedMore: false,
        failedSearchLogId: @json($failedSearchLogId ? (int) $failedSearchLogId : null),
        form: {
            email: '',
            phone: '',
            oem_number: '{{ $normalizedQuery }}',
            manufacturer: '',
            car_model: '',
            year: '',
            vin_number: '',
            quantity: 1,
            urgency: 'normal',
            notes: '',
            website: ''
        },
        errors: {},
        get notesLength() {
            return this.form.notes.length;
        },
        get vinProgress() {
            return this.form.vin_number.length;
        },
        get hasVehicleOrNotes() {
            return this.form.manufacturer || this.form.car_model || this.form.year || this.form.vin_number || this.form.notes || this.form.quantity > 1 || this.form.urgency !== 'normal';
        },
        get emailValid() {
            return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.form.email);
        },
        clearError(field) {
            if (this.errors[field]) {
                delete this.errors[field];
                this.errors = { ...this.errors };
            }
        },
        resetForm() {
            this.step = 1;
            this.state = 'idle';
            this.errors = {};
            this.expandedVehicle = false;
            this.expandedMore = false;
            this.form.email = '';
            this.form.phone = '';
            this.form.oem_number = '{{ $normalizedQuery }}';
            this.form.manufacturer = '';
            this.form.car_model = '';
            this.form.year = '';
            this.form.vin_number = '';
            this.form.quantity = 1;
            this.form.urgency = 'normal';
            this.form.notes = '';
            this.form.website = '';
        },
        expandDetails() {
            this.step = 2;
            this.expandedVehicle = true;
            this.expandedMore = true;
            this.$nextTick(() => { if ($refs.scrollArea) $refs.scrollArea.scrollTop = 0 });
        },
        collapseDetails() {
            this.step = 1;
            this.$nextTick(() => { if ($refs.scrollArea) $refs.scrollArea.scrollTop = 0 });
        },
        async submit() {
            if (this.state === 'loading') return;
            this.state = 'loading';
            this.errors = {};
            try {
                const honeypotData = {};
                document.querySelectorAll('[name^=my_name], [name=valid_from]').forEach(el => {
                    honeypotData[el.name] = el.value;
                });
                const res = await fetch('{{ route('frontend.inquiry.store', ['lang' => $lang]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ ...this.form, ...honeypotData, failed_search_log_id: this.failedSearchLogId })
                });
                const data = await res.json();
                if (data.success) {
                    this.state = 'success';
                    this.successMsg = data.message;
                    this.$nextTick(() => this.launchConfetti());
                } else if (res.status === 422 && data.errors) {
                    this.errors = data.errors;
                    this.state = 'idle';
                    if (Object.keys(data.errors).some(k => ['manufacturer','car_model','year','vin_number','quantity','urgency','notes'].includes(k))) {
                        this.step = 2;
                    }
                } else {
                    this.errors = { email: [data.message || this.errGeneric] };
                    this.state = 'idle';
                }
            } catch(e) {
                this.errors = { email: [this.errNetwork] };
                this.state = 'idle';
            }
        },
        launchConfetti() {
            if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const colors = ['#F59E0B', '#0B1A29', '#B8862B', '#F97316', '#FFFFFF'];
            const container = this.$refs.confettiContainer;
            if (!container) return;
            for (let i = 0; i < 50; i++) {
                const particle = document.createElement('div');
                particle.className = 'confetti-particle';
                particle.style.left = Math.random() * 100 + 'vw';
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                particle.style.animationDuration = (Math.random() * 2 + 2) + 's';
                particle.style.animationDelay = Math.random() * 0.5 + 's';
                particle.style.width = (Math.random() * 6 + 4) + 'px';
                particle.style.height = (Math.random() * 6 + 4) + 'px';
                particle.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
                container.appendChild(particle);
                setTimeout(() => particle.remove(), 4000);
            }
        },
        formatOEM(input) {
            if (input == null || input === '') return '';
            return String(input).toUpperCase().replace(/[^A-Z0-9\-]/g, '');
        }
    }"
    @open-inquiry-modal.window="resetForm(); form.oem_number = $event.detail?.oem ?? '{{ $normalizedQuery }}'; open = true; $nextTick(() => { if ($refs.scrollArea) $refs.scrollArea.scrollTop = 0; $refs.focusOem?.focus({ preventScroll: true }) })"
    @keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    {{-- z-70, not z-50: must always render above the cookie-consent corner
         banner (z-40, see its own comment) and the header (z-50) — a real
         content modal should never be covered by a transient overlay. --}}
    class="fixed inset-0 z-70 flex items-end sm:items-center justify-center p-3 sm:p-4 motion-reduce:transition-none"
>
    {{-- Confetti container --}}
    <div x-ref="confettiContainer" class="pointer-events-none fixed inset-0 z-71"></div>

    {{-- Backdrop — ink wash with grid texture --}}
    <div class="absolute inset-0 bg-ink/85 bg-grid-navy bg-grid-md motion-reduce:transition-none"
         @click="open = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel — flat document with offset amber shadow --}}
    <div class="relative w-full max-w-xl bg-paper border border-ink flex flex-col max-h-[92vh] sm:max-h-[90vh]
                bp-shadow-lg motion-reduce:transition-none" style="--bp-shadow-color: rgba(245,158,11,1);"
         role="dialog"
         aria-modal="true"
         aria-labelledby="part-inquiry-modal-title"
         x-trap.noscroll="open"
         x-transition:enter="transition ease-out duration-300 motion-reduce:duration-0"
         x-transition:enter-start="opacity-0 translate-y-8 sm:scale-95 motion-reduce:translate-y-0 motion-reduce:scale-100"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200 motion-reduce:duration-0"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:scale-95 motion-reduce:translate-y-0"
    >
        {{-- Amber tick strip --}}
        <div class="h-1 bg-amber shrink-0" aria-hidden="true"></div>

        {{-- ═══════════ INK DOCUMENT HEADER ═══════════ --}}
        <div class="relative shrink-0 bg-ink text-ivory px-5 sm:px-7 py-5 sm:py-6 border-b border-ink">
            {{-- Subtle grid texture --}}
            <div class="pointer-events-none absolute inset-0 bg-grid-navy bg-grid-md opacity-50"></div>

            {{-- Corner register marks (amber) --}}
            <span class="pointer-events-none absolute top-2 left-2 w-3 h-3 border-l border-t border-amber" aria-hidden="true"></span>
            <span class="pointer-events-none absolute top-2 right-2 w-3 h-3 border-r border-t border-amber" aria-hidden="true"></span>

            <div class="relative z-10 flex items-start justify-between gap-4">
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    {{-- Doc mark: squared icon tile --}}
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center border border-amber/60 bg-ink">
                        <x-heroicon-o-paper-airplane class="h-6 w-6 text-amber" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 id="part-inquiry-modal-title" class="font-display text-lg sm:text-xl font-black leading-[1.05] tracking-tight text-ivory">
                            {{ __('part_inquiry.heading') }}<span class="text-amber">.</span>
                        </h2>
                        <p class="mt-1.5 text-xs text-ivory/70 leading-relaxed">
                            {{ __('part_inquiry.header_reply', ['hours' => $inquiryHours]) }}
                        </p>
                    </div>
                </div>
                <button type="button"
                        @click="open = false"
                        class="shrink-0 w-9 h-9 border border-ivory/20 text-ivory/70 hover:text-ink hover:bg-amber hover:border-amber
                               flex items-center justify-center transition-colors
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-2 focus-visible:ring-offset-ink"
                        aria-label="{{ __('part_inquiry.close') }}">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            {{-- Ledger step indicator --}}
            <div class="relative z-10 mt-5 flex items-center gap-3" x-show="state !== 'success'">
                {{-- Step 01 --}}
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="flex h-7 w-7 items-center justify-center text-[11px] font-mono font-bold border transition-all"
                          :class="step === 1
                              ? 'bg-amber text-ink border-amber'
                              : (step > 1 ? 'bg-ink text-amber border-amber' : 'bg-ink text-ivory/50 border-ivory/25')">
                        <template x-if="step > 1">
                            <x-heroicon-s-check class="h-3.5 w-3.5" />
                        </template>
                        <span x-show="step === 1">01</span>
                        <span x-show="step < 1">01</span>
                    </span>
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase transition-colors"
                          :class="step === 1 ? 'text-ivory' : 'text-ivory/50'">{{ __('part_inquiry.step1') }}</span>
                </div>

                {{-- Connector: dotted leader --}}
                <div class="flex-1 h-px bg-ivory/20 relative overflow-hidden">
                    <div class="absolute inset-y-0 left-0 bg-amber transition-all duration-500"
                         :class="step === 1 ? 'w-0' : 'w-full'"></div>
                </div>

                {{-- Step 02 --}}
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase transition-colors"
                          :class="step === 2 ? 'text-ivory' : 'text-ivory/50'">{{ __('part_inquiry.step2') }}</span>
                    <span class="flex h-7 w-7 items-center justify-center text-[11px] font-mono font-bold border transition-all"
                          :class="step === 2
                              ? 'bg-amber text-ink border-amber'
                              : 'bg-ink text-ivory/50 border-ivory/25'">02</span>
                </div>
            </div>
        </div>

        {{-- ═══════════ SUCCESS STATE ═══════════ --}}
        <div x-show="state === 'success'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="overflow-y-auto px-5 py-10 text-center sm:px-8 bg-paper relative">

            {{-- Corner marks --}}
            <span class="pointer-events-none absolute top-3 left-3 w-3 h-3 border-l border-t border-amber" aria-hidden="true"></span>
            <span class="pointer-events-none absolute top-3 right-3 w-3 h-3 border-r border-t border-amber" aria-hidden="true"></span>
            <span class="pointer-events-none absolute bottom-3 left-3 w-3 h-3 border-l border-b border-amber" aria-hidden="true"></span>
            <span class="pointer-events-none absolute bottom-3 right-3 w-3 h-3 border-r border-b border-amber" aria-hidden="true"></span>

            {{-- Success stamp --}}
            <div class="mx-auto mb-6 w-20 h-20 border-2 border-ink flex items-center justify-center bg-ivory-alt">
                <x-heroicon-s-check class="h-10 w-10 text-ink" />
            </div>

            {{-- Doc tag --}}
            <div class="flex items-center justify-center gap-2 mb-4">
                <span class="inline-block w-6 h-[2px] bg-amber"></span>
                <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber-ink">
                    STATUS · TRANSMITTED
                </span>
                <span class="inline-block w-6 h-[2px] bg-amber"></span>
            </div>

            <h3 class="font-display text-2xl sm:text-3xl font-black text-ink leading-tight tracking-tight">
                {{ __('part_inquiry.success_title') }}<span class="text-amber">.</span>
            </h3>
            <p class="mx-auto mt-3 max-w-md text-sm text-body leading-relaxed" x-text="successMsg || successFallback"></p>

            {{-- Response time ledger --}}
            <div class="mx-auto mt-6 max-w-md border border-ink bg-ivory-alt">
                <div class="flex items-center justify-between px-4 py-2.5 border-b border-rule">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                        {{ __('part_inquiry.success_expected_label') }}
                    </span>
                    <x-heroicon-o-clock class="h-4 w-4 text-amber-ink" />
                </div>
                <div class="px-4 py-4">
                    <p class="font-display text-xl font-black text-ink leading-none">
                        {{ $inquiryHours }}<span class="text-amber">h</span>
                    </p>
                    <p class="mt-1 text-xs text-body">
                        {{ __('part_inquiry.success_expected', ['hours' => $inquiryHours]) }}
                    </p>
                </div>
            </div>

            {{-- Confirmation checklist --}}
            <div class="mx-auto mt-6 max-w-sm text-left border border-rule bg-paper">
                <div class="px-4 py-2 border-b border-rule bg-ivory-alt">
                    <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink-muted">
                        {{ __('part_inquiry.next_protocol') }}
                    </span>
                </div>
                <ol class="divide-y divide-rule">
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="font-mono text-[10px] font-bold text-amber-ink w-6 shrink-0">01</span>
                        <x-heroicon-s-check class="h-4 w-4 text-ink shrink-0" />
                        <span class="text-xs text-body">{{ __('part_inquiry.success_check_email') }}</span>
                    </li>
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="font-mono text-[10px] font-bold text-amber-ink w-6 shrink-0">02</span>
                        <x-heroicon-s-check class="h-4 w-4 text-ink shrink-0" />
                        <span class="text-xs text-body">{{ __('part_inquiry.success_check_team') }}</span>
                    </li>
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="font-mono text-[10px] font-bold text-amber-ink w-6 shrink-0">03</span>
                        <x-heroicon-s-check class="h-4 w-4 text-ink shrink-0" />
                        <span class="text-xs text-body">{{ __('part_inquiry.success_check_queue') }}</span>
                    </li>
                </ol>
            </div>

            <button type="button"
                    @click="open = false"
                    class="bp-btn-primary mt-8 w-full max-w-xs mx-auto inline-flex items-center justify-center gap-2 py-3.5 text-sm font-bold">
                <span>{{ __('part_inquiry.close') }}</span>
                <x-heroicon-s-arrow-long-right class="h-4 w-4" />
            </button>
        </div>

        {{-- ═══════════ FORM ═══════════ --}}
        <form x-show="state !== 'success'" @submit.prevent="submit" class="flex min-h-0 flex-1 flex-col">
            <input type="text" name="website" x-model="form.website" class="hidden" tabindex="-1" autocomplete="off">
            @honeypot

            <div x-ref="scrollArea" class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-paper">
                <div class="relative px-5 py-6 sm:px-7 sm:py-7">

                    <div class="relative z-10 space-y-5">
                        {{-- ═════════════════════════════════
                             STEP 1 — Part + Contact
                             ═════════════════════════════════ --}}
                        <div x-show="step === 1"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-4">

                            {{-- Block card --}}
                            <div class="relative border border-ink bg-paper">
                                {{-- Corner register marks --}}
                                <span class="pointer-events-none absolute top-1.5 left-1.5 w-2.5 h-2.5 border-l border-t border-rule-strong" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute top-1.5 right-1.5 w-2.5 h-2.5 border-r border-t border-rule-strong" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute bottom-1.5 left-1.5 w-2.5 h-2.5 border-l border-b border-rule-strong" aria-hidden="true"></span>
                                <span class="pointer-events-none absolute bottom-1.5 right-1.5 w-2.5 h-2.5 border-r border-b border-rule-strong" aria-hidden="true"></span>

                                {{-- Section header strip --}}
                                <div class="flex items-center gap-3 px-5 sm:px-6 py-4 border-b border-ink bg-ivory-alt">
                                    <span class="flex h-9 w-9 items-center justify-center border border-ink bg-paper text-ink">
                                        <x-heroicon-o-identification class="h-5 w-5" />
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-amber-ink">01</span>
                                            <p class="text-sm font-bold text-ink leading-tight">{{ __('part_inquiry.section_part_title') }}</p>
                                        </div>
                                        <p class="text-xs text-body mt-0.5">{{ __('part_inquiry.section_part_subtitle') }}</p>
                                    </div>
                                </div>

                                <div class="p-5 sm:p-6 space-y-4">
                                    {{-- OEM Number --}}
                                    <div>
                                        <label for="inquiry-oem" class="flex items-center justify-between mb-1.5">
                                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_oem') }} <span class="text-amber-ink">*</span>
                                            </span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">REQUIRED</span>
                                        </label>
                                        <div class="relative">
                                            <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10">
                                                <x-heroicon-o-cube class="h-4 w-4 text-ink-muted" />
                                            </div>
                                            <input type="text"
                                                   x-ref="focusOem"
                                                   x-model="form.oem_number"
                                                   @input="form.oem_number = formatOEM(form.oem_number); clearError('oem_number')"
                                                   id="inquiry-oem"
                                                   inputmode="text"
                                                   autocapitalize="characters"
                                                   placeholder="e.g. 06K103495BK"
                                                   class="bp-input w-full pl-9 pr-10 py-3 text-sm font-mono font-bold uppercase text-ink placeholder:normal-case placeholder:font-sans placeholder:font-normal placeholder:text-ink-muted/60"
                                                   :class="errors.oem_number ? '!border-red-500 !bg-red-50/40' : ''">
                                            <div x-show="form.oem_number.length >= 3 && !errors.oem_number"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-50"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                                <x-heroicon-s-check class="h-4 w-4 text-amber-ink" />
                                            </div>
                                        </div>
                                        <p x-show="errors.oem_number" x-text="errors.oem_number?.[0]" class="mt-1.5 font-mono text-[10px] font-bold tracking-[0.15em] uppercase text-red-600"></p>
                                    </div>

                                    {{-- Email --}}
                                    <div>
                                        <label for="inquiry-email" class="flex items-center justify-between mb-1.5">
                                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_email') }} <span class="text-amber-ink">*</span>
                                            </span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">REQUIRED</span>
                                        </label>
                                        <div class="relative">
                                            <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10">
                                                <x-heroicon-o-at-symbol class="h-4 w-4 text-ink-muted" />
                                            </div>
                                            <input type="email"
                                                   x-model="form.email"
                                                   @input="clearError('email')"
                                                   id="inquiry-email"
                                                   inputmode="email"
                                                   placeholder="you@company.com"
                                                   class="bp-input w-full pl-9 pr-10 py-3 text-sm text-ink placeholder:text-ink-muted/60"
                                                   :class="errors.email ? '!border-red-500 !bg-red-50/40' : ''">
                                            <div x-show="emailValid && !errors.email"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-50"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2">
                                                <x-heroicon-s-check class="h-4 w-4 text-amber-ink" />
                                            </div>
                                        </div>
                                        <p x-show="errors.email" x-text="errors.email?.[0]" class="mt-1.5 font-mono text-[10px] font-bold tracking-[0.15em] uppercase text-red-600"></p>
                                    </div>

                                    {{-- Phone --}}
                                    <div>
                                        <label for="inquiry-phone" class="flex items-center justify-between mb-1.5">
                                            <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_phone') }}
                                            </span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">{{ __('part_inquiry.optional') }}</span>
                                        </label>
                                        <div class="relative">
                                            <div class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 z-10">
                                                <x-heroicon-o-phone class="h-4 w-4 text-ink-muted" />
                                            </div>
                                            <input type="tel"
                                                   x-model="form.phone"
                                                   id="inquiry-phone"
                                                   inputmode="tel"
                                                   placeholder="+1 555 0100"
                                                   class="bp-input w-full pl-9 pr-3 py-3 text-sm text-ink placeholder:text-ink-muted/60">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Expand to step 2 --}}
                            <button type="button"
                                    @click="expandDetails()"
                                    class="mt-3 group flex w-full items-center justify-between gap-3 px-5 py-4 border border-dashed border-rule-strong bg-paper
                                           text-left hover:border-amber hover:bg-amber/5 transition-colors
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="flex h-9 w-9 items-center justify-center border border-ink bg-ivory-alt text-ink group-hover:bg-amber group-hover:border-amber transition-colors">
                                        <x-heroicon-o-plus class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-amber-ink">02 · OPTIONAL</p>
                                        <p class="text-sm font-bold text-ink mt-0.5">{{ __('part_inquiry.button_add_vehicle') }}</p>
                                    </div>
                                </div>
                                <x-heroicon-s-arrow-long-right class="h-5 w-5 text-ink-muted group-hover:text-amber-ink transition-colors shrink-0" />
                            </button>
                        </div>

                        {{-- ═════════════════════════════════
                             STEP 2 — Vehicle Details & Notes
                             ═════════════════════════════════ --}}
                        <div x-show="step === 2"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-4"
                             class="space-y-4">

                            {{-- Vehicle details accordion --}}
                            <div class="border border-ink bg-paper">
                                <button type="button"
                                        @click="expandedVehicle = !expandedVehicle"
                                        class="flex w-full items-center justify-between gap-3 px-5 sm:px-6 py-4 text-left transition-colors hover:bg-ivory-alt">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex h-9 w-9 items-center justify-center border border-ink bg-paper text-ink">
                                            <x-heroicon-o-truck class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-amber-ink">02A</span>
                                                <p class="text-sm font-bold text-ink leading-tight">{{ __('part_inquiry.vehicle_title') }}</p>
                                            </div>
                                            <p class="text-xs text-body mt-0.5">{{ __('part_inquiry.vehicle_subtitle') }}</p>
                                        </div>
                                    </div>
                                    <span class="w-7 h-7 border border-ink flex items-center justify-center text-ink shrink-0 transition-transform"
                                          x-bind:class="expandedVehicle ? 'bg-amber border-amber rotate-180' : 'bg-paper'">
                                        <x-heroicon-s-chevron-down class="h-4 w-4" />
                                    </span>
                                </button>
                                <div x-show="expandedVehicle" x-collapse class="border-t border-ink px-5 sm:px-6 pt-5 pb-5 bg-ivory-alt/40">
                                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                        <div>
                                            <label class="block mb-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_brand') }}
                                            </label>
                                            <input type="text" x-model="form.manufacturer" placeholder="{{ __('part_inquiry.placeholder_brand') }}"
                                                   class="bp-input w-full px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted/60">
                                        </div>
                                        <div>
                                            <label class="block mb-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_model') }}
                                            </label>
                                            <input type="text" x-model="form.car_model" placeholder="{{ __('part_inquiry.placeholder_model') }}"
                                                   class="bp-input w-full px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted/60">
                                        </div>
                                        <div>
                                            <label class="block mb-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_year') }}
                                            </label>
                                            <input type="text" x-model="form.year" inputmode="numeric" placeholder="{{ __('part_inquiry.placeholder_year') }}"
                                                   class="bp-input w-full px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted/60 font-mono">
                                        </div>
                                        <div>
                                            <label class="block mb-1.5 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_vin') }}
                                            </label>
                                            <input type="text" x-model="form.vin_number" inputmode="text" autocapitalize="characters" placeholder="{{ __('part_inquiry.placeholder_vin') }}" maxlength="17"
                                                   class="bp-input w-full px-3 py-2.5 font-mono text-sm uppercase text-ink placeholder:normal-case placeholder:font-sans placeholder:text-ink-muted/60">
                                            {{-- VIN progress: dotted leader --}}
                                            <div x-show="form.vin_number.length > 0"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100"
                                                 class="mt-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex-1 h-1 bg-rule overflow-hidden">
                                                        <div class="h-full transition-all duration-300"
                                                             :class="vinProgress >= 17 ? 'bg-amber' : vinProgress >= 10 ? 'bg-amber-ink' : 'bg-ink-muted'"
                                                             :style="'width: ' + (vinProgress / 17 * 100) + '%'"></div>
                                                    </div>
                                                    <span class="font-mono text-[10px] font-bold tracking-[0.15em]"
                                                          :class="vinProgress >= 17 ? 'text-amber-ink' : 'text-ink-muted'"
                                                          x-text="vinProgress + '/17'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Timing & notes accordion --}}
                            <div class="border border-ink bg-paper">
                                <button type="button"
                                        @click="expandedMore = !expandedMore"
                                        class="flex w-full items-center justify-between gap-3 px-5 sm:px-6 py-4 text-left transition-colors hover:bg-ivory-alt">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="flex h-9 w-9 items-center justify-center border border-ink bg-paper text-ink">
                                            <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-mono text-[9px] font-bold tracking-[0.22em] uppercase text-amber-ink">02B</span>
                                                <p class="text-sm font-bold text-ink leading-tight">{{ __('part_inquiry.timing_title') }}</p>
                                            </div>
                                            <p class="text-xs text-body mt-0.5">{{ __('part_inquiry.timing_subtitle') }}</p>
                                        </div>
                                    </div>
                                    <span class="w-7 h-7 border border-ink flex items-center justify-center text-ink shrink-0 transition-transform"
                                          x-bind:class="expandedMore ? 'bg-amber border-amber rotate-180' : 'bg-paper'">
                                        <x-heroicon-s-chevron-down class="h-4 w-4" />
                                    </span>
                                </button>
                                <div x-show="expandedMore" x-collapse class="border-t border-ink px-5 sm:px-6 pt-5 pb-5 bg-ivory-alt/40">
                                    <div class="space-y-5">
                                        {{-- Urgency --}}
                                        <div>
                                            <label class="block mb-2 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_urgency') }}
                                            </label>
                                            <div class="grid grid-cols-3 gap-0 border border-ink bg-paper">
                                                <label class="group relative cursor-pointer border-r border-ink last:border-r-0">
                                                    <input type="radio" x-model="form.urgency" value="normal" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center py-4 px-2 text-center transition-colors
                                                                peer-checked:bg-ink peer-checked:text-ivory
                                                                peer-focus-visible:ring-2 peer-focus-visible:ring-amber peer-focus-visible:ring-inset
                                                                group-hover:bg-ivory-alt peer-checked:group-hover:bg-ink">
                                                        <x-heroicon-o-check-circle class="h-5 w-5 mb-1.5 transition-colors text-ink peer-checked:group-[]:text-amber" />
                                                        <span class="font-mono text-[10px] font-bold tracking-[0.15em] uppercase">{{ __('part_inquiry.urgency_normal') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight opacity-70">{{ __('part_inquiry.urgency_normal_hint') }}</span>
                                                    </div>
                                                </label>
                                                <label class="group relative cursor-pointer border-r border-ink last:border-r-0">
                                                    <input type="radio" x-model="form.urgency" value="soon" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center py-4 px-2 text-center transition-colors
                                                                peer-checked:bg-ink peer-checked:text-ivory
                                                                peer-focus-visible:ring-2 peer-focus-visible:ring-amber peer-focus-visible:ring-inset
                                                                group-hover:bg-ivory-alt peer-checked:group-hover:bg-ink">
                                                        <x-heroicon-o-clock class="h-5 w-5 mb-1.5 transition-colors text-ink peer-checked:group-[]:text-amber" />
                                                        <span class="font-mono text-[10px] font-bold tracking-[0.15em] uppercase">{{ __('part_inquiry.urgency_soon') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight opacity-70">{{ __('part_inquiry.urgency_soon_hint') }}</span>
                                                    </div>
                                                </label>
                                                <label class="group relative cursor-pointer">
                                                    <input type="radio" x-model="form.urgency" value="urgent" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center py-4 px-2 text-center transition-colors
                                                                peer-checked:bg-amber peer-checked:text-ink
                                                                peer-focus-visible:ring-2 peer-focus-visible:ring-ink peer-focus-visible:ring-inset
                                                                group-hover:bg-ivory-alt peer-checked:group-hover:bg-amber">
                                                        <x-heroicon-o-bolt class="h-5 w-5 mb-1.5 text-ink" />
                                                        <span class="font-mono text-[10px] font-bold tracking-[0.15em] uppercase">{{ __('part_inquiry.urgency_urgent') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight opacity-70">{{ __('part_inquiry.urgency_urgent_hint') }}</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        {{-- Quantity stepper --}}
                                        <div>
                                            <label class="block mb-2 font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                {{ __('part_inquiry.label_quantity') }}
                                            </label>
                                            <div class="inline-flex h-11 items-stretch border border-ink bg-paper">
                                                <button type="button"
                                                        @click="form.quantity = Math.max(1, form.quantity - 1)"
                                                        class="flex w-11 items-center justify-center text-ink hover:bg-ink hover:text-amber transition-colors border-r border-ink
                                                               focus:outline-none focus-visible:bg-amber"
                                                        :class="form.quantity <= 1 ? 'cursor-not-allowed opacity-30' : ''"
                                                        aria-label="Decrease">
                                                    <x-heroicon-o-minus class="h-4 w-4" />
                                                </button>
                                                <input type="text" inputmode="numeric"
                                                       :value="form.quantity"
                                                       @change="form.quantity = Math.max(1, Math.min(99, parseInt($event.target.value) || 1)); $event.target.value = form.quantity"
                                                       class="w-16 bg-ivory-alt text-center font-mono text-base font-bold text-ink focus:outline-none focus:bg-paper focus:ring-2 focus:ring-amber focus:ring-inset">
                                                <button type="button"
                                                        @click="form.quantity = Math.min(99, form.quantity + 1)"
                                                        class="flex w-11 items-center justify-center text-ink hover:bg-ink hover:text-amber transition-colors border-l border-ink
                                                               focus:outline-none focus-visible:bg-amber"
                                                        :class="form.quantity >= 99 ? 'cursor-not-allowed opacity-30' : ''"
                                                        aria-label="Increase">
                                                    <x-heroicon-o-plus class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Notes --}}
                                        <div>
                                            <label class="flex items-center justify-between mb-2">
                                                <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">
                                                    {{ __('part_inquiry.label_notes') }}
                                                </span>
                                                <span class="font-mono text-[10px] font-bold tracking-[0.15em] px-2 py-0.5 border"
                                                      :class="notesLength >= 450 ? 'border-red-500 text-red-600 bg-red-50' : notesLength >= 350 ? 'border-amber text-amber-ink bg-amber/10' : 'border-rule text-ink-muted bg-paper'"
                                                      x-text="notesLength + '/500'"></span>
                                            </label>
                                            <textarea x-model="form.notes" rows="3" maxlength="500" placeholder="{{ __('part_inquiry.notes_placeholder') }}"
                                                      class="bp-input w-full resize-none px-3 py-2.5 text-sm text-ink placeholder:text-ink-muted/60"></textarea>
                                            {{-- Progress bar --}}
                                            <div x-show="notesLength > 0"
                                                 class="mt-1.5 h-px bg-rule overflow-hidden">
                                                <div class="h-full transition-all duration-300"
                                                     :class="notesLength >= 450 ? 'bg-red-500' : notesLength >= 350 ? 'bg-amber' : 'bg-amber-ink'"
                                                     :style="'width: ' + (notesLength / 500 * 100) + '%'"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Back to step 1 --}}
                            <button type="button"
                                    @click="collapseDetails()"
                                    class="group flex w-full items-center justify-center gap-2 px-5 py-3.5 border border-dashed border-rule-strong bg-paper
                                           font-mono text-[11px] font-bold tracking-[0.22em] uppercase text-ink
                                           hover:border-ink hover:bg-ivory-alt transition-colors
                                           focus:outline-none focus-visible:ring-2 focus-visible:ring-amber focus-visible:ring-offset-2">
                                <x-heroicon-s-arrow-long-left class="h-4 w-4 transition-transform group-hover:-translate-x-0.5" />
                                <span>{{ __('part_inquiry.back_step1') }}</span>
                            </button>
                        </div>

                        {{-- Validation errors --}}
                        <div x-show="Object.keys(errors).length > 0 && state !== 'loading'"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             role="alert" aria-live="assertive"
                             class="flex items-start gap-3 px-4 py-3 border border-red-500 bg-red-50">
                            <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-red-600" />
                            <div class="flex-1 min-w-0">
                                <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-red-700 mb-0.5">Error · Validation</p>
                                <p class="text-sm text-red-800" x-text="Object.values(errors)[0]?.[0] ?? validationFallback"></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════ FOOTER ═══════════ --}}
            <div class="shrink-0 border-t border-ink bg-ivory-alt px-5 sm:px-7 py-4">
                {{-- Submit progress bar --}}
                <div x-show="state === 'loading'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="mb-3 h-0.5 overflow-hidden bg-rule">
                    <div class="h-full animate-shimmer shimmer-progress bg-gradient-to-r from-amber via-amber-ink to-amber"></div>
                </div>

                <button type="submit" :disabled="state === 'loading'"
                        class="bp-btn-primary w-full inline-flex items-center justify-center gap-2 py-3.5 text-sm font-bold
                               disabled:cursor-wait disabled:opacity-60 transition-all">
                    <span x-show="state !== 'loading'" class="flex items-center justify-center gap-2">
                        <x-heroicon-o-paper-airplane class="h-4 w-4" />
                        <span>{{ __('part_inquiry.submit') }}</span>
                        <x-heroicon-s-arrow-long-right class="h-4 w-4" />
                    </span>
                    <span x-show="state === 'loading'" class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span>{{ __('part_inquiry.submitting') }}</span>
                    </span>
                </button>

                {{-- Trust ledger --}}
                <div class="mt-3 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 font-mono text-[9px] font-bold tracking-[0.18em] uppercase text-ink-muted">
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-s-clock class="h-3 w-3 text-amber-ink" />
                        {{ __('part_inquiry.trust_response', ['hours' => $inquiryHours]) }}
                    </span>
                    <span class="text-rule-strong">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <x-heroicon-s-check-badge class="h-3 w-3 text-amber-ink" />
                        {{ __('part_inquiry.trust_nospam') }}
                    </span>
                </div>

                {{-- Keyboard hint --}}
                <p class="mt-2 hidden sm:block text-center font-mono text-[9px] tracking-[0.15em] uppercase text-ink-muted/80">
                    {!! __('part_inquiry.keyboard_hint_html', [
                        'kbd' => '<kbd class="inline-block border border-rule-strong bg-paper px-1.5 py-0.5 font-mono text-[10px] font-bold text-ink">'.e(__('part_inquiry.keyboard_key')).'</kbd>',
                    ]) !!}
                </p>
            </div>
        </form>
    </div>
</div>
