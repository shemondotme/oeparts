@extends('emails.layout')

@php
    $mt = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, $locale);
    $typeName = $mt('name_invoice');
@endphp

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    {{ $mt('reminder_eyebrow') }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ $typeName }} {{ $invoice->invoice_number }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ $mt('mail_hello', ['name' => $invoice->client_name]) }}<br>
                    {{ $mt('reminder_intro') }}@if($daysOverdue > 0) {{ trans_choice('invoice_doc.reminder_late', $daysOverdue, ['count' => $daysOverdue], $locale) }}@endif.
                    {{ $mt('reminder_ignore') }}
                </p>
            </td>
        </tr>

        <tr>
            <td style="padding: 24px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border: 1px solid #D8CFB6; background-color: #F7F3E7;">
                    <tr>
                        <td style="padding: 20px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">{{ $mt('mail_no', ['type' => mb_strtoupper($typeName)]) }}</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">{{ $invoice->invoice_number }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper($mt('due')) }}</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ $invoice->due_date->format('d/m/Y') }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="spec-label" style="color: #4E5A74;">{{ $mt('reminder_outstanding') }}</span></td>
                                    <td align="right" style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">{{ format_price($balance, $invoice->currency, $locale) }}</span></td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        @if(!empty($bank))
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 10px 0; color: #4E5A74;">{{ $mt('mail_pay_bank') }}</p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size: 14px; color: #0A1228;">
                    @if(!empty($bank['account_holder']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('account_holder') }}</td><td align="right">{{ $bank['account_holder'] }}</td></tr>
                    @endif
                    @if(!empty($bank['bank_name']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('bank') }}</td><td align="right">{{ $bank['bank_name'] }}</td></tr>
                    @endif
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('iban') }}</td><td align="right" class="font-mono">{{ $bank['iban'] }}</td></tr>
                    @if(!empty($bank['bic']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('swift') }}</td><td align="right" class="font-mono">{{ $bank['bic'] }}</td></tr>
                    @endif
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('payment_reference') }}</td><td align="right" class="font-mono">{{ $invoice->invoice_number }}</td></tr>
                </table>
            </td>
        </tr>
        @endif

        @if($invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 10px 0; color: #4E5A74;">{{ $mt('mail_pay_online') }}</p>
                <p style="margin: 0; font-size: 14px;"><a href="{{ $invoice->payment_link_url }}" style="color: #9A5A00;">{{ $invoice->payment_link_url }}</a></p>
            </td>
        </tr>
        @endif

        <tr>
            <td style="font-size: 14px; line-height: 21px; color: #4E5A74;">
                {{ $mt('reminder_attached_again', ['email' => settings('company.email', 'info@oeparts.lt')]) }}
            </td>
        </tr>
    </table>
@endsection
