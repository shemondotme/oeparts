<?php

namespace App\Services;

use App\Enums\InvoiceDocumentType;
use App\Enums\SequenceType;
use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * SequenceService — generates human-readable, sequential reference numbers.
 *
 * Format: {PREFIX}-{YYYYMM}-{NNNNNN}
 * Example order:   ORD-202603-000001
 * Example invoice: INV-202603-000001
 * Example RMA:     RMA-000001  (no month — does not reset)
 *
 * Uses SELECT … FOR UPDATE to guarantee uniqueness under concurrent requests.
 */
class SequenceService
{
    /**
     * Generate and return the next order number.
     */
    public function nextOrderNumber(): string
    {
        return $this->next(SequenceType::Order);
    }

    /**
     * Generate and return the next invoice number.
     */
    public function nextInvoiceNumber(): string
    {
        return $this->next(SequenceType::Invoice);
    }

    /**
     * Next number for a hand-written document, from that type's own series
     * (invoices share the order-invoice series; quotes, proformas and credit notes have theirs).
     */
    public function nextDocumentNumber(InvoiceDocumentType $type): string
    {
        return $this->next($type->sequenceType());
    }

    /**
     * Generate and return the next RMA number.
     */
    public function nextRmaNumber(): string
    {
        return $this->next(SequenceType::Rma);
    }

    /**
     * Core: atomically increment the sequence and return the formatted number.
     */
    private function next(SequenceType $type): string
    {
        return DB::transaction(function () use ($type) {
            // Ensure a row exists the first time this sequence type is used.
            Sequence::firstOrCreate(
                ['type' => $type],
                [
                    'current_value' => 0,
                    'resets_monthly' => $type !== SequenceType::Rma,
                    'last_reset_month' => null,
                ]
            );

            /** @var Sequence $sequence */
            $sequence = Sequence::where('type', $type)->lockForUpdate()->firstOrFail();

            $currentMonth = now()->format('Y-m');

            // Reset monthly if the month has rolled over
            if ($sequence->resets_monthly && $sequence->last_reset_month !== $currentMonth) {
                $sequence->current_value = 0;
                $sequence->last_reset_month = $currentMonth;
            }

            $sequence->current_value += 1;
            $sequence->save();

            return $this->format($type, $sequence->current_value, $sequence->resets_monthly);
        });
    }

    /**
     * Format a sequence number into a human-readable string.
     */
    private function format(SequenceType $type, int $value, bool $hasMonth): string
    {
        $prefix = $this->prefix($type);
        $padding = (int) settings('orders.order_number_padding', 6);
        $padded = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

        if ($hasMonth) {
            $month = now()->format('Ym');

            return "{$prefix}-{$month}-{$padded}";
        }

        return "{$prefix}-{$padded}";
    }

    /**
     * Map sequence type to its string prefix.
     * Falls back to settings if available, else uses defaults.
     */
    private function prefix(SequenceType $type): string
    {
        return match ($type) {
            SequenceType::Order => settings('orders.order_number_prefix', 'ORD'),
            SequenceType::Invoice => settings('orders.invoice_number_prefix', 'INV'),
            SequenceType::Rma => settings('orders.rma_number_prefix', 'RMA'),
            SequenceType::Quote => settings('orders.quote_number_prefix', 'QUO'),
            SequenceType::Proforma => settings('orders.proforma_number_prefix', 'PRO'),
            SequenceType::CreditNote => settings('orders.credit_note_number_prefix', 'CRN'),
        };
    }
}
