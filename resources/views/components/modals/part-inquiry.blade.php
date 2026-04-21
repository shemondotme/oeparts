@props(['normalizedQuery' => ''])

@php
    $lang = app()->getLocale();
    $inquiryHours = (int) settings('part_inquiry.response_hours', 24);
@endphp

{{-- Part Inquiry Modal — Redesigned with floating labels, enhanced UX, confetti --}}
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
                const res = await fetch('{{ route('frontend.inquiry.store', ['lang' => $lang]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (data.success) {
                    this.state = 'success';
                    this.successMsg = data.message;
                    // Trigger confetti
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
            const colors = ['#F59E0B', '#0B3A68', '#10B981', '#F97316', '#3B82F6', '#EF4444'];
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
                particle.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
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
    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-3 sm:p-4 motion-reduce:transition-none"
>
    {{-- Confetti container --}}
    <div x-ref="confettiContainer" class="pointer-events-none fixed inset-0 z-[60]"></div>

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-navy/70 backdrop-blur-sm motion-reduce:transition-none"
         @click="open = false"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel --}}
    <div class="relative w-full max-w-xl bg-white rounded-3xl shadow-2xl shadow-navy/25 border border-gray-100 overflow-hidden flex flex-col max-h-[92vh] sm:max-h-[90vh] motion-reduce:transition-none"
         role="dialog"
         aria-modal="true"
         aria-labelledby="part-inquiry-modal-title"
         x-transition:enter="transition ease-out duration-300 motion-reduce:duration-0"
         x-transition:enter-start="opacity-0 translate-y-8 sm:scale-95 motion-reduce:translate-y-0 motion-reduce:scale-100"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200 motion-reduce:duration-0"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 sm:scale-95 motion-reduce:translate-y-0"
    >
        {{-- Top accent gradient --}}
        <div class="h-1.5 bg-gradient-to-r from-amber via-orange-400 to-amber shrink-0 animate-gradient" aria-hidden="true"></div>

        {{-- Header with geometric pattern --}}
        <div class="relative shrink-0 overflow-hidden bg-gradient-to-br from-navy via-navy to-blue-900 px-5 sm:px-7 py-5 sm:py-6">
            {{-- Geometric grid pattern --}}
            <div class="pointer-events-none absolute inset-0 grid-pattern opacity-40"></div>

            {{-- Soft gradient blobs --}}
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -right-10 -top-14 h-44 w-44 rounded-full bg-amber/20 blur-3xl animate-blob"></div>
                <div class="absolute -left-12 bottom-0 h-36 w-36 rounded-full bg-blue-500/25 blur-3xl animate-blob animation-delay-2000"></div>
                <div class="absolute right-1/3 top-1/2 h-28 w-28 rounded-full bg-orange-400/15 blur-3xl animate-blob animation-delay-4000"></div>
            </div>

            <div class="relative z-10 flex items-start justify-between gap-4">
                <div class="flex min-w-0 flex-1 items-start gap-3 sm:gap-4">
                    {{-- Animated icon badge --}}
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber/30 to-orange-500/25 ring-1 ring-white/25
                                transition-transform duration-300 hover:scale-110 hover:rotate-6">
                        <x-heroicon-o-paper-airplane class="h-6 w-6 text-amber" />
                    </div>
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-2 px-3 py-1 text-[10px] font-bold tracking-widest uppercase rounded-full
                                     bg-gradient-to-r from-amber/20 to-orange-500/15 border border-amber/30 text-amber backdrop-blur-sm">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber animate-pulse"></span>
                            {{ __('part_inquiry.badge_expert') }}
                        </span>
                        <h2 id="part-inquiry-modal-title" class="font-display mt-2.5 text-lg font-black leading-tight text-white sm:text-xl">
                            {{ __('part_inquiry.heading') }}
                        </h2>
                        <p class="mt-1 text-sm text-white/70">
                            {{ __('part_inquiry.header_reply', ['hours' => $inquiryHours]) }}
                        </p>
                    </div>
                </div>
                <button type="button"
                        @click="open = false"
                        class="shrink-0 rounded-xl p-2 text-white/50 transition-all hover:bg-white/10 hover:text-white hover:scale-110 focus:outline-none focus:ring-2 focus:ring-amber/50"
                        aria-label="{{ __('part_inquiry.close') }}">
                    <x-heroicon-o-x-mark class="h-6 w-6" />
                </button>
            </div>

            {{-- Enhanced step indicator with progress line --}}
            <div class="relative z-10 mt-5 flex items-center gap-3" x-show="state !== 'success'">
                {{-- Step 1 --}}
                <div class="flex items-center gap-2.5">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold transition-all duration-300 ring-2"
                          :class="step === 1
                              ? 'bg-amber text-navy ring-amber shadow-lg shadow-amber/30 scale-110'
                              : 'bg-white/20 text-white/60 ring-white/20'">
                        <template x-if="step > 1">
                            <x-heroicon-s-check class="h-3.5 w-3.5" />
                        </template>
                        <span x-show="step === 1">1</span>
                    </span>
                    <span class="text-xs font-semibold transition-colors"
                          :class="step === 1 ? 'text-white' : 'text-white/50'">{{ __('part_inquiry.step1') }}</span>
                </div>

                {{-- Progress line --}}
                <div class="flex-1 h-1.5 rounded-full bg-white/15 overflow-hidden">
                    <div class="h-full rounded-full bg-gradient-to-r from-amber to-orange-400 transition-all duration-500 ease-out"
                         :class="step === 1 ? 'w-0' : 'w-full'"></div>
                </div>

                {{-- Step 2 --}}
                <div class="flex items-center gap-2.5">
                    <span class="text-xs font-semibold transition-colors"
                          :class="step === 2 ? 'text-white' : 'text-white/50'">{{ __('part_inquiry.step2') }}</span>
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-bold transition-all duration-300 ring-2"
                          :class="step === 2
                              ? 'bg-amber text-navy ring-amber shadow-lg shadow-amber/30 scale-110'
                              : 'bg-white/20 text-white/60 ring-white/20'">2</span>
                </div>
            </div>
        </div>

        {{-- Success state with confetti --}}
        <div x-show="state === 'success'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             class="overflow-y-auto px-5 py-10 text-center sm:px-8">
            {{-- Success icon --}}
            <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border-2 border-emerald-200 bg-gradient-to-br from-emerald-50 to-emerald-100/50 shadow-xl shadow-emerald-500/15">
                <x-heroicon-s-check-circle class="h-12 w-12 text-emerald-500 animate-bounce" />
            </div>
            <h3 class="font-display text-2xl font-black text-navy sm:text-3xl">{{ __('part_inquiry.success_title') }}</h3>
            <p class="mx-auto mt-3 max-w-md text-sm text-body" x-text="successMsg || successFallback"></p>

            {{-- Response time badge --}}
            <div class="mx-auto mt-6 max-w-md rounded-2xl border border-amber/25 bg-gradient-to-br from-amber/5 to-orange-50/50 px-5 py-4 shadow-sm">
                <p class="flex items-center justify-center gap-2 text-sm font-bold text-amber-text">
                    <x-heroicon-o-clock class="h-5 w-5 shrink-0 animate-pulse" />
                    {{ __('part_inquiry.success_expected', ['hours' => $inquiryHours]) }}
                </p>
            </div>

            {{-- Confirmation checklist --}}
            <div class="mx-auto mt-6 max-w-sm space-y-2 text-left">
                <div class="flex items-center gap-2 text-xs text-emerald-700">
                    <x-heroicon-s-check-circle class="h-4 w-4" />
                    <span>{{ __('part_inquiry.success_check_email') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-emerald-700">
                    <x-heroicon-s-check-circle class="h-4 w-4" />
                    <span>{{ __('part_inquiry.success_check_team') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-emerald-700">
                    <x-heroicon-s-check-circle class="h-4 w-4" />
                    <span>{{ __('part_inquiry.success_check_queue') }}</span>
                </div>
            </div>

            <button type="button"
                    @click="open = false"
                    class="btn-primary mt-8 w-full max-w-xs py-3.5 text-sm font-bold">
                {{ __('part_inquiry.close') }}
            </button>
        </div>

        {{-- Form --}}
        <div x-show="state !== 'success'" class="flex min-h-0 flex-1 flex-col">
            <input type="text" name="website" x-model="form.website" class="hidden" tabindex="-1" autocomplete="off">

            <div x-ref="scrollArea" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
                <div class="relative bg-gradient-to-b from-bg-page/80 via-white to-amber-50/30 px-5 py-6 sm:px-7 sm:py-7">
                    {{-- Dot pattern overlay --}}
                    <div class="pointer-events-none absolute inset-0 dot-pattern opacity-40"></div>

                    <div class="relative z-10 space-y-5">
                        {{-- ═══════════════════════════════════════════════
                             STEP 1 — Part & Contact (Floating Labels)
                             ═══════════════════════════════════════════════ --}}
                        <div x-show="step === 1"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-4">
                            <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-lg shadow-amber/5 sm:p-6">
                                {{-- Section header with gradient --}}
                                <div class="mb-5 flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-amber/20 to-orange-100 text-amber shadow-sm">
                                        <x-heroicon-o-identification class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-bold text-navy">{{ __('part_inquiry.section_part_title') }}</p>
                                        <p class="text-xs text-muted">{{ __('part_inquiry.section_part_subtitle') }}</p>
                                    </div>
                                </div>

                                <div class="space-y-5">
                                    {{-- OEM Number with floating label --}}
                                    <div>
                                        <div class="relative">
                                            <input type="text"
                                                   x-ref="focusOem"
                                                   x-model="form.oem_number"
                                                   @input="form.oem_number = formatOEM(form.oem_number)"
                                                   id="inquiry-oem"
                                                   inputmode="text"
                                                   autocapitalize="characters"
                                                   placeholder=" "
                                                   class="peer w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 pl-12 pr-4 pt-5 pb-2 text-sm font-mono font-bold uppercase text-navy placeholder:normal-case placeholder:font-sans placeholder:font-normal placeholder:text-transparent focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200"
                                                   :class="errors.oem_number ? '!border-red-400 !ring-4 !ring-red-100 !bg-red-50/30' : ''">
                                            <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 transition-colors duration-200
                                                        peer-focus:text-navy peer-[:not(:placeholder-shown)]:text-navy"
                                                 :class="errors.oem_number ? '!text-red-400' : 'text-gray-400'">
                                                <x-heroicon-o-cube class="h-5 w-5" />
                                            </div>
                                            {{-- Floating label --}}
                                            <label for="inquiry-oem"
                                                   class="pointer-events-none absolute left-12 top-3.5 text-sm text-gray-400 transition-all duration-200
                                                          peer-focus:top-1.5 peer-focus:text-xs peer-focus:text-navy peer-focus:font-semibold
                                                          peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-navy peer-[:not(:placeholder-shown)]:font-semibold">
                                                {{ __('part_inquiry.label_oem') }} <span class="text-red-500">*</span>
                                            </label>
                                            {{-- Valid state indicator --}}
                                            <div x-show="form.oem_number.length >= 3 && !errors.oem_number"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-50"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2">
                                                <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-500" />
                                            </div>
                                        </div>
                                        <p x-show="errors.oem_number" x-text="errors.oem_number?.[0]" class="mt-1.5 text-xs font-medium text-red-600"></p>
                                    </div>

                                    {{-- Email with floating label --}}
                                    <div>
                                        <div class="relative">
                                            <input type="email"
                                                   x-model="form.email"
                                                   id="inquiry-email"
                                                   inputmode="email"
                                                   placeholder=" "
                                                   class="peer w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 pl-12 pr-4 pt-5 pb-2 text-sm text-navy placeholder:text-transparent focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200"
                                                   :class="errors.email ? '!border-red-400 !ring-4 !ring-red-100 !bg-red-50/30' : ''">
                                            <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 transition-colors duration-200
                                                        peer-focus:text-navy peer-[:not(:placeholder-shown)]:text-navy"
                                                 :class="errors.email ? '!text-red-400' : 'text-gray-400'">
                                                <x-heroicon-o-at-symbol class="h-5 w-5" />
                                            </div>
                                            <label for="inquiry-email"
                                                   class="pointer-events-none absolute left-12 top-3.5 text-sm text-gray-400 transition-all duration-200
                                                          peer-focus:top-1.5 peer-focus:text-xs peer-focus:text-navy peer-focus:font-semibold
                                                          peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-navy peer-[:not(:placeholder-shown)]:font-semibold">
                                                {{ __('part_inquiry.label_email') }} <span class="text-red-500">*</span>
                                            </label>
                                            <div x-show="form.email.includes('@') && form.email.includes('.') && !errors.email"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0 scale-50"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 class="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2">
                                                <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-500" />
                                            </div>
                                        </div>
                                        <p x-show="errors.email" x-text="errors.email?.[0]" class="mt-1.5 text-xs font-medium text-red-600"></p>
                                    </div>

                                    {{-- Phone with floating label --}}
                                    <div>
                                        <div class="relative">
                                            <input type="tel"
                                                   x-model="form.phone"
                                                   id="inquiry-phone"
                                                   inputmode="tel"
                                                   placeholder=" "
                                                   class="peer w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 pl-12 pr-4 pt-5 pb-2 text-sm text-navy placeholder:text-transparent focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200">
                                            <div class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 transition-colors duration-200
                                                        peer-focus:text-navy peer-[:not(:placeholder-shown)]:text-navy text-gray-400">
                                                <x-heroicon-o-phone class="h-5 w-5" />
                                            </div>
                                            <label for="inquiry-phone"
                                                   class="pointer-events-none absolute left-12 top-3.5 text-sm text-gray-400 transition-all duration-200
                                                          peer-focus:top-1.5 peer-focus:text-xs peer-focus:text-navy peer-focus:font-semibold
                                                          peer-[:not(:placeholder-shown)]:top-1.5 peer-[:not(:placeholder-shown)]:text-xs peer-[:not(:placeholder-shown)]:text-navy peer-[:not(:placeholder-shown)]:font-semibold">
                                                {{ __('part_inquiry.label_phone') }} <span class="text-xs font-normal text-muted">{{ __('part_inquiry.optional') }}</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Expand to step 2 --}}
                            <button type="button"
                                    @click="expandDetails()"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 bg-white/60 py-4 text-sm font-semibold text-navy
                                           transition-all duration-200 hover:border-amber/50 hover:bg-amber/5 hover:text-amber-text hover:shadow-md focus:outline-none focus:ring-2 focus:ring-amber/30">
                                <x-heroicon-o-plus-circle class="h-5 w-5 text-amber transition-transform duration-200 hover:scale-110" />
                                <span>{{ __('part_inquiry.button_add_vehicle') }}</span>
                                <span class="text-xs text-muted">{{ __('part_inquiry.optional') }}</span>
                            </button>
                        </div>

                        {{-- ═══════════════════════════════════════════════
                             STEP 2 — Vehicle Details & Notes
                             ═══════════════════════════════════════════════ --}}
                        <div x-show="step === 2"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 translate-x-4"
                             x-transition:enter-end="opacity-100 translate-x-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-x-0"
                             x-transition:leave-end="opacity-0 -translate-x-4">

                            {{-- Vehicle details --}}
                            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white/90 shadow-md shadow-blue-500/5">
                                <button type="button"
                                        @click="expandedVehicle = !expandedVehicle"
                                        class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition-all hover:bg-gray-50/80 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500/15 to-blue-600/10 text-blue-700 shadow-sm">
                                            <x-heroicon-o-truck class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-bold text-navy">{{ __('part_inquiry.vehicle_title') }}</p>
                                            <p class="text-xs text-muted">{{ __('part_inquiry.vehicle_subtitle') }}</p>
                                        </div>
                                    </div>
                                    <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-gray-400 transition-all duration-200"
                                        x-bind:class="expandedVehicle ? 'rotate-180 text-amber-text' : ''" />
                                </button>
                                <div x-show="expandedVehicle" x-collapse class="border-t border-gray-100 px-5 pb-5 pt-2 sm:px-6">
                                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                                        <div>
                                            <label class="mb-1.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_brand') }}</label>
                                            <input type="text" x-model="form.manufacturer" placeholder="{{ __('part_inquiry.placeholder_brand') }}"
                                                   class="w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-navy placeholder:text-gray-400 focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200">
                                        </div>
                                        <div>
                                            <label class="mb-1.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_model') }}</label>
                                            <input type="text" x-model="form.car_model" placeholder="{{ __('part_inquiry.placeholder_model') }}"
                                                   class="w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-navy placeholder:text-gray-400 focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200">
                                        </div>
                                        <div>
                                            <label class="mb-1.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_year') }}</label>
                                            <input type="text" x-model="form.year" inputmode="numeric" placeholder="{{ __('part_inquiry.placeholder_year') }}"
                                                   class="w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-navy placeholder:text-gray-400 focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200">
                                        </div>
                                        <div>
                                            <label class="mb-1.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_vin') }}</label>
                                            <input type="text" x-model="form.vin_number" inputmode="text" autocapitalize="characters" placeholder="{{ __('part_inquiry.placeholder_vin') }}" maxlength="17"
                                                   class="w-full rounded-xl border-2 border-gray-200 bg-gray-50/50 px-4 py-3 font-mono text-sm uppercase text-navy placeholder:normal-case placeholder:font-sans placeholder:text-gray-400 focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200">
                                            {{-- VIN progress bar --}}
                                            <div x-show="form.vin_number.length > 0"
                                                 x-transition:enter="transition ease-out duration-200"
                                                 x-transition:enter-start="opacity-0"
                                                 x-transition:enter-end="opacity-100"
                                                 class="mt-2">
                                                <div class="flex items-center justify-between gap-2">
                                                    <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                                        <div class="h-full rounded-full transition-all duration-300"
                                                             :class="vinProgress >= 17 ? 'bg-emerald-500' : vinProgress >= 10 ? 'bg-amber' : 'bg-gray-300'"
                                                             :style="'width: ' + (vinProgress / 17 * 100) + '%'"></div>
                                                    </div>
                                                    <span class="text-[10px] font-bold"
                                                          :class="vinProgress >= 17 ? 'text-emerald-600' : 'text-muted'"
                                                          x-text="vinProgress + '/17'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Timing & notes --}}
                            <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white/90 shadow-md shadow-amber/5">
                                <button type="button"
                                        @click="expandedMore = !expandedMore"
                                        class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left transition-all hover:bg-gray-50/80 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-amber/20 to-orange-100 text-amber shadow-sm">
                                            <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-bold text-navy">{{ __('part_inquiry.timing_title') }}</p>
                                            <p class="text-xs text-muted">{{ __('part_inquiry.timing_subtitle') }}</p>
                                        </div>
                                    </div>
                                    <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-gray-400 transition-all duration-200"
                                        x-bind:class="expandedMore ? 'rotate-180 text-amber-text' : ''" />
                                </button>
                                <div x-show="expandedMore" x-collapse class="border-t border-gray-100 px-5 pb-5 pt-2 sm:px-6">
                                    <div class="space-y-5">
                                        {{-- Urgency with emoji icons --}}
                                        <div>
                                            <label class="mb-2.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_urgency') }}</label>
                                            <div class="grid grid-cols-3 gap-2.5">
                                                <label class="group relative cursor-pointer">
                                                    <input type="radio" x-model="form.urgency" value="normal" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 px-2 text-center
                                                                transition-all duration-200
                                                                peer-checked:border-emerald-400 peer-checked:bg-emerald-50 peer-checked:shadow-lg peer-checked:shadow-emerald-100
                                                                group-hover:border-emerald-200 group-hover:bg-emerald-50/30">
                                                        <span class="text-xl mb-1">🟢</span>
                                                        <span class="text-xs font-bold text-navy peer-checked:text-emerald-800">{{ __('part_inquiry.urgency_normal') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight text-muted peer-checked:text-emerald-700/90">{{ __('part_inquiry.urgency_normal_hint') }}</span>
                                                    </div>
                                                </label>
                                                <label class="group relative cursor-pointer">
                                                    <input type="radio" x-model="form.urgency" value="soon" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 px-2 text-center
                                                                transition-all duration-200
                                                                peer-checked:border-amber/50 peer-checked:bg-amber/10 peer-checked:shadow-lg peer-checked:shadow-amber/10
                                                                group-hover:border-amber/30 group-hover:bg-amber/5">
                                                        <span class="text-xl mb-1">🟡</span>
                                                        <span class="text-xs font-bold text-navy peer-checked:text-amber-text">{{ __('part_inquiry.urgency_soon') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight text-muted peer-checked:text-amber-text/90">{{ __('part_inquiry.urgency_soon_hint') }}</span>
                                                    </div>
                                                </label>
                                                <label class="group relative cursor-pointer">
                                                    <input type="radio" x-model="form.urgency" value="urgent" class="peer sr-only">
                                                    <div class="flex flex-col items-center justify-center rounded-xl border-2 border-gray-200 bg-gray-50/50 py-3.5 px-2 text-center
                                                                transition-all duration-200
                                                                peer-checked:border-red-400 peer-checked:bg-red-50 peer-checked:shadow-lg peer-checked:shadow-red-100
                                                                group-hover:border-red-200 group-hover:bg-red-50/30">
                                                        <span class="text-xl mb-1">🔴</span>
                                                        <span class="text-xs font-bold text-navy peer-checked:text-red-800">{{ __('part_inquiry.urgency_urgent') }}</span>
                                                        <span class="mt-0.5 text-[10px] leading-tight text-muted peer-checked:text-red-700/90">{{ __('part_inquiry.urgency_urgent_hint') }}</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>

                                        {{-- Quantity stepper with animation --}}
                                        <div>
                                            <label class="mb-2.5 text-sm font-semibold text-navy">{{ __('part_inquiry.label_quantity') }}</label>
                                            <div class="inline-flex h-12 max-w-[220px] items-stretch overflow-hidden rounded-xl border-2 border-gray-200 bg-gray-50/50 shadow-sm">
                                                <button type="button"
                                                        @click="form.quantity = Math.max(1, form.quantity - 1)"
                                                        class="flex w-12 items-center justify-center text-navy transition-all hover:bg-gray-200 active:scale-90"
                                                        :class="form.quantity <= 1 ? 'cursor-not-allowed opacity-30' : ''">
                                                    <x-heroicon-o-minus class="h-5 w-5" />
                                                </button>
                                                <input type="text" inputmode="numeric"
                                                       :value="form.quantity"
                                                       @change="form.quantity = Math.max(1, Math.min(99, parseInt($event.target.value) || 1)); $event.target.value = form.quantity"
                                                       class="relative z-10 w-16 border-x-2 border-gray-200 bg-white text-center text-base font-bold text-navy focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all">
                                                <button type="button"
                                                        @click="form.quantity = Math.min(99, form.quantity + 1)"
                                                        class="flex w-12 items-center justify-center text-navy transition-all hover:bg-gray-200 active:scale-90"
                                                        :class="form.quantity >= 99 ? 'cursor-not-allowed opacity-30' : ''">
                                                    <x-heroicon-o-plus class="h-5 w-5" />
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Notes with character counter --}}
                                        <div>
                                            <label class="mb-2 flex items-center justify-between text-sm font-semibold text-navy">
                                                <span>{{ __('part_inquiry.label_notes') }}</span>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                      :class="notesLength >= 450 ? 'bg-red-100 text-red-700' : notesLength >= 350 ? 'bg-amber/15 text-amber-text' : 'bg-gray-100 text-muted'"
                                                      x-text="notesLength + '/500'"></span>
                                            </label>
                                            <textarea x-model="form.notes" rows="3" maxlength="500" placeholder="{{ __('part_inquiry.notes_placeholder') }}"
                                                      class="w-full resize-none rounded-xl border-2 border-gray-200 bg-gray-50/50 px-4 py-3 text-sm text-navy placeholder:text-gray-400 focus:border-navy focus:bg-white focus:outline-none focus:ring-4 focus:ring-navy/10 transition-all duration-200"></textarea>
                                            {{-- Character progress bar --}}
                                            <div x-show="notesLength > 0"
                                                 class="mt-2 h-1 rounded-full bg-gray-100 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-300"
                                                     :class="notesLength >= 450 ? 'bg-red-500' : notesLength >= 350 ? 'bg-amber' : 'bg-emerald-500'"
                                                     :style="'width: ' + (notesLength / 500 * 100) + '%'"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Back to step 1 --}}
                            <button type="button"
                                    @click="collapseDetails()"
                                    class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 bg-white/60 py-4 text-sm font-semibold text-navy
                                           transition-all duration-200 hover:border-navy/50 hover:bg-navy/5 focus:outline-none focus:ring-2 focus:ring-navy/30">
                                <x-heroicon-o-arrow-left class="h-5 w-5 transition-transform duration-200 hover:-translate-x-0.5" />
                                <span>{{ __('part_inquiry.back_step1') }}</span>
                            </button>
                        </div>

                        {{-- Validation errors --}}
                        <div x-show="Object.keys(errors).length > 0 && state !== 'loading'"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="flex items-start gap-2.5 rounded-2xl border border-red-200 bg-red-50/80 p-4 text-sm text-red-700 shadow-sm">
                            <x-heroicon-o-exclamation-circle class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
                            <span x-text="Object.values(errors)[0]?.[0] ?? validationFallback"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Footer with trust badges and progress bar --}}
            <div class="shrink-0 border-t border-gray-100 bg-gradient-to-b from-white to-bg-page/50 px-5 py-4 sm:px-7">
                {{-- Submit progress bar --}}
                <div x-show="state === 'loading'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0"
                     x-transition:enter-end="opacity-100"
                     class="mb-3 h-1 overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full animate-shimmer shimmer-progress rounded-full bg-gradient-to-r from-amber via-orange-400 to-amber"></div>
                </div>

                <button type="button" @click="submit" :disabled="state === 'loading'"
                        class="btn-primary w-full py-4 text-sm font-bold disabled:cursor-wait disabled:opacity-60 disabled:hover:scale-100
                               transition-all duration-200 active:scale-95">
                    <span x-show="state !== 'loading'" class="flex items-center justify-center gap-2">
                        <x-heroicon-o-paper-airplane class="h-5 w-5" />
                        {{ __('part_inquiry.submit') }}
                    </span>
                    <span x-show="state === 'loading'" class="flex items-center justify-center gap-2">
                        <svg class="h-5 w-5 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ __('part_inquiry.submitting') }}
                    </span>
                </button>

                {{-- Trust badges row --}}
                <div class="mt-3 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 text-[10px] text-muted">
                    <span class="inline-flex items-center gap-1">
                        <x-heroicon-s-lock-closed class="h-3 w-3 text-amber" />
                        {{ __('part_inquiry.trust_ssl') }}
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1">
                        <x-heroicon-s-clock class="h-3 w-3 text-emerald-600" />
                        {{ __('part_inquiry.trust_response', ['hours' => $inquiryHours]) }}
                    </span>
                    <span class="text-gray-300">·</span>
                    <span class="inline-flex items-center gap-1">
                        <x-heroicon-s-check-badge class="h-3 w-3 text-blue-600" />
                        {{ __('part_inquiry.trust_nospam') }}
                    </span>
                </div>

                {{-- Keyboard hint --}}
                <p class="mt-2 hidden sm:block text-center text-[10px] text-muted">
                    {!! __('part_inquiry.keyboard_hint_html', [
                        'kbd' => '<kbd class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-semibold">'.e(__('part_inquiry.keyboard_key')).'</kbd>',
                    ]) !!}
                </p>
            </div>
        </div>
    </div>
</div>
