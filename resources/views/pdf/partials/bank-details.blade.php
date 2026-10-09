{{-- Bank transfer instructions. $bank comes from InvoiceService::bankDetails()
     (null when no IBAN is configured, so the block is simply omitted).
     $paymentReference is what the client must put on the transfer.
     $docLocale picks the language of the labels: a custom document passes its own;
     order invoices pass nothing and stay English, like the rest of that PDF. --}}
@php
    $bt = fn (string $key) => __('invoice_doc.'.$key, [], $docLocale ?? 'en');
    $pairs = array_values(array_filter([
        [$bt('account_holder'), $bank['account_holder'] ?? '', false],
        [$bt('bank'), $bank['bank_name'] ?? '', false],
        [$bt('iban'), $bank['iban'] ?? '', true],
        [$bt('swift'), $bank['bic'] ?? '', true],
        [$bt('intermediary'), $bank['intermediary_bank'] ?? '', false],
        [$bt('payment_reference'), $paymentReference ?? '', true],
    ], fn ($pair) => filled($pair[1])));
@endphp
@if(!empty($bank))
<table class="pay">
    <tr class="pay-head"><td colspan="2">{{ $bt('payment_bank') }}</td></tr>
    @foreach(array_chunk($pairs, 2) as $row)
    <tr>
        @foreach($row as [$pairLabel, $pairValue, $isMono])
        <td style="width: 50%;">
            <div class="label">{{ $pairLabel }}</div>
            <div class="{{ $isMono ? 'value' : '' }}">{!! nl2br(e($pairValue)) !!}</div>
        </td>
        @endforeach
        @if(count($row) === 1)<td></td>@endif
    </tr>
    @endforeach
    @if(!empty($bank['instructions']))
    <tr><td colspan="2" class="pay-note" style="color: #374151;">{!! nl2br(e($bank['instructions'])) !!}</td></tr>
    @endif
    <tr><td colspan="2" class="pay-note">{{ $bt('quote_reference') }}</td></tr>
</table>
@endif
