Payment reminder: invoice {{ $invoice->invoice_number }}

Hello {{ $invoice->client_name }},

This is a friendly reminder that the invoice below is still open@if($daysOverdue > 0) (it was due {{ $daysOverdue }} {{ $daysOverdue === 1 ? 'day' : 'days' }} ago)@endif.
If you have already paid it, please ignore this message and accept our thanks.

Invoice no.: {{ $invoice->invoice_number }}
Due date: {{ $invoice->due_date->format('d/m/Y') }}
Outstanding: {{ format_price($balance, $invoice->currency, 'en') }}
@if(!empty($bank))

Pay by bank transfer
@if(!empty($bank['account_holder']))Account holder: {{ $bank['account_holder'] }}
@endif
@if(!empty($bank['bank_name']))Bank: {{ $bank['bank_name'] }}
@endif
IBAN: {{ $bank['iban'] }}
@if(!empty($bank['bic']))SWIFT / BIC: {{ $bank['bic'] }}
@endif
Payment reference: {{ $invoice->invoice_number }}
@endif
@if($invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)

Pay online: {{ $invoice->payment_link_url }}
@endif

The invoice is attached again as a PDF. Questions? Contact {{ settings('company.email', 'info@oeparts.lt') }}.

---
{{ config('app.url') }}
