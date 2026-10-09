{{-- "How to pay" block of a custom document. $invoice->payment_method decides what is printed;
     $bank is only set (by CustomInvoiceService::viewData) for the bank-transfer method;
     $docLocale is the language the document is written in. --}}
@php
    $method = $invoice->payment_method instanceof \BackedEnum ? $invoice->payment_method->value : (string) $invoice->payment_method;
    $isPaid = $invoice->status === \App\Enums\CustomInvoiceStatus::Paid;
    $pt = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, $docLocale ?? 'en');
@endphp
@if(! $isPaid && $method !== 'none')
    @if($method === 'bank_transfer')
        @include('pdf.partials.bank-details', ['bank' => $bank ?? null, 'paymentReference' => $invoice->invoice_number, 'docLocale' => $docLocale ?? 'en'])
    @elseif($method === 'payment_link' && $invoice->payment_link_url)
        <div class="section" style="margin-top: 18px;">
            <div class="section-title">{{ $pt('payment_online') }}</div>
            <div>{{ $pt('pay_online_text') }}</div>
            <div class="mono" style="margin-top: 4px; word-break: break-all;">{{ $invoice->payment_link_url }}</div>
            <div style="margin-top: 6px; font-size: 10px; color: #6B7280;">{{ $pt('pay_online_ref', ['number' => $invoice->invoice_number]) }}</div>
        </div>
    @elseif($method === 'cash')
        <div class="section" style="margin-top: 18px;">
            <div class="section-title">{{ $pt('payment_cash') }}</div>
            <div>{{ $pt('cash_text', ['number' => $invoice->invoice_number]) }}</div>
        </div>
    @endif

    @if(filled($invoice->payment_instructions))
        <div class="notice-box" style="margin-top: 12px;">
            {!! nl2br(e($invoice->payment_instructions)) !!}
        </div>
    @endif
@endif
