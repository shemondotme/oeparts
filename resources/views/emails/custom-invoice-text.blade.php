@php
    $mt = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, $locale);
    $typeName = $mt('name_'.$documentType->value);
    $dueKey = match ($documentType) {
        \App\Enums\InvoiceDocumentType::Quote => 'valid_until',
        \App\Enums\InvoiceDocumentType::Proforma => 'pay_before',
        default => 'due',
    };
@endphp
{{ $typeName }} {{ $invoice->invoice_number }}

{{ $mt('mail_hello', ['name' => $invoice->client_name]) }}

{{ $mt('mail_attached', ['type' => mb_strtolower($typeName)]) }}
@if(filled($customMessage ?? null))

{{ $customMessage }}
@endif

{{ $typeName }} {{ $mt('number') }}: {{ $invoice->invoice_number }}
{{ $mt($dueKey) }}: {{ $invoice->due_date->format('d/m/Y') }}
{{ ucfirst(mb_strtolower($documentType->isCredit() ? $mt('mail_credit_amount') : ($documentType->requestsPayment() ? $mt('mail_amount_due') : $mt('mail_total')))) }}: {{ format_price($invoice->total, $invoice->currency, $locale) }}
@if(!empty($bank))

{{ ucfirst(mb_strtolower($mt('mail_pay_bank'))) }}
@if(!empty($bank['account_holder']))
{{ $mt('account_holder') }}: {{ $bank['account_holder'] }}
@endif
@if(!empty($bank['bank_name']))
{{ $mt('bank') }}: {{ $bank['bank_name'] }}
@endif
{{ $mt('iban') }}: {{ $bank['iban'] }}
@if(!empty($bank['bic']))
{{ $mt('swift') }}: {{ $bank['bic'] }}
@endif
@if(!empty($bank['intermediary_bank']))
{{ $mt('intermediary') }}: {{ $bank['intermediary_bank'] }}
@endif
{{ $mt('payment_reference') }}: {{ $invoice->invoice_number }}
@endif
@if($documentType->requestsPayment() && $invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)

{{ ucfirst(mb_strtolower($mt('mail_pay_online'))) }}: {{ $invoice->payment_link_url }}
@endif
@if($documentType->requestsPayment() && $invoice->payment_method !== \App\Enums\InvoicePaymentMethod::None && filled($invoice->payment_instructions))

{{ $invoice->payment_instructions }}
@endif

{{ $mt('mail_questions', ['email' => settings('company.email', 'info@oeparts.lt')]) }}

---
{{ config('app.url') }}
