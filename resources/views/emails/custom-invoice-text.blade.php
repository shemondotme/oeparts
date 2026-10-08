Invoice {{ $invoice->invoice_number }}

Hello {{ $invoice->client_name }},

Please find your invoice attached as a PDF.

Invoice no.: {{ $invoice->invoice_number }}
Due date: {{ $invoice->due_date->format('d/m/Y') }}
Amount due: {{ format_price($invoice->total, $invoice->currency, 'en') }}
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

Questions? Contact {{ settings('company.email', 'info@oeparts.lt') }}.

---
{{ config('app.url') }}
