<?php

namespace App\Services;

/**
 * Totals of a hand-written invoice, derived only from its lines (bcmath, 2 decimals,
 * half-up rounding). Used by the model on every save and by the form's live preview,
 * so what the admin sees while typing is exactly what gets stored.
 *
 * Per line:  net = quantity × unit price − line discount %
 * Invoice:   subtotal = Σ line nets
 *            discount = a fixed amount or a % of the subtotal (never more than it)
 *            VAT      = per rate: (rate's share of the subtotal − its proportional
 *                       share of the discount) × rate, rounded once per rate
 *            total    = subtotal − discount + VAT
 *
 * A line can carry its own VAT rate (goods, freight and services often differ); a
 * line without one uses the invoice's default rate. Any treatment other than
 * 'standard' (reverse charge, intra-EU supply, export, exempt) means 0% on every line.
 */
class InvoiceCalculator
{
    public const TREATMENTS = ['standard', 'reverse_charge', 'intra_eu', 'export', 'exempt'];

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{
     *     lines: list<array{description: string, part_number: string, lead_time: string, unit: string, quantity: string, unit_price: string, discount_percent: string, vat_rate: string, line_total: string, product_id: ?int}>,
     *     subtotal: string, discount_amount: string, vat_amount: string, total: string,
     *     breakdown: list<array{rate: string, base: string, vat: string}>, treatment: string
     * }
     */
    public function calculate(
        array $items,
        string $treatment,
        mixed $defaultVatRate,
        string $discountType = 'amount',
        mixed $discountValue = 0,
    ): array {
        $treatment = in_array($treatment, self::TREATMENTS, true) ? $treatment : 'standard';
        $defaultRate = $this->percent($defaultVatRate);

        $lines = [];
        $subtotal = '0.00';

        foreach ($items as $item) {
            $quantity = $this->decimal($item['quantity'] ?? 0);
            $unit = $this->decimal($item['unit_price'] ?? 0);
            $discountPercent = $this->percent($item['discount_percent'] ?? 0);

            $gross = $this->round2(bcmul($quantity, $unit, 6));
            $lineDiscount = $this->round2(bcdiv(bcmul($gross, $discountPercent, 6), '100', 6));
            $net = bcsub($gross, $lineDiscount, 2);

            $lineRate = $treatment === 'standard'
                ? (isset($item['vat_rate']) && $item['vat_rate'] !== '' ? $this->percent($item['vat_rate']) : $defaultRate)
                : '0.00';

            $lines[] = [
                'description' => (string) ($item['description'] ?? ''),
                'part_number' => (string) ($item['part_number'] ?? ''),
                'lead_time' => (string) ($item['lead_time'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unit,
                'discount_percent' => $discountPercent,
                'vat_rate' => $lineRate,
                'line_total' => $net,
                'product_id' => isset($item['product_id']) && $item['product_id'] !== '' ? (int) $item['product_id'] : null,
            ];

            $subtotal = bcadd($subtotal, $net, 2);
        }

        // Invoice-level discount, never more than the subtotal (that would be a negative invoice).
        $discount = $discountType === 'percent'
            ? $this->round2(bcdiv(bcmul($subtotal, $this->percent($discountValue), 6), '100', 6))
            : $this->decimal($discountValue);
        if (bccomp($discount, $subtotal, 2) > 0) {
            $discount = $subtotal;
        }

        // Group the nets by rate and spread the discount over the groups in proportion;
        // the last group takes the remainder so the pieces always add up to the discount.
        $groups = [];
        foreach ($lines as $line) {
            $groups[$line['vat_rate']] = bcadd($groups[$line['vat_rate']] ?? '0.00', $line['line_total'], 2);
        }
        krsort($groups, SORT_NUMERIC);

        $breakdown = [];
        $vatTotal = '0.00';
        $discountLeft = $discount;
        $remaining = count($groups);

        foreach ($groups as $rate => $base) {
            $remaining--;
            $share = $remaining === 0
                ? $discountLeft
                : (bccomp($subtotal, '0', 2) === 0 ? '0.00' : $this->round2(bcdiv(bcmul($discount, $base, 6), $subtotal, 6)));
            $discountLeft = bcsub($discountLeft, $share, 2);

            $taxable = bcsub($base, $share, 2);
            $vat = $this->round2(bcdiv(bcmul($taxable, (string) $rate, 6), '100', 6));

            $breakdown[] = ['rate' => (string) $rate, 'base' => $taxable, 'vat' => $vat];
            $vatTotal = bcadd($vatTotal, $vat, 2);
        }

        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'vat_amount' => $vatTotal,
            'total' => bcadd(bcsub($subtotal, $discount, 2), $vatTotal, 2),
            'breakdown' => $breakdown,
            'treatment' => $treatment,
        ];
    }

    /** Half-up rounding to 2 decimals for a bcmath string of any precision. */
    private function round2(string $value): string
    {
        return bccomp($value, '0', 6) >= 0
            ? bcadd($value, '0.005', 2)
            : bcsub($value, '0.005', 2);
    }

    /** Non-negative money/quantity from whatever was typed ("12,5", "", null), 2 decimals. */
    private function decimal(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) && bccomp($value, '0', 6) >= 0 ? $this->round2($value) : '0.00';
    }

    /** A percentage clamped to 0–100. */
    private function percent(mixed $value): string
    {
        $value = $this->decimal($value);

        return bccomp($value, '100', 2) > 0 ? '100.00' : $value;
    }
}
