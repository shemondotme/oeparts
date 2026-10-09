{{ $documentType->getLabel() }} {{ $invoice->invoice_number }}

Hello {{ $invoice->client_name }},

Please find your {{ strtolower($documentType->getLabel()) }} attached as a PDF.

{{ $documentType->getLabel() }} no.: {{ $invoice->invoice_number }}
{{ $documentType->dueLabel() }}: {{ $invoice->due_date->format('d/m/Y') }}
{{ $documentType->isCredit() ? 'Credit amount' : ($documentType->requestsPayment() ? 'Amount due' : 'Total') }}: {{ format_price($invoice->total, $invoice->currency, 'en') }}
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
@if($documentType->requestsPayment() && $invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)

Pay online: {{ $invoice->payment_link_url }}
@endif
@if($documentType->requestsPayment() && $invoice->payment_method !== \App\Enums\InvoicePaymentMethod::None && filled($invoice->payment_instructions))

{{ $invoice->payment_instructions }}
@endif

Questions? Contact {{ settings('company.email', 'info@oeparts.lt') }}.

---
{{ config('app.url') }}
