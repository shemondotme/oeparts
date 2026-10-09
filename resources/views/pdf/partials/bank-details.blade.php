{{-- Bank transfer instructions. $bank comes from InvoiceService::bankDetails()
     (null when no IBAN is configured, so the block is simply omitted).
     $paymentReference is what the client must put on the transfer. --}}
@if(!empty($bank))
<div class="section" style="margin-top: 18px;">
    <div class="section-title">Payment Details · Bank Transfer</div>
    <table>
        <tbody>
            @if(!empty($bank['account_holder']))
            <tr><td style="width: 32%;"><strong>Account holder</strong></td><td>{{ $bank['account_holder'] }}</td></tr>
            @endif
            @if(!empty($bank['bank_name']))
            <tr><td style="width: 32%;"><strong>Bank</strong></td><td>{{ $bank['bank_name'] }}</td></tr>
            @endif
            <tr><td style="width: 32%;"><strong>IBAN</strong></td><td class="mono">{{ $bank['iban'] }}</td></tr>
            @if(!empty($bank['bic']))
            <tr><td style="width: 32%;"><strong>SWIFT / BIC</strong></td><td class="mono">{{ $bank['bic'] }}</td></tr>
            @endif
            @if(!empty($bank['intermediary_bank']))
            <tr><td style="width: 32%;"><strong>Intermediary bank</strong></td><td>{!! nl2br(e($bank['intermediary_bank'])) !!}</td></tr>
            @endif
            <tr><td style="width: 32%;"><strong>Payment reference</strong></td><td class="mono">{{ $paymentReference }}</td></tr>
        </tbody>
    </table>
    @if(!empty($bank['instructions']))
    <div style="margin-top: 6px; font-size: 10px; color: #374151;">{!! nl2br(e($bank['instructions'])) !!}</div>
    @endif
    <div style="margin-top: 6px; font-size: 10px; color: #6B7280;">Please quote the payment reference exactly so your transfer can be matched to this invoice. Bank charges are borne by the payer.</div>
</div>
@endif
