@extends('layouts.app')

@php
    $lang     = app()->getLocale();
    $siteName = settings('general.site_name', 'OeParts');
    $phone    = settings('contact.phone', '');
    $email    = settings('contact.email', config('mail.from.address'));
    $contactTitle = trans('contact.title', [], $lang);
    $contactDescr = trans('contact.description', [], $lang);
@endphp

@section('title'){{ $contactTitle }} · {{ $siteName }}@endsection
@section('meta_description'){{ $contactDescr }}@endsection
@section('og_title'){{ $contactTitle }} · {{ $siteName }}@endsection
@section('canonical')
    <link rel="canonical" href="{{ url('/' . $lang . '/contact') }}">
@endsection
@section('hreflang')
    @foreach(['en','de','lt','fr','es'] as $hLang)
        <link rel="alternate" hreflang="{{ $hLang }}" href="{{ url('/' . $hLang . '/contact') }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url('/en/contact') }}">
@endsection

{{-- ══════════════════════════════════════════════════════════════════════
     INDUSTRIAL BLUEPRINT — CONTACT
     Document-style enquiry panel with OTP-verified contact form.
     ══════════════════════════════════════════════════════════════════ --}}
@section('content')

<div class="relative bg-ivory text-ink min-h-screen">

    <div class="fixed inset-0 bg-grid-ivory-fine bg-grid-sm opacity-70 pointer-events-none" aria-hidden="true"></div>

    <div class="relative max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-10 pt-10 pb-24">

        {{-- ═══ Doc header ═══ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-5 border-b border-rule mb-10 bp-rise">
            <nav class="flex items-center gap-3 font-mono text-[11px] uppercase tracking-[0.16em] text-ink-muted" aria-label="Breadcrumb">
                <a href="{{ url('/'.$lang.'/') }}" class="hover:text-ink transition-colors">{{ __('Home') }}</a>
                <span class="text-rule-strong">/</span>
                <span class="text-ink">{{ __('Contact Us') }}</span>
            </nav>
            <div class="font-mono text-[10px] tracking-[0.2em] uppercase text-ink-muted">
                DOC · CONTACT · ENQUIRY-FORM
            </div>
        </div>

        {{-- ═══ Hero ═══ --}}
        <header class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-8 gap-y-8 mb-12 bp-rise bp-rise-delay-1">
            <div class="col-span-12 lg:col-span-8">
                <div class="flex items-center gap-4 mb-8">
                    <span class="w-10 h-[3px] bg-amber inline-block"></span>
                    <span class="bp-spec text-amber-ink">{{ __('Contact · Desk') }}</span>
                </div>
                <h1 class="font-display font-extrabold text-ink leading-[0.95] tracking-[-0.03em]
                           text-4xl sm:text-5xl lg:text-6xl max-w-[22ch]">
                    {{ $contactTitle }}<span class="text-amber">.</span>
                </h1>
                <div class="mt-6 mb-6">
                    <div class="bp-rule-draw h-px bg-ink/70 origin-left"></div>
                </div>
                <p class="max-w-xl text-lg text-body leading-relaxed">
                    {{ $contactDescr }}
                </p>
            </div>

            {{-- Quick contact strip --}}
            <aside class="col-span-12 lg:col-span-4">
                <div class="border border-ink bg-paper bp-register">
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">{{ __('Direct · Channel') }}</span>
                        <span class="font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/60">{{ __('MON–FRI') }}</span>
                    </div>
                    <dl class="p-5 space-y-3.5">
                        <div>
                            <dt class="bp-spec text-ink-muted">{{ __('Email') }}</dt>
                            <dd class="mt-1">
                                <a href="mailto:{{ $email }}" class="font-mono text-sm font-bold text-ink hover:text-amber-ink transition-colors break-all">
                                    {{ $email }}
                                </a>
                            </dd>
                        </div>
                        @if($phone)
                        <div>
                            <dt class="bp-spec text-ink-muted">{{ __('Phone') }}</dt>
                            <dd class="mt-1">
                                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="font-mono text-sm font-bold text-ink hover:text-amber-ink transition-colors tabular-nums">
                                    {{ $phone }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        <div>
                            <dt class="bp-spec text-ink-muted">{{ trans('contact.response_time') }}</dt>
                            <dd class="mt-1 font-mono text-sm font-bold text-ink">
                                {{ trans('contact.response_time_value') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="bp-spec text-ink-muted">{{ __('Hours') }}</dt>
                            <dd class="mt-1 font-mono text-sm font-bold text-ink tabular-nums">09:00–18:00 CET</dd>
                        </div>
                    </dl>
                </div>
            </aside>
        </header>

        {{-- ═══ Main grid: Form + Info rail ═══ --}}
        <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 lg:gap-x-10 gap-y-10 bp-rise bp-rise-delay-2">

            {{-- ── Form column ── --}}
            <section class="col-span-12 lg:col-span-8">
                <div class="flex items-end justify-between pb-3 border-b border-ink mb-6">
                    <span class="bp-spec text-ink">01 · {{ __('Enquiry · Form') }}</span>
                </div>

                <div x-data="contactForm()"
                     class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                    {{-- Status bar --}}
                    <div class="px-5 py-3 bg-ink text-ivory flex items-center justify-between">
                        <span class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase">ENQ-FORM · REV.02</span>
                        <span class="flex items-center gap-1.5 font-mono text-[10px] tracking-[0.18em] uppercase text-ivory/70">
                            <span class="w-1.5 h-1.5 bg-emerald-500"></span>
                            <span>{{ __('Operational') }}</span>
                        </span>
                    </div>

                    <div class="p-6 lg:p-8">

                        {{-- Success Message --}}
                        <div x-show="successMsg" x-cloak x-transition
                             role="status" aria-live="polite"
                             class="mb-6 px-4 py-3 border border-emerald-600 bg-emerald-50 flex items-start gap-3">
                            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" aria-hidden="true" />
                            <p class="text-sm text-emerald-800" x-text="successMsg"></p>
                        </div>

                        {{-- Error Message --}}
                        <div x-show="errorMsg" x-cloak x-transition
                             role="alert" aria-live="assertive"
                             class="mb-6 px-4 py-3 border border-red-600 bg-red-50 flex items-start gap-3">
                            <x-heroicon-s-exclamation-triangle class="w-5 h-5 text-red-600 shrink-0 mt-0.5" aria-hidden="true" />
                            <p class="text-sm text-red-800" x-text="errorMsg"></p>
                        </div>

                        <form @submit.prevent="submitForm" class="space-y-6">
                            @csrf
                            <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true">
                            @honeypot

                            {{-- Name --}}
                            <div>
                                <label for="name" class="flex items-center justify-between mb-2">
                                    <span class="bp-spec text-ink">{{ trans('contact.name') }} <span class="text-red-600">*</span></span>
                                    <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.01</span>
                                </label>
                                <input type="text" id="name" x-model="form.name" required
                                       class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                              focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                       placeholder="{{ trans('contact.name_placeholder') }}">
                                <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.name" x-text="fieldErrors.name"></p>
                            </div>

                            <div>
                                <label for="email" class="flex items-center justify-between mb-2">
                                    <span class="bp-spec text-ink">{{ trans('contact.email') }} <span class="text-red-600">*</span></span>
                                    <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.02</span>
                                </label>
                                <input type="email" id="email" x-model="form.email" required
                                       class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                              focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                       placeholder="{{ trans('contact.email_placeholder') }}">
                                <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.email" x-text="fieldErrors.email"></p>
                            </div>

                            {{-- Subject Type --}}
                            <div>
                                <label for="subject_type" class="flex items-center justify-between mb-2">
                                    <span class="bp-spec text-ink">{{ trans('contact.subject') }} <span class="text-red-600">*</span></span>
                                    <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.03</span>
                                </label>
                                <div class="relative">
                                    <select id="subject_type" x-model="form.subject_type" required
                                            class="w-full px-4 py-3 pr-10 border border-ink bg-ivory font-mono text-sm text-ink
                                                   focus:outline-none focus:bg-paper focus:border-amber appearance-none cursor-pointer">
                                        <option value="">{{ trans('contact.select_subject') }}</option>
                                        <option value="general_inquiry">{{ trans('contact.subjects.general_inquiry') }}</option>
                                        <option value="part_not_found">{{ trans('contact.subjects.part_not_found') }}</option>
                                        <option value="order_issue">{{ trans('contact.subjects.order_issue') }}</option>
                                        <option value="shipping_question">{{ trans('contact.subjects.shipping_question') }}</option>
                                        <option value="return_refund">{{ trans('contact.subjects.return_refund') }}</option>
                                        <option value="b2b_partnership">{{ trans('contact.subjects.b2b_partnership') }}</option>
                                        <option value="other">{{ trans('contact.subjects.other') }}</option>
                                    </select>
                                    <x-heroicon-s-chevron-down class="w-4 h-4 text-ink-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                                </div>
                                <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.subject_type" x-text="fieldErrors.subject_type"></p>
                            </div>

                            {{-- ─── Conditional: Order details (order_issue / shipping_question / return_refund) ─── --}}
                            <div x-show="needsOrder" x-cloak x-collapse>
                                <div class="flex items-center gap-3 mb-3 pt-2">
                                    <span class="w-6 h-[2px] bg-amber inline-block"></span>
                                    <span class="bp-spec text-amber-ink">{{ trans('contact.section_order_details') }}</span>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="order_number" class="flex items-center justify-between mb-2">
                                            <span class="bp-spec text-ink">
                                                {{ trans('contact.order_number') }}
                                                <span class="text-red-600" x-show="form.subject_type === 'order_issue' || form.subject_type === 'return_refund'">*</span>
                                            </span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.04</span>
                                        </label>
                                        <input type="text" id="order_number" x-model="form.order_number"
                                               class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                                      focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                               placeholder="{{ trans('contact.order_number_placeholder') }}">
                                        <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.order_number" x-text="fieldErrors.order_number"></p>
                                    </div>
                                    <div x-show="form.subject_type === 'return_refund'">
                                        <label for="oem_number_r" class="flex items-center justify-between mb-2">
                                            <span class="bp-spec text-ink">{{ trans('contact.oem_number') }}</span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.05</span>
                                        </label>
                                        <input type="text" id="oem_number_r" x-model="form.oem_number"
                                               class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink tracking-wide
                                                      focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                               placeholder="{{ trans('contact.oem_number_placeholder') }}">
                                    </div>
                                </div>
                            </div>

                            {{-- ─── Conditional: Part details (part_not_found) ─── --}}
                            <div x-show="form.subject_type === 'part_not_found'" x-cloak x-collapse>
                                <div class="flex items-center gap-3 mb-3 pt-2">
                                    <span class="w-6 h-[2px] bg-amber inline-block"></span>
                                    <span class="bp-spec text-amber-ink">{{ trans('contact.section_part_details') }}</span>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label for="oem_number" class="flex items-center justify-between mb-2">
                                            <span class="bp-spec text-ink">{{ trans('contact.oem_number') }} <span class="text-red-600">*</span></span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.05</span>
                                        </label>
                                        <input type="text" id="oem_number" x-model="form.oem_number"
                                               class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink tracking-wide
                                                      focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                               placeholder="{{ trans('contact.oem_number_placeholder') }}">
                                        <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.oem_number" x-text="fieldErrors.oem_number"></p>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div class="sm:col-span-1">
                                            <label for="manufacturer" class="flex items-center justify-between mb-2">
                                                <span class="bp-spec text-ink">{{ trans('contact.manufacturer') }}</span>
                                                <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.06</span>
                                            </label>
                                            <input type="text" id="manufacturer" x-model="form.manufacturer"
                                                   class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                                          focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                                   placeholder="{{ trans('contact.manufacturer_placeholder') }}">
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label for="car_model" class="flex items-center justify-between mb-2">
                                                <span class="bp-spec text-ink">{{ trans('contact.car_model') }}</span>
                                                <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.07</span>
                                            </label>
                                            <input type="text" id="car_model" x-model="form.car_model"
                                                   class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                                          focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                                   placeholder="{{ trans('contact.car_model_placeholder') }}">
                                        </div>
                                        <div class="sm:col-span-1">
                                            <label for="year" class="flex items-center justify-between mb-2">
                                                <span class="bp-spec text-ink">{{ trans('contact.vehicle_year') }}</span>
                                                <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.08</span>
                                            </label>
                                            <input type="text" id="year" x-model="form.year" inputmode="numeric" maxlength="4"
                                                   class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink tabular-nums
                                                          focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                                   placeholder="{{ trans('contact.vehicle_year_placeholder') }}">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="vin_number" class="flex items-center justify-between mb-2">
                                            <span class="bp-spec text-ink">{{ trans('contact.vin_number') }}</span>
                                            <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.09</span>
                                        </label>
                                        <input type="text" id="vin_number" x-model="form.vin_number" maxlength="17"
                                               class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink tracking-[0.1em] uppercase
                                                      focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                               placeholder="{{ trans('contact.vin_number_placeholder') }}">
                                    </div>
                                </div>
                            </div>

                            {{-- ─── Conditional: B2B details ─── --}}
                            <div x-show="form.subject_type === 'b2b_partnership'" x-cloak x-collapse>
                                <div class="flex items-center gap-3 mb-3 pt-2">
                                    <span class="w-6 h-[2px] bg-amber inline-block"></span>
                                    <span class="bp-spec text-amber-ink">{{ trans('contact.section_b2b_details') }}</span>
                                </div>
                                <div>
                                    <label for="company" class="flex items-center justify-between mb-2">
                                        <span class="bp-spec text-ink">{{ trans('contact.company_name') }}</span>
                                        <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.10</span>
                                    </label>
                                    <input type="text" id="company" x-model="form.manufacturer"
                                           class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                                  focus:outline-none focus:bg-paper focus:border-amber placeholder:text-ink-muted"
                                           placeholder="{{ trans('contact.company_name_placeholder') }}">
                                </div>
                            </div>

                            {{-- Message --}}
                            <div>
                                <label for="message" class="flex items-center justify-between mb-2">
                                    <span class="bp-spec text-ink">{{ trans('contact.message') }} <span class="text-red-600">*</span></span>
                                    <span class="font-mono text-[9px] tracking-[0.22em] uppercase text-ink-muted">01.11</span>
                                </label>
                                <textarea id="message" x-model="form.message" required rows="6"
                                          class="w-full px-4 py-3 border border-ink bg-ivory font-mono text-sm text-ink
                                                 focus:outline-none focus:bg-paper focus:border-amber resize-y placeholder:text-ink-muted"
                                          placeholder="{{ trans('contact.message_placeholder') }}"></textarea>
                                <div class="flex items-center justify-between mt-1.5">
                                    <p class="font-mono text-[10px] tracking-[0.06em] text-ink-muted">{{ trans('contact.message_min_length') }}</p>
                                    <p class="font-mono text-[10px] tabular-nums" :class="form.message.length >= 10 ? 'text-emerald-700' : 'text-ink-muted'">
                                        <span x-text="form.message.length"></span> / 5000
                                    </p>
                                </div>
                                <p class="mt-1 font-mono text-[11px] text-red-600" x-show="fieldErrors.message" x-text="fieldErrors.message"></p>
                            </div>

                            {{-- Honeypot --}}
                            <input type="text" x-model="form.website" class="hidden" tabindex="-1" autocomplete="off">

                            {{-- Submit --}}
                            <div class="pt-2">
                                <button type="submit" :disabled="!verified || submitting"
                                        class="group w-full inline-flex items-center justify-center gap-3 px-6 py-4
                                               bg-ink text-ivory border border-ink
                                               font-mono text-[12px] font-bold tracking-[0.24em] uppercase
                                               hover:bg-amber hover:text-ink hover:border-amber
                                               disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-ink disabled:hover:text-ivory
                                               transition-colors">
                                    <x-heroicon-o-paper-airplane class="w-4 h-4" />
                                    <span x-text="submitting ? '{{ trans('contact.sending') }}…' : '{{ trans('contact.send_message') }}'"></span>
                                    <x-heroicon-s-arrow-long-right class="w-4 h-4 transition-transform group-hover:translate-x-1" x-show="!submitting" />
                                </button>
                                <p class="mt-3 font-mono text-[10px] tracking-[0.18em] uppercase text-center"
                                   :class="verified ? 'text-emerald-700' : 'text-ink-muted'">
                                    <span x-show="!verified">{{ __('Verify email to enable submit') }}</span>
                                    <span x-show="verified" class="inline-flex items-center gap-1.5">
                                        <x-heroicon-s-check class="w-3 h-3" />
                                        {{ __('Ready to send') }}
                                    </span>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            {{-- ── Info rail ── --}}
            <aside class="col-span-12 lg:col-span-4 space-y-6">
                <div class="flex items-end justify-between pb-3 border-b border-ink">
                    <span class="bp-spec text-ink">02 · {{ __('Info · Rail') }}</span>
                </div>

                <div class="border border-ink bg-paper">
                    <div class="px-5 py-4 border-b border-rule flex items-start gap-4">
                        <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                            <x-heroicon-s-envelope class="w-5 h-5 text-ink" />
                        </div>
                        <div class="min-w-0">
                            <p class="bp-spec text-amber-ink">{{ trans('contact.email_us') }}</p>
                            <a href="mailto:{{ $email }}" class="mt-1 block font-mono text-sm font-bold text-ink hover:text-amber-ink break-all">
                                {{ $email }}
                            </a>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-b border-rule flex items-start gap-4">
                        <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                            <x-heroicon-s-clock class="w-5 h-5 text-ink" />
                        </div>
                        <div class="min-w-0">
                            <p class="bp-spec text-amber-ink">{{ trans('contact.response_time') }}</p>
                            <p class="mt-1 font-mono text-sm font-bold text-ink">{{ trans('contact.response_time_value') }}</p>
                        </div>
                    </div>
                    <div class="px-5 py-4 flex items-start gap-4">
                        <div class="w-10 h-10 border border-ink bg-ivory-alt flex items-center justify-center shrink-0">
                            <x-heroicon-s-shield-check class="w-5 h-5 text-ink" />
                        </div>
                        <div class="min-w-0">
                            <p class="bp-spec text-amber-ink">{{ trans('contact.secure') }}</p>
                            <p class="mt-1 text-sm text-body leading-relaxed">{{ trans('contact.secure_note') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Quick actions panel --}}
                <div class="border border-ink bg-ink text-ivory p-5">
                    <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-amber mb-3">{{ __('Shortcut · Panel') }}</p>
                    <p class="font-display text-lg font-extrabold tracking-[-0.02em] leading-tight">
                        {{ __('Looking for a part?') }}
                    </p>
                    <p class="mt-2 text-sm text-ivory/70 leading-relaxed">
                        {{ __('For part searches, use the console directly — you will get results in seconds.') }}
                    </p>
                    <a href="{{ route('frontend.search.console', ['lang' => $lang]) }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 bg-amber text-ink
                              font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                              hover:bg-paper transition-colors">
                        {{ __('Open search') }}
                        <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                    </a>
                </div>
            </aside>
        </div>

    </div>
</div>

@push('scripts')
<script>
function contactForm() {
    const submitUrl    = @json(route('frontend.contact.submit', ['lang' => $lang]));
    const csrfToken    = document.querySelector('meta[name="csrf-token"]')?.content || '';

    return {
        form: {
            name: '',
            email: '',
            subject_type: '',
            order_number: '',
            oem_number: '',
            manufacturer: '',
            car_model: '',
            year: '',
            vin_number: '',
            message: '',
            website: '',
        },
        submitting: false,
        successMsg: '',
        errorMsg: '',
        fieldErrors: {},

        get needsOrder() {
            return ['order_issue', 'shipping_question', 'return_refund'].includes(this.form.subject_type);
        },

        resetMsgs() {
            this.successMsg = '';
            this.errorMsg = '';
            this.fieldErrors = {};
        },

        async submitForm() {
            this.resetMsgs();
            this.submitting = true;
            try {
                const honeypotData = {};
                document.querySelectorAll('[name^="my_name"], [name="valid_from"]').forEach(el => {
                    honeypotData[el.name] = el.value;
                });
                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ ...this.form, ...honeypotData }),
                });
                const data = await res.json();
                if (res.ok && data.success) {
                    this.successMsg = data.message || @json(trans('contact.sent_success'));
                    this.form = {
                        name: '', email: '', subject_type: '',
                        order_number: '', oem_number: '', manufacturer: '',
                        car_model: '', year: '', vin_number: '', message: '', website: '',
                    };
                    window.dispatchEvent(new CustomEvent('toast', { detail: { message: this.successMsg, type: 'success' } }));
                } else {
                    if (data.errors) { this.fieldErrors = Object.fromEntries(Object.entries(data.errors).map(([k,v]) => [k, Array.isArray(v) ? v[0] : v])); }
                    this.errorMsg = data.message || @json(trans('contact.sent_failed'));
                }
            } catch (e) {
                this.errorMsg = 'Network error. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endpush
@endsection
