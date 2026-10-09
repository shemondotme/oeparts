{{-- Bank transfer instructions. $bank comes from InvoiceService::bankDetails()
     (null when no IBAN is configured, so the block is simply omitted).
     $paymentReference is what the client must put on the transfer.
     $docLocale picks the language of the labels: a custom document passes its own;
     order invoices pass nothing and stay English, like the rest of that PDF. --}}
@php $bt = fn (string $key) => __('invoice_doc.'.$key, [], $docLocale ?? 'en'); @endphp
@if(!empty($bank))
<div class="section" style="margin-top: 18px;">
    <div class="section-title">{{ $bt('payment_bank') }}</div>
    <table>
        <tbody>
            @if(!empty($bank['account_holder']))
            <tr><td style="width: 32%;"><strong>{{ $bt('account_holder') }}</strong></td><td>{{ $bank['account_holder'] }}</td></tr>
            @endif
            @if(!empty($bank['bank_name']))
            <tr><td style="width: 32%;"><strong>{{ $bt('bank') }}</strong></td><td>{{ $bank['bank_name'] }}</td></tr>
            @endif
            <tr><td style="width: 32%;"><strong>{{ $bt('iban') }}</strong></td><td class="mono">{{ $bank['iban'] }}</td></tr>
            @if(!empty($bank['bic']))
            <tr><td style="width: 32%;"><strong>{{ $bt('swift') }}</strong></td><td class="mono">{{ $bank['bic'] }}</td></tr>
            @endif
            @if(!empty($bank['intermediary_bank']))
            <tr><td style="width: 32%;"><strong>{{ $bt('intermediary') }}</strong></td><td>{!! nl2br(e($bank['intermediary_bank'])) !!}</td></tr>
            @endif
            <tr><td style="width: 32%;"><strong>{{ $bt('payment_reference') }}</strong></td><td class="mono">{{ $paymentReference }}</td></tr>
        </tbody>
    </table>
    @if(!empty($bank['instructions']))
    <div style="margin-top: 6px; font-size: 10px; color: #374151;">{!! nl2br(e($bank['instructions'])) !!}</div>
    @endif
    <div style="margin-top: 6px; font-size: 10px; color: #6B7280;">{{ $bt('quote_reference') }}</div>
</div>
@endif
