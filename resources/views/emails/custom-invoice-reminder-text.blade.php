@php
    $mt = fn (string $key, array $replace = []) => __('invoice_doc.'.$key, $replace, $locale);
@endphp
{{ $mt('name_invoice') }} {{ $invoice->invoice_number }}

{{ $mt('mail_hello', ['name' => $invoice->client_name]) }}

{{ $mt('reminder_intro') }}@if($daysOverdue > 0) {{ trans_choice('invoice_doc.reminder_late', $daysOverdue, ['count' => $daysOverdue], $locale) }}@endif.
{{ $mt('reminder_ignore') }}

{{ $mt('name_invoice') }} {{ $mt('number') }}: {{ $invoice->invoice_number }}
{{ $mt('due') }}: {{ $invoice->due_date->format('d/m/Y') }}
{{ ucfirst(mb_strtolower($mt('reminder_outstanding'))) }}: {{ format_price($balance, $invoice->currency, $locale) }}
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
{{ $mt('payment_reference') }}: {{ $invoice->invoice_number }}
@endif
@if($invoice->payment_method === \App\Enums\InvoicePaymentMethod::PaymentLink && $invoice->payment_link_url)

{{ ucfirst(mb_strtolower($mt('mail_pay_online'))) }}: {{ $invoice->payment_link_url }}
@endif

{{ $mt('reminder_attached_again', ['email' => company_contact_email()]) }}

---
{{ config('app.url') }}
