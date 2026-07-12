<?php

namespace App\Services;

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
                    'current_value'    => 0,
                    'resets_monthly'   => $type !== SequenceType::Rma,
                    'last_reset_month' => null,
                ]
            );

            /** @var Sequence $sequence */
            $sequence = Sequence::where('type', $type)->lockForUpdate()->firstOrFail();

            $currentMonth = now()->format('Y-m');

            // Reset monthly if the month has rolled over
            if ($sequence->resets_monthly && $sequence->last_reset_month !== $currentMonth) {
                $sequence->current_value   = 0;
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
        $prefix  = $this->prefix($type);
        $padding = (int) settings('orders.order_number_padding', 6);
        $padded  = str_pad((string) $value, $padding, '0', STR_PAD_LEFT);

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
            SequenceType::Order   => settings('orders.order_number_prefix', 'ORD'),
            SequenceType::Invoice => settings('orders.invoice_number_prefix', 'INV'),
            SequenceType::Rma     => settings('orders.rma_number_prefix', 'RMA'),
        };
    }
}
