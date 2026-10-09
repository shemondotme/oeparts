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
@if(!empty($bank['intermediary_bank']))Intermediary bank: {{ $bank['intermediary_bank'] }}
@endif
Payment reference: {{ $invoice->invoice_number }}
@endif
@if($invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)

Pay online: {{ $invoice->payment_link_url }}
@endif
@if($invoice->payment_method !== \App\Enums\InvoicePaymentMethod::None && filled($invoice->payment_instructions))

{{ $invoice->payment_instructions }}
@endif

Questions? Contact {{ settings('company.email', 'info@oeparts.lt') }}.

---
{{ config('app.url') }}
