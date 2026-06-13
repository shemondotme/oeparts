{{-- Section: newsletter (Industrial Blueprint)
     content: eyebrow, headline(ml), subheadline(ml), button_text(ml), placeholder(ml), success_text(ml)
     Submits via fetch() to POST /{lang}/newsletter/subscribe. Honeypot included.
--}}
@php
    $eyebrow = trans_field($section->content['eyebrow'] ?? null);
    $headline = trans_field($section->content['headline'] ?? null);
    $subheadline = trans_field($section->content['subheadline'] ?? null);
    $buttonText = trans_field($section->content['button_text'] ?? null) ?: 'Subscribe';
    $placeholder = trans_field($section->content['placeholder'] ?? null) ?: 'Your email address';
    $sectionNumber = str_pad((int)(($section->sort_order ?? 10) / 10), 2, '0', STR_PAD_LEFT);
@endphp

<section class="relative bg-paper text-ink border-b border-rule">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-10 py-20 md:py-28">

        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10 items-center">

            <div class="col-span-12 lg:col-span-5">
                @if($eyebrow)
                <div class="flex items-center gap-4 mb-6">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ $eyebrow }}</span>
                </div>
                @endif
                @if($headline)
                <h2 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[14ch]">
                    {{ $headline }}<span class="text-amber">.</span>
                </h2>
                @endif
                @if($subheadline)
                <p class="mt-6 text-base text-body leading-relaxed max-w-md">
                    {{ $subheadline }}
                </p>
                @endif
            </div>

            <div class="col-span-12 lg:col-span-7 lg:pl-10 lg:border-l lg:border-rule"
                 x-data="{
                    email: '',
                    state: 'idle',
                    error: '',
                    successText: {{ json_encode(trans_field($section->content['success_text'] ?? null) ?: 'Subscription confirmed.') }},
                    async submit() {
                        if (!this.email || this.state === 'loading') return;
                        this.state = 'loading';
                        this.error = '';
                        try {
                            const honeypotData = {};
                            document.querySelectorAll('[name^=my_name], [name=valid_from]').forEach(el => {
                                honeypotData[el.name] = el.value;
                            });
                            const res = await fetch('{{ route('frontend.newsletter.subscribe', ['lang' => app()->getLocale()]) }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({ email: this.email, lang: '{{ app()->getLocale() }}', ...honeypotData }),
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
                 }">

                <template x-if="state !== 'success'">
                    <form @submit.prevent="submit" novalidate>
                        <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">
                        @honeypot

                        <label for="newsletter-email" class="bp-spec mb-3 inline-block">
                            {{ __('Enter email address') }}
                        </label>

                        {{-- Input + button row --}}
                        <div class="flex flex-col sm:flex-row border border-ink bg-paper focus-within:border-amber transition-colors">
                            <input type="email"
                                   inputmode="email"
                                   x-model="email"
                                   id="newsletter-email"
                                   placeholder="{{ $placeholder }}"
                                   class="flex-1 px-4 py-4 bg-transparent font-mono text-base text-ink
                                          placeholder:text-ink-muted placeholder:font-sans
                                          focus:outline-none"
                                   required
                                   :disabled="state === 'loading'"
                                   :aria-invalid="state === 'error'"
                                   aria-describedby="newsletter-error">
                            <button type="submit"
                                    :disabled="state === 'loading'"
                                    class="inline-flex items-center justify-center gap-2 px-6 py-4 bg-ink text-ivory
                                           font-mono text-xs font-bold uppercase tracking-[0.22em]
                                           hover:bg-amber hover:text-ink transition-colors
                                           disabled:opacity-50 disabled:cursor-not-allowed">
                                <span x-show="state !== 'loading'" class="inline-flex items-center gap-2">
                                    {{ $buttonText }}
                                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                                </span>
                                <span x-show="state === 'loading'" x-cloak class="inline-flex items-center gap-2">
                                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('Transmitting') }}
                                </span>
                            </button>
                        </div>

                        <p x-show="state === 'error'" x-text="error" id="newsletter-error" role="alert"
                           class="mt-3 font-mono text-[11px] uppercase tracking-wider text-red-600"></p>

                        <p class="mt-4 flex items-center gap-2 bp-spec-mono">
                            <x-heroicon-s-lock-closed class="w-3 h-3 text-amber-ink" />
                            {{ __('GDPR · Privacy respected · Unsubscribe any time') }}
                        </p>
                    </form>
                </template>

                <template x-if="state === 'success'">
                    <div class="border border-amber bg-amber/10 p-8">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 border-2 border-amber flex items-center justify-center shrink-0">
                                <x-heroicon-s-check class="w-5 h-5 text-amber-ink" />
                            </div>
                            <div>
                                <p class="font-mono text-[10px] tracking-[0.22em] uppercase text-amber-ink mb-2">
                                    {{ __('Status · Confirmed') }}
                                </p>
                                <h3 class="font-display text-2xl font-extrabold text-ink mb-2 tracking-tight">
                                    {{ __('Subscription logged') }}<span class="text-amber">.</span>
                                </h3>
                                <p class="text-body" x-text="successText"></p>
                                <p class="mt-3 bp-spec-mono">
                                    {{ __('Check inbox · confirmation email sent') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</section>
