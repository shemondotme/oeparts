<?php

namespace App\Services;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentMethod;
use App\Mail\CustomInvoiceMail;
use App\Models\CustomInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
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

    public function pdf(CustomInvoice $invoice): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.custom-invoice', $this->viewData($invoice));
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(CustomInvoice $invoice): array
    {
        $documentType = $invoice->document_type;

        return [
            'invoice' => $invoice,
            'documentType' => $documentType,
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
     * @throws \RuntimeException when the invoice has no client email or is cancelled
     */
    public function send(CustomInvoice $invoice): void
    {
        if ($invoice->status === CustomInvoiceStatus::Cancelled) {
            throw new \RuntimeException('A cancelled document cannot be sent.');
        }

        if (blank($invoice->client_email)) {
            throw new \RuntimeException('This document has no client email address.');
        }

        Mail::to($invoice->client_email)->send(new CustomInvoiceMail($invoice, $this->pdf($invoice)->output()));

        $invoice->forceFill([
            'sent_at' => now(),
            'status' => $invoice->status === CustomInvoiceStatus::Draft ? CustomInvoiceStatus::Sent : $invoice->status,
        ])->save();
    }

    public function markPaid(CustomInvoice $invoice): void
    {
        $invoice->forceFill([
            'status' => CustomInvoiceStatus::Paid,
            'paid_at' => now(),
        ])->save();
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
