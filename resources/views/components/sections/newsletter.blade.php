{{-- Section: newsletter
     content: headline(ml), subheadline(ml), button_text(ml),
              placeholder(ml), success_text(ml)
     Submits via fetch() to POST /api/newsletter/subscribe.
     Honeypot field included.
--}}
<section class="bg-gradient-to-b from-amber-50/50 via-orange-50/30 to-amber-50/50 py-14 md:py-20 px-4 relative overflow-hidden">

    {{-- Decorative blobs --}}
    <div class="absolute inset-0 opacity-15 pointer-events-none">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-amber/15 rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-96 h-96 bg-blue-500/15 rounded-full filter blur-3xl"></div>
    </div>

    <div class="relative z-10 max-w-4xl mx-auto">

        {{-- White card with shadow --}}
        <div class="bg-white rounded-3xl p-8 md:p-12 shadow-xl shadow-amber/5 border border-gray-100">

            <x-section-heading
                :eyebrow="trans_field($section->content['eyebrow'] ?? null)"
                :headline="trans_field($section->content['headline'] ?? null)"
                :subheadline="trans_field($section->content['subheadline'] ?? null)"
                :accentBar="true"
                class="mb-10"
            />

            <div
                x-data="{
                    email: '',
                    state: 'idle',
                    error: '',
                    successText: {{ json_encode(trans_field($section->content['success_text'] ?? null) ?: 'Thank you!') }},
                    async submit() {
                        if (!this.email || this.state === 'loading') return;
                        this.state = 'loading';
                        this.error = '';
                        try {
                            const res = await fetch('/{{ app()->getLocale() }}/newsletter/subscribe', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ email: this.email, lang: '{{ app()->getLocale() }}' }),
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
                    }
                }"
            >
                <template x-if="state !== 'success'">
                    <form @submit.prevent="submit" novalidate>
                        {{-- Honeypot --}}
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                        <div class="flex flex-col sm:flex-row gap-4 max-w-xl mx-auto">
                            <div class="relative flex-1">
                                {{-- Email icon --}}
                                <div class="absolute left-4 top-1/2 -translate-y-1/2" aria-hidden="true">
                                    <x-heroicon-o-envelope class="w-5 h-5 text-gray-400" />
                                </div>

                                <label for="newsletter-email" class="sr-only">Email address</label>
                                <input
                                    type="email"
                                    inputmode="email"
                                    x-model="email"
                                    id="newsletter-email"
                                    placeholder="{{ trans_field($section->content['placeholder'] ?? null) ?: 'Your email address' }}"
                                    class="w-full pl-12 pr-5 py-4 rounded-xl text-gray-900 text-sm
                                           bg-white border-2 border-gray-200
                                           placeholder-gray-400
                                           focus:outline-none focus:border-amber focus:ring-4 focus:ring-amber/20
                                           transition-all duration-300"
                                    required
                                    :disabled="state === 'loading'"
                                    :aria-invalid="state === 'error'"
                                    aria-describedby="newsletter-error"
                                >
                            </div>

                            <button
                                type="submit"
                                :disabled="state === 'loading'"
                                class="btn-primary"
                            >
                                <span x-show="state !== 'loading'" class="flex items-center gap-2">
                                    {{ trans_field($section->content['button_text'] ?? null) ?: 'SUBSCRIBE' }}
                                    <x-heroicon-o-arrow-right class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" aria-hidden="true" />
                                </span>
                                <span x-show="state === 'loading'" class="flex items-center gap-2">
                                    <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Sending...
                                </span>
                            </button>
                        </div>

                        <p x-show="state === 'error'" x-text="error" id="newsletter-error" role="alert" class="mt-4 text-red-600 text-sm text-center font-medium"></p>

                        {{-- Trust text --}}
                        <p class="mt-6 text-sm text-muted text-center">
                            <x-heroicon-s-lock-closed class="w-3.5 h-3.5 inline mr-1 text-amber" aria-hidden="true" />
                            We respect your privacy. Unsubscribe at any time.
                        </p>
                    </form>
                </template>

                {{-- Success State --}}
                <template x-if="state === 'success'">
                    <div class="text-center py-8">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full
                                    bg-gradient-to-br from-emerald-500 to-teal-600
                                    shadow-xl shadow-emerald/30 mb-6
                                    animate-[scaleIn_0.5s_ease-out]">
                            <x-heroicon-s-check-circle class="w-10 h-10 text-white" />
                        </div>
                        <h3 class="font-display text-2xl font-bold text-navy mb-2">You're In!</h3>
                        <p class="text-body text-base" x-text="successText"></p>
                        <p class="mt-4 text-sm text-muted">Check your inbox for a confirmation email.</p>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>
