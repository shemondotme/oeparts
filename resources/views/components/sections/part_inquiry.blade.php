{{-- Section: part_inquiry
     content: headline(ml), subheadline(ml), button_text(ml)
     Inline quick-inquiry form on the page (not a modal).
--}}
<section class="relative py-14 md:py-20 px-4 overflow-hidden">

    {{-- Soft amber/cream background with gradient --}}
    <div class="absolute inset-0 bg-gradient-to-br from-amber-50/50 via-orange-50/30 to-amber-50/50"></div>

    {{-- Decorative blobs --}}
    <div class="absolute inset-0 opacity-20 pointer-events-none">
        <div class="absolute top-0 right-0 w-96 h-96 bg-amber/15 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-72 h-72 bg-blue-500/10 rounded-full filter blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-2xl mx-auto">

        <x-section-heading
            :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
            :headline="trans_field($section->content['headline'] ?? null)"
            :subheadline="trans_field($section->content['subheadline'] ?? null)"
            :accentBar="true"
            class="mb-12"
        />

        {{-- Inline Inquiry Form --}}
        <div
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
                        const res = await fetch('/api/inquiry', {
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
            }"
        >
            {{-- Form Card --}}
            <div class="bg-white rounded-3xl shadow-lg shadow-amber/5 border border-gray-100 overflow-hidden">

                {{-- Idle / Error state — show form --}}
                <div x-show="state !== 'success'" class="p-6 md:p-8">
                    <form @submit.prevent="submit" class="space-y-5" novalidate>
                        {{-- Honeypot --}}
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                        {{-- OEM Part Number --}}
                        <div>
                            <label for="inquiry-oem" class="block text-sm font-semibold text-navy mb-2">OEM Part Number <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                x-model="oem"
                                id="inquiry-oem"
                                inputmode="text"
                                autocapitalize="characters"
                                placeholder="e.g. 1K0407271F"
                                class="w-full px-4 py-3 rounded-xl text-navy font-mono text-sm
                                       uppercase placeholder:normal-case placeholder:font-sans placeholder:text-gray-400
                                       bg-gray-50 border border-gray-200
                                       focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10
                                       transition-all"
                                required
                                :disabled="state === 'loading'"
                                :aria-invalid="state === 'error'"
                                aria-describedby="inquiry-oem-error"
                            >
                            <p x-show="state === 'error' && !oem" id="inquiry-oem-error" role="alert" class="mt-1 text-red-600 text-xs">OEM part number is required.</p>
                        </div>

                        {{-- Email Address --}}
                        <div>
                            <label for="inquiry-email" class="block text-sm font-semibold text-navy mb-2">Your Email Address <span class="text-red-500">*</span></label>
                            <input
                                type="email"
                                inputmode="email"
                                x-model="email"
                                id="inquiry-email"
                                placeholder="you@company.com"
                                class="w-full px-4 py-3 rounded-xl text-navy text-sm
                                       placeholder:text-gray-400
                                       bg-gray-50 border border-gray-200
                                       focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10
                                       transition-all"
                                required
                                :disabled="state === 'loading'"
                                :aria-invalid="state === 'error'"
                                aria-describedby="inquiry-email-error"
                            >
                            <p x-show="state === 'error' && !email" id="inquiry-email-error" role="alert" class="mt-1 text-red-600 text-xs">Email address is required.</p>
                        </div>

                        {{-- Vehicle Details (collapsible) --}}
                        <div x-data="{ expanded: false }">
                            <button
                                type="button"
                                @click="expanded = !expanded"
                                :aria-expanded="expanded"
                                aria-controls="vehicle-details-panel"
                                class="flex items-center gap-2 text-sm font-medium text-navy/70 hover:text-navy transition-colors"
                            >
                                <x-heroicon-o-plus-circle class="w-4 h-4" x-show="!expanded" />
                                <x-heroicon-o-minus-circle class="w-4 h-4" x-show="expanded" x-cloak />
                                <span x-text="expanded ? 'Hide Vehicle Details' : 'Add Vehicle Details (optional)'"></span>
                            </button>

                            <div id="vehicle-details-panel" x-show="expanded" x-collapse x-cloak class="mt-4 space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="vehicle-make" class="sr-only">Vehicle Make</label>
                                        <input
                                            type="text"
                                            x-model="vehicle_make"
                                            id="vehicle-make"
                                            placeholder="Make (e.g. VW)"
                                            class="w-full px-4 py-2.5 rounded-xl text-navy text-sm
                                                   bg-gray-50 border border-gray-200
                                                   focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10"
                                            :disabled="state === 'loading'"
                                        >
                                    </div>
                                    <div>
                                        <label for="vehicle-model" class="sr-only">Vehicle Model</label>
                                        <input
                                            type="text"
                                            x-model="vehicle_model"
                                            id="vehicle-model"
                                            placeholder="Model (e.g. Golf)"
                                            class="w-full px-4 py-2.5 rounded-xl text-navy text-sm
                                                   bg-gray-50 border border-gray-200
                                                   focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10"
                                            :disabled="state === 'loading'"
                                        >
                                    </div>
                                </div>
                                <div>
                                    <label for="vehicle-year" class="sr-only">Vehicle Year</label>
                                    <input
                                        type="text"
                                        x-model="vehicle_year"
                                        id="vehicle-year"
                                        placeholder="Year (e.g. 2015)"
                                        class="w-full px-4 py-2.5 rounded-xl text-navy text-sm
                                               bg-gray-50 border border-gray-200
                                               focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10"
                                        :disabled="state === 'loading'"
                                    >
                                </div>
                                <div>
                                    <label for="inquiry-notes" class="sr-only">Additional Notes</label>
                                    <textarea
                                        x-model="notes"
                                        id="inquiry-notes"
                                        placeholder="Any additional notes..."
                                        rows="2"
                                        class="w-full px-4 py-2.5 rounded-xl text-navy text-sm resize-none
                                               bg-gray-50 border border-gray-200
                                               focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/10"
                                        :disabled="state === 'loading'"
                                    ></textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Error Message --}}
                        <div x-show="state === 'error'" x-cloak
                             class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-center gap-2">
                            <x-heroicon-s-exclamation-circle class="w-5 h-5 shrink-0" />
                            <span x-text="error"></span>
                        </div>

                        {{-- Submit Button --}}
                        <button
                            type="submit"
                            :disabled="state === 'loading'"
                            class="w-full btn-primary py-3.5 rounded-xl
                                   flex items-center justify-center gap-2 transition-all disabled:opacity-50"
                        >
                            <span x-show="state !== 'loading'" class="flex items-center gap-2">
                                <x-heroicon-o-paper-airplane class="w-5 h-5" />
                                {{ trans_field($section->content['button_text'] ?? null) ?: 'Submit Inquiry' }}
                            </span>
                            <span x-show="state === 'loading'" x-cloak class="flex items-center gap-2">
                                <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Processing...
                            </span>
                        </button>
                    </form>
                </div>

                {{-- Success State --}}
                <div x-show="state === 'success'" x-cloak class="p-8 md:p-10 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-4">
                        <x-heroicon-s-check-circle class="w-8 h-8 text-emerald-600" />
                    </div>
                    <h3 class="font-display font-bold text-navy text-xl mb-2">Inquiry Submitted!</h3>
                    <p class="text-sm text-muted mb-6">Thank you. We'll review your request and respond within 24 hours.</p>
                    <button
                        @click="reset()"
                        class="text-sm font-semibold text-navy hover:text-amber transition-colors inline-flex items-center gap-1"
                    >
                        <x-heroicon-o-arrow-path class="w-4 h-4" />
                        Submit Another Inquiry
                    </button>
                </div>
            </div>

            {{-- Trust text --}}
            <p class="mt-4 text-sm text-muted text-center flex items-center justify-center gap-2">
                <x-heroicon-s-lock-closed class="w-3.5 h-3.5 text-amber" />
                <span>Secure inquiry &bull; Response within 24 hours</span>
            </p>
        </div>
    </div>
</section>
