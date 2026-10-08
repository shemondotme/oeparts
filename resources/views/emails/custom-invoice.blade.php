@extends('emails.layout')

@section('content')
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td style="padding-bottom: 24px; border-bottom: 1px solid #D8CFB6;">
                <p class="spec-label" style="margin: 0 0 8px 0; color: #9A5A00;">
                    FINANCE · INVOICE
                </p>
                <h2 class="font-display" style="margin: 0; font-size: 24px; line-height: 32px; color: #0A1228;">
                    Invoice {{ $invoice->invoice_number }}<span class="text-amber">.</span>
                </h2>
                <p style="margin: 12px 0 0 0; font-size: 15px; line-height: 22px; color: #4E5A74;">
                    Hello {{ $invoice->client_name }},<br>
                    Please find your invoice attached as a PDF. A summary is below.
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
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">INVOICE NO.</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228; font-weight: bold;">{{ $invoice->invoice_number }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-bottom: 12px;"><span class="spec-label" style="color: #4E5A74;">DUE DATE</span></td>
                                    <td align="right" style="padding-bottom: 12px;"><span class="font-mono" style="font-size: 14px; color: #0A1228;">{{ $invoice->due_date->format('d/m/Y') }}</span></td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="spec-label" style="color: #4E5A74;">AMOUNT DUE</span></td>
                                    <td align="right" style="padding-top: 12px; border-top: 1px dashed #D8CFB6;"><span class="font-mono" style="font-size: 18px; color: #0A1228; font-weight: bold;">{{ format_price($invoice->total, $invoice->currency, 'en') }}</span></td>
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
                <p class="spec-label" style="margin: 0 0 10px 0; color: #4E5A74;">PAY BY BANK TRANSFER</p>
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size: 14px; color: #0A1228;">
                    @if(!empty($bank['account_holder']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">Account holder</td><td align="right">{{ $bank['account_holder'] }}</td></tr>
                    @endif
                    @if(!empty($bank['bank_name']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">Bank</td><td align="right">{{ $bank['bank_name'] }}</td></tr>
                    @endif
                    <tr><td style="padding: 3px 0; color: #4E5A74;">IBAN</td><td align="right" class="font-mono">{{ $bank['iban'] }}</td></tr>
                    @if(!empty($bank['bic']))
                    <tr><td style="padding: 3px 0; color: #4E5A74;">SWIFT / BIC</td><td align="right" class="font-mono">{{ $bank['bic'] }}</td></tr>
                    @endif
                    <tr><td style="padding: 3px 0; color: #4E5A74;">Payment reference</td><td align="right" class="font-mono">{{ $invoice->invoice_number }}</td></tr>
                </table>
            </td>
        </tr>
        @endif

        <tr>
            <td style="font-size: 14px; line-height: 21px; color: #4E5A74;">
                Questions about this invoice? Just reply to this email or contact {{ settings('company.email', 'info@oeparts.lt') }}.
            </td>
        </tr>
    </table>
@endsection
