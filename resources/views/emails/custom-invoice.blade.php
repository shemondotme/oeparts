@extends('emails.layout')

@php
    // Every label in the document's own language ($locale), not the site's.
    $mt = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, $locale);
    $typeName = $mt('name_'.$documentType->value);
    $dueKey = match ($documentType) {
        \App\Enums\InvoiceDocumentType::Quote => 'valid_until',
        \App\Enums\InvoiceDocumentType::Proforma => 'pay_before',
        default => 'due',
    };
@endphp

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    {{ $mt('mail_eyebrow', ['type' => mb_strtoupper($typeName)]) }}
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    {{ $typeName }} {{ $invoice->invoice_number }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    {{ $mt('mail_hello', ['name' => $invoice->client_name]) }}<br>
                    {{ $mt('mail_attached', ['type' => mb_strtolower($typeName)]) }}
                </p>
            </td>
        </tr>

        @if(filled($customMessage ?? null))
        <tr>
            <td style="padding: 20px 0 0 0;">
                <div style="padding: 14px 16px; border-left: 3px solid #F59E0B; background-color: #FBF7EA; font-size: 15px; line-height: 22px; color: #0A1228;">
                    {!! nl2br(e($customMessage)) !!}
                </div>
            </td>
        </tr>
        @endif

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
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">{{ mb_strtoupper($mt($dueKey)) }}</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ $invoice->due_date->format('d/m/Y') }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="spec-label" style="color: #4E5A74;">{{ $documentType->isCredit() ? $mt('mail_credit_amount') : ($documentType->requestsPayment() ? $mt('mail_amount_due') : $mt('mail_total')) }}</span></td>
                                    <td align="right" style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">{{ format_price($invoice->total, $invoice->currency, $locale) }}</span></td>
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
                    @if(!empty($bank['intermediary_bank']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('intermediary') }}</td><td align="right">{!! nl2br(e($bank['intermediary_bank'])) !!}</td></tr>
                    @endif
                    <tr><td style="padding: 3px 0; color: #4E5A74;">{{ $mt('payment_reference') }}</td><td align="right" class="font-mono">{{ $invoice->invoice_number }}</td></tr>
                </table>
            </td>
        </tr>
        @endif

        @if($documentType->requestsPayment() && $invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)
        <tr>
            <td style="padding-bottom: 24px;">
                <p class="spec-label" style="margin: 0 0 10px 0; color: #4E5A74;">{{ $mt('mail_pay_online') }}</p>
                <p style="margin: 0; font-size: 14px;"><a href="{{ $invoice->payment_link_url }}" style="color: #9A5A00;">{{ $invoice->payment_link_url }}</a></p>
            </td>
        </tr>
        @endif

        @if($documentType->requestsPayment() && $invoice->payment_method !== \App\Enums\InvoicePaymentMethod::None && filled($invoice->payment_instructions))
        <tr>
            <td style="padding-bottom: 24px; font-size: 14px; line-height: 21px; color: #0A1228;">
                {!! nl2br(e($invoice->payment_instructions)) !!}
            </td>
        </tr>
        @endif

        <tr>
            <td style="font-size: 14px; line-height: 21px; color: #4E5A74;">
                {{ $mt('mail_questions', ['email' => company_contact_email()]) }}
            </td>
        </tr>
    </table>
@endsection
