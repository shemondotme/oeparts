{{-- "How to pay" block of a custom invoice. $invoice->payment_method decides what is printed;
     $bank is only set (by CustomInvoiceService::viewData) for the bank-transfer method. --}}
@php
    $method = $invoice->payment_method instanceof \BackedEnum ? $invoice->payment_method->value : (string) $invoice->payment_method;
    $isPaid = $invoice->status === \App\Enums\CustomInvoiceStatus::Paid;
@endphp
@if(! $isPaid && $method !== 'none')
    @if($method === 'bank_transfer')
        @include('pdf.partials.bank-details', ['bank' => $bank ?? null, 'paymentReference' => $invoice->invoice_number])
    @elseif($method === 'payment_link' && $invoice->payment_link_url)
        <div class="section" style="margin-top: 18px;">
            <div class="section-title">Payment Details · Pay Online</div>
            <div>Pay securely online with a card or bank at:</div>
            <div class="mono" style="margin-top: 4px; word-break: break-all;">{{ $invoice->payment_link_url }}</div>
            <div style="margin-top: 6px; font-size: 10px; color: #6B7280;">Please quote invoice {{ $invoice->invoice_number }} if the page asks for a reference.</div>
        </div>
    @elseif($method === 'cash')
        <div class="section" style="margin-top: 18px;">
            <div class="section-title">Payment Details · Cash</div>
            <div>Payable in cash on delivery or pickup. Please quote invoice {{ $invoice->invoice_number }}.</div>
        </div>
    @endif

    @if(filled($invoice->payment_instructions))
        <div class="notice-box" style="margin-top: 12px;">
            {!! nl2br(e($invoice->payment_instructions)) !!}
        </div>
    @endif
@endif
