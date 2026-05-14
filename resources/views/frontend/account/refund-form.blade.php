@extends('layouts.app')

@section('title', __('Request Refund — Order :number', ['number' => $order->order_number]) . ' — ' . settings('general.site_name', 'OEMHub'))

@section('meta_robots')<meta name="robots" content="noindex, nofollow">@endsection

@php $lang = app()->getLocale(); @endphp

@section('content')
<x-account.shell
    active="refunds"
    eyebrow="§ Refund · Request · Form"
    :title="'Request a refund'"
    :subtitle="__('Open a refund case against order :number. Our team will review and respond within 2 business days.', ['number' => $order->order_number])"
    :docId="'DOC · REFUND-REQUEST · ' . $order->order_number"
    :breadcrumb="[
        ['label' => 'Orders', 'href' => route('frontend.account.orders', ['lang' => $lang])],
        ['label' => $order->order_number, 'href' => route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order])],
        ['label' => 'Refund'],
    ]"
>
    <x-slot name="actions">
        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 border border-ivory/20 text-ivory
                  font-mono text-[11px] font-bold tracking-[0.22em] uppercase
                  hover:border-amber hover:text-amber transition-colors">
            <x-heroicon-s-arrow-long-left class="w-4 h-4" />
            Back to order
        </a>
    </x-slot>

    @if($errors->any())
        <div class="mb-6 border border-red-600 bg-red-50 p-5"
             style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 border border-red-600 bg-paper flex items-center justify-center shrink-0">
                    <x-heroicon-s-exclamation-triangle class="w-4 h-4 text-red-600" />
                </div>
                <div class="flex-1">
                    <p class="bp-spec text-red-700 mb-1">§ Validation · Error</p>
                    <ul class="text-sm text-red-800 space-y-0.5 list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-12 gap-x-4 sm:gap-x-6 gap-y-6 items-start">

        {{-- ── Left: Form ──────────────────────────────────────────── --}}
        <div class="col-span-12 lg:col-span-8">
            <section class="border border-ink bg-paper" style="box-shadow: 6px 6px 0 rgba(20,22,29,1);">
                <header class="flex items-center justify-between px-5 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-document-text class="w-3.5 h-3.5" />
                        § Refund · Details
                    </span>
                    <span class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">* = required</span>
                </header>

                <form method="POST"
                      action="{{ route('frontend.account.order.refund.submit', ['lang' => $lang, 'order' => $order]) }}"
                      enctype="multipart/form-data"
                      class="p-6 sm:p-8 space-y-6">
                    @csrf
                    <input type="text" name="website" class="hidden" tabindex="-1" autocomplete="off">

                    <fieldset>
                        <legend class="bp-spec text-amber-ink mb-3">§ 01 · Reason</legend>
                        <label for="reason" class="bp-spec block mb-2 text-ink">
                            Reason for refund <span class="text-red-600">*</span>
                        </label>
                        <textarea id="reason" name="reason" rows="6" required minlength="20" maxlength="2000"
                                  placeholder="{{ __('Describe why you are requesting a refund (minimum 20 characters).') }}"
                                  class="bp-input w-full resize-y">{{ old('reason') }}</textarea>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                                Minimum 20 · Maximum 2000 characters
                            </p>
                        </div>
                    </fieldset>

                    <div class="border-t border-dotted border-rule-strong"></div>

                    <fieldset>
                        <legend class="bp-spec text-amber-ink mb-3">§ 02 · Evidence</legend>
                        <label for="return_images" class="bp-spec block mb-2 text-ink">
                            Photo evidence
                            <span class="text-ink-muted/80 normal-case tracking-normal font-normal ml-1">(optional, up to 5 images)</span>
                        </label>
                        <input type="file" id="return_images" name="return_images[]" multiple
                               accept="image/jpeg,image/png"
                               class="block w-full text-sm text-body font-mono
                                      file:mr-4 file:py-2.5 file:px-4 file:border-0 file:border-r file:border-ink
                                      file:bg-ink file:text-ivory file:font-mono file:text-[10px] file:font-bold file:uppercase file:tracking-[0.22em]
                                      file:cursor-pointer hover:file:bg-amber hover:file:text-ink transition-colors
                                      border border-ink bg-paper">
                        <p class="mt-2 font-mono text-[10px] tracking-[0.18em] uppercase text-ink-muted">
                            JPEG or PNG · max 2 MB each · up to 5 photos
                        </p>
                    </fieldset>

                    <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-6 border-t border-ink">
                        <a href="{{ route('frontend.account.order.detail', ['lang' => $lang, 'order' => $order]) }}"
                           class="bp-btn-outline justify-center sm:justify-start">
                            <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="bp-btn-primary justify-center flex-1 sm:flex-none">
                            <x-heroicon-s-paper-airplane class="w-5 h-5" />
                            <span>{{ __('Submit refund request') }}</span>
                            <x-heroicon-s-arrow-long-right class="w-5 h-5" />
                        </button>
                    </div>
                </form>
            </section>
        </div>

        {{-- ── Right: Order summary ─────────────────────────────────── --}}
        <aside class="col-span-12 lg:col-span-4 lg:sticky lg:top-10 lg:h-fit">
            <div class="border border-ink bg-paper" style="box-shadow: 4px 4px 0 rgba(20,22,29,1);">
                <header class="flex items-center justify-between px-4 py-3 border-b border-ink bg-ivory-alt">
                    <span class="bp-spec text-amber-ink flex items-center gap-2">
                        <x-heroicon-o-receipt-refund class="w-3.5 h-3.5" />
                        § Order · Summary
                    </span>
                </header>
                <dl class="p-4 space-y-2">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Order no.</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-xs font-bold text-ink tabular-nums">{{ $order->order_number }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Placed</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-xs font-bold text-ink tabular-nums">{{ $order->created_at->format('Y-m-d') }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="font-mono text-[10px] tracking-[0.22em] uppercase text-ink-muted">Status</dt>
                        <span class="flex-1 border-b border-dotted border-rule-strong translate-y-[-4px]"></span>
                        <dd class="font-mono text-xs font-bold text-ink">{{ $order->status->label() }}</dd>
                    </div>
                </dl>
                <div class="px-4 py-4 border-t-2 border-ink flex items-end justify-between gap-3">
                    <div>
                        <p class="font-mono text-[10px] font-bold tracking-[0.22em] uppercase text-ink">Order total</p>
                        <p class="font-mono text-[9px] tracking-[0.2em] uppercase text-ink-muted mt-1">EUR · incl. VAT</p>
                    </div>
                    <p class="font-mono text-2xl font-medium text-ink tabular-nums leading-none tracking-tight">
                        €{{ number_format((float) $order->grand_total, 2) }}
                    </p>
                </div>
            </div>

            {{-- Policy note --}}
            <div class="mt-4 border border-rule bg-ivory-alt p-4">
                <p class="bp-spec text-amber-ink mb-1.5">§ Refund · Policy</p>
                <p class="text-xs text-ink-muted leading-relaxed">
                    Requests are reviewed within 2 business days. Approved refunds are settled via the original payment method within 5–10 business days.
                </p>
            </div>
        </aside>
    </div>
</x-account.shell>
@endsection
