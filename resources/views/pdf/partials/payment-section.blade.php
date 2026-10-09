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
        <table class="pay">
            <tr class="pay-head"><td>{{ $pt('payment_online') }}</td></tr>
            <tr><td>{{ $pt('pay_online_text') }}<div class="value" style="margin-top: 4px; word-break: break-all;">{{ $invoice->payment_link_url }}</div></td></tr>
            <tr><td class="pay-note">{{ $pt('pay_online_ref', ['number' => $invoice->invoice_number]) }}</td></tr>
        </table>
    @elseif($method === 'cash')
        <table class="pay">
            <tr class="pay-head"><td>{{ $pt('payment_cash') }}</td></tr>
            <tr><td>{{ $pt('cash_text', ['number' => $invoice->invoice_number]) }}</td></tr>
        </table>
    @endif

    @if(filled($invoice->payment_instructions))
        <div class="notice-box" style="margin-top: 12px;">
            {!! nl2br(e($invoice->payment_instructions)) !!}
        </div>
    @endif
@endif
