<?php

namespace App\Services;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentMethod;
use App\Mail\CustomInvoiceMail;
use App\Mail\CustomInvoiceReminderMail;
use App\Models\CustomInvoice;
use App\Models\CustomInvoicePayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Stand-alone documents (quotation, proforma, invoice, credit note): numbering,
 * conversion between them, PDF rendering and emailing the client.
 * Order invoices stay in InvoiceService; both share the same bank-details block.
 */
class CustomInvoiceService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Next number from the type's own series (invoices share the order-invoice run).
     */
    public function nextNumber(InvoiceDocumentType $type = InvoiceDocumentType::Invoice): string
    {
        return $this->sequences->nextDocumentNumber($type);
    }

    /** Languages a document can be written in (the site's own locales). */
    public const LANGUAGES = ['en', 'de', 'es', 'fr', 'lt'];

    /** The language this document is written in; anything unknown falls back to English. */
    public function languageOf(CustomInvoice $invoice): string
    {
        return in_array($invoice->language, self::LANGUAGES, true) ? (string) $invoice->language : 'en';
    }

    /** The document's HTML, rendered in its own language. */
    public function html(CustomInvoice $invoice): string
    {
        // The app locale is switched only while this one document renders (settings_trans() and
        // anything else locale-aware follow it), then put back, even if rendering throws.
        $previous = App::getLocale();
        App::setLocale($this->languageOf($invoice));

        try {
            return view('pdf.custom-invoice', $this->viewData($invoice))->render();
        } finally {
            App::setLocale($previous);
        }
    }

    public function pdf(CustomInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHTML($this->html($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(CustomInvoice $invoice): array
    {
        $documentType = $invoice->document_type;
        $locale = $this->languageOf($invoice);

        return [
            'invoice' => $invoice,
            'documentType' => $documentType,
            'locale' => $locale,
            // Label lookup in the document's language: {{ $t('subtotal') }}
            't' => fn (string $key, array $replace = []): string => __('invoice_doc.'.$key, $replace, $locale),
            'items' => $invoice->normalizedItems(),
            'bank' => $documentType->requestsPayment() && $invoice->payment_method === InvoicePaymentMethod::BankTransfer
                ? $this->invoices->bankDetailsFor($invoice->bank_account_id, $invoice->currency)
                : null,
            'settings' => [
                'company_name' => settings('company.name', 'OeParts'),
                'company_address' => settings('company.address', ''),
                'company_vat' => settings('company.vat_number', ''),
                'company_registration' => settings('company.registration_number', ''),
                'company_email' => settings('company.email', 'info@oeparts.lt'),
                'company_phone' => settings('company.phone', ''),
            ],
        ];
    }

    public function filename(CustomInvoice $invoice): string
    {
        $prefix = match ($invoice->document_type) {
            InvoiceDocumentType::Quote => 'quotation',
            InvoiceDocumentType::Proforma => 'proforma',
            InvoiceDocumentType::CreditNote => 'credit-note',
            default => 'invoice',
        };

        return $prefix.'-'.$invoice->invoice_number.'.pdf';
    }

    /**
     * Email the PDF to the client. A draft becomes "sent"; re-sending a sent
     * or paid invoice just resends it (and refreshes sent_at).
     *
     * @param  array{cc?: array<int, string>, bcc?: array<int, string>, message?: ?string, copy_to_sender?: bool}  $options
     *                                                                                                                       cc / bcc: extra recipients; message: a personal note shown above the summary;
     *                                                                                                                       copy_to_sender: blind-copy the admin who sends it
     *
     * @throws \RuntimeException when the invoice has no client email or is cancelled
     */
    public function send(CustomInvoice $invoice, array $options = []): void
    {
        if ($invoice->status === CustomInvoiceStatus::Cancelled) {
            throw new \RuntimeException('A cancelled document cannot be sent.');
        }

        if (blank($invoice->client_email)) {
            throw new \RuntimeException('This document has no client email address.');
        }

        $clean = fn (array $list): array => array_values(array_unique(array_filter(array_map('trim', $list), fn (string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)));
        $cc = $clean((array) ($options['cc'] ?? []));
        $bcc = $clean((array) ($options['bcc'] ?? []));
        if (! empty($options['copy_to_sender']) && ($self = auth('admin')->user()?->email)) {
            $bcc = $clean([...$bcc, $self]);
        }

        $pending = Mail::to($invoice->client_email);
        if ($cc !== []) {
            $pending->cc($cc);
        }
        if ($bcc !== []) {
            $pending->bcc($bcc);
        }
        $pending->send(new CustomInvoiceMail($invoice, $this->pdf($invoice)->output(), filled($options['message'] ?? null) ? trim((string) $options['message']) : null));

        $invoice->forceFill([
            'sent_at' => now(),
            'status' => $invoice->status === CustomInvoiceStatus::Draft ? CustomInvoiceStatus::Sent : $invoice->status,
        ])->save();
    }

    /** Mark the whole remaining balance as received today (a payment record is kept). */
    public function markPaid(CustomInvoice $invoice): void
    {
        $balance = $invoice->balanceDue();

        if ($invoice->document_type->requestsPayment() && bccomp($balance, '0', 2) > 0
            && ! in_array($invoice->status, [CustomInvoiceStatus::Cancelled, CustomInvoiceStatus::Paid], true)) {
            // A draft is issued by being paid in full: it becomes "sent" first so the status follows the money.
            if ($invoice->status === CustomInvoiceStatus::Draft) {
                $invoice->forceFill(['status' => CustomInvoiceStatus::Sent])->save();
            }

            $this->recordPayment($invoice, $balance, now(), null, null, 'Marked as paid in full');

            return;
        }

        $invoice->forceFill([
            'status' => CustomInvoiceStatus::Paid,
            'paid_at' => now(),
        ])->save();
    }

    /**
     * Record a payment received against an invoice or proforma. The status follows the
     * money: partly paid while a balance remains, paid (with the date of the last payment)
     * once it is covered. Over-payments and payments on documents that are not payable are
     * refused rather than guessed at.
     *
     * @throws \RuntimeException
     */
    public function recordPayment(CustomInvoice $invoice, string|float $amount, ?\DateTimeInterface $paidOn = null, ?string $method = null, ?string $reference = null, ?string $note = null): CustomInvoicePayment
    {
        if (! $invoice->document_type->requestsPayment()) {
            throw new \RuntimeException('Payments can only be recorded on an invoice or a proforma invoice.');
        }

        if (in_array($invoice->status, [CustomInvoiceStatus::Cancelled, CustomInvoiceStatus::Paid], true)) {
            throw new \RuntimeException('This document is '.strtolower($invoice->status->getLabel()).' — no payment can be added.');
        }

        $amount = number_format((float) str_replace(',', '.', (string) $amount), 2, '.', '');
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \RuntimeException('The payment amount must be more than zero.');
        }

        if (bccomp($amount, $invoice->balanceDue(), 2) > 0) {
            throw new \RuntimeException('The payment ('.$amount.') is more than the balance due ('.$invoice->balanceDue().').');
        }

        return DB::transaction(function () use ($invoice, $amount, $paidOn, $method, $reference, $note): CustomInvoicePayment {
            /** @var CustomInvoicePayment $payment */
            $payment = $invoice->payments()->create([
                'amount' => $amount,
                'paid_on' => ($paidOn ?? now())->format('Y-m-d'),
                'method' => $method,
                'reference' => $reference,
                'note' => $note,
                'created_by' => auth('admin')->id(),
            ]);

            $this->syncPaymentStatus($invoice);

            return $payment;
        });
    }

    /** Remove a mistaken payment; the status goes back to match the money that remains. */
    public function removePayment(CustomInvoicePayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            /** @var CustomInvoice $invoice */
            $invoice = $payment->invoice;
            $payment->delete();
            $this->syncPaymentStatus($invoice->refresh());
        });
    }

    /** Draft/Sent/PartiallyPaid/Paid from the payments on file (cancelled and draft documents are left alone). */
    private function syncPaymentStatus(CustomInvoice $invoice): void
    {
        if (in_array($invoice->status, [CustomInvoiceStatus::Cancelled, CustomInvoiceStatus::Draft], true)) {
            return;
        }

        $paid = $invoice->amountPaid();
        $covered = bccomp($paid, (string) $invoice->total, 2) >= 0 && bccomp((string) $invoice->total, '0', 2) > 0;

        if ($covered) {
            $invoice->forceFill([
                'status' => CustomInvoiceStatus::Paid,
                'paid_at' => $invoice->payments()->max('paid_on') ?: now(),
            ])->save();
        } elseif (bccomp($paid, '0', 2) > 0) {
            $invoice->forceFill(['status' => CustomInvoiceStatus::PartiallyPaid, 'paid_at' => null])->save();
        } else {
            $invoice->forceFill(['status' => $invoice->sent_at ? CustomInvoiceStatus::Sent : CustomInvoiceStatus::Draft, 'paid_at' => null])->save();
        }
    }

    // ---- payment reminders ---------------------------------------------------------------------

    /** The configured days-after-due schedule ("3,10,21" -> [3, 10, 21]). */
    public function reminderSchedule(): array
    {
        // Only whole numbers count: a stray word or a minus sign is dropped, never read as day 0.
        $tokens = preg_split('/\s*,\s*/', trim((string) settings('invoice.reminder_days', '3,10,21'))) ?: [];
        $days = array_values(array_unique(array_map('intval', array_filter($tokens, fn (string $t): bool => ctype_digit($t)))));
        sort($days);

        return $days;
    }

    /** Is the next scheduled reminder due for this invoice today? */
    public function reminderIsDue(CustomInvoice $invoice): bool
    {
        if (! $invoice->isOverdue() || blank($invoice->client_email)) {
            return false;
        }

        $next = $this->reminderSchedule()[$invoice->reminder_count] ?? null;
        if ($next === null || $invoice->daysOverdue() < $next) {
            return false;
        }

        // Never twice in a day, even if the schedule has two close entries.
        return $invoice->last_reminded_at === null || $invoice->last_reminded_at->isBefore(now()->startOfDay());
    }

    /**
     * Email a friendly payment reminder (with the PDF) and count it.
     *
     * @throws \RuntimeException when there is nothing to chase or nowhere to send it
     */
    public function sendReminder(CustomInvoice $invoice): void
    {
        if (! $invoice->document_type->requestsPayment() || ! in_array($invoice->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::PartiallyPaid], true)) {
            throw new \RuntimeException('Only a sent, unpaid invoice can be chased for payment.');
        }

        if (blank($invoice->client_email)) {
            throw new \RuntimeException('This document has no client email address.');
        }

        Mail::to($invoice->client_email)->send(new CustomInvoiceReminderMail($invoice, $this->pdf($invoice)->output()));

        $invoice->forceFill([
            'reminder_count' => $invoice->reminder_count + 1,
            'last_reminded_at' => now(),
        ])->save();
    }

    /** Send every scheduled reminder that is due today; returns how many were sent. */
    public function sendDueReminders(): int
    {
        $sent = 0;

        CustomInvoice::query()
            ->where('document_type', InvoiceDocumentType::Invoice->value)
            ->whereIn('status', [CustomInvoiceStatus::Sent->value, CustomInvoiceStatus::PartiallyPaid->value])
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('id')
            ->each(function (CustomInvoice $invoice) use (&$sent): void {
                if (! $this->reminderIsDue($invoice)) {
                    return;
                }

                try {
                    $this->sendReminder($invoice);
                    $sent++;
                } catch (\Throwable $e) {
                    Log::warning('Payment reminder failed', ['invoice' => $invoice->invoice_number, 'error' => $e->getMessage()]);
                }
            });

        return $sent;
    }

    public function cancel(CustomInvoice $invoice): void
    {
        $invoice->forceFill(['status' => CustomInvoiceStatus::Cancelled])->save();
    }

    /** Which documents a given one can be turned into. */
    public function conversionTargets(CustomInvoice $source): array
    {
        if (in_array($source->status, [CustomInvoiceStatus::Cancelled, CustomInvoiceStatus::Declined], true)) {
            return [];
        }

        return match ($source->document_type) {
            InvoiceDocumentType::Quote => [InvoiceDocumentType::Proforma, InvoiceDocumentType::Invoice],
            InvoiceDocumentType::Proforma => [InvoiceDocumentType::Invoice],
            default => [],
        };
    }

    /**
     * Turn a quotation into a proforma/invoice, or a proforma into an invoice: a new
     * draft with its own number and the same lines, linked to its source. A quotation
     * that gets converted is, by definition, accepted.
     *
     * @throws \RuntimeException when the conversion is not allowed
     */
    public function convert(CustomInvoice $source, InvoiceDocumentType $target): CustomInvoice
    {
        if (! in_array($target, $this->conversionTargets($source), true)) {
            throw new \RuntimeException("A {$source->document_type->getLabel()} cannot be converted to a {$target->getLabel()}.");
        }

        return DB::transaction(function () use ($source, $target): CustomInvoice {
            $new = $this->copy($source, $target);
            $new->parent_id = $source->id;
            $new->save();

            if ($source->document_type === InvoiceDocumentType::Quote && in_array($source->status, [CustomInvoiceStatus::Draft, CustomInvoiceStatus::Sent], true)) {
                $source->forceFill(['status' => CustomInvoiceStatus::Accepted])->save();
            }

            return $new;
        });
    }

    /**
     * A credit note against an issued invoice: a new draft with the same lines (edit it
     * down for a partial credit), no payment block, linked to the invoice. The original
     * stays untouched — an issued invoice is never altered.
     *
     * @throws \RuntimeException when the document is not an issued invoice
     */
    public function issueCreditNote(CustomInvoice $invoice, ?string $reason = null): CustomInvoice
    {
        if ($invoice->document_type !== InvoiceDocumentType::Invoice
            || ! in_array($invoice->status, [CustomInvoiceStatus::Sent, CustomInvoiceStatus::Paid], true)) {
            throw new \RuntimeException('A credit note can only be issued against a sent or paid invoice.');
        }

        return DB::transaction(function () use ($invoice, $reason): CustomInvoice {
            $note = $this->copy($invoice, InvoiceDocumentType::CreditNote);
            $note->parent_id = $invoice->id;
            $note->payment_method = InvoicePaymentMethod::None;
            $note->bank_account_id = null;
            $note->payment_link_url = null;
            $note->payment_instructions = null;
            $note->notes = trim('Credit note for invoice '.$invoice->invoice_number.'.'.(filled($reason) ? ' '.trim($reason) : ''));
            $note->save();

            return $note;
        });
    }

    /** A fresh draft of the same type with the same content and a new number. */
    public function duplicate(CustomInvoice $source): CustomInvoice
    {
        $new = $this->copy($source, $source->document_type);
        $new->save();

        return $new;
    }

    /** Unsaved draft of $type carrying $source's client, lines and terms, dated today, with a new number. */
    private function copy(CustomInvoice $source, InvoiceDocumentType $type): CustomInvoice
    {
        $new = $source->replicate([
            'invoice_number', 'status', 'sent_at', 'paid_at', 'parent_id', 'supply_date',
            'subtotal', 'vat_amount', 'total', 'vat_breakdown', 'issue_date', 'due_date', 'created_at', 'updated_at',
        ]);

        $new->document_type = $type;
        $new->invoice_number = $this->nextNumber($type);
        $new->status = CustomInvoiceStatus::Draft;
        $new->issue_date = now();
        $new->due_date = now()->addDays((int) settings('invoice.payment_terms_days', 30));
        $new->created_by = auth('admin')->id() ?? $source->created_by;

        return $new;
    }
}
