<?php

namespace App\Services;

use App\Models\Coupon;

/**
 * Totals for an order an admin is creating by hand (phone / email / walk-in
 * orders), using the SAME rules the storefront checkout applies, so an
 * admin-created order can never disagree with what the shop would have charged:
 *
 *   discount  — CouponService::validateCoupon() against the product subtotal
 *   shipping  — the method's flat rate, free above its threshold, and only for
 *               countries the method's zone serves
 *   VAT       — on (subtotal − discount + shipping + fees), at the rate
 *               TaxRateService::resolve() gives for the destination (EU Art. 79(b):
 *               the discount is outside the taxable amount, as in CheckoutService)
 *
 * Plus the two cases the storefront can never meet because it sells inside
 * the EU only: a destination OUTSIDE the EU is a zero-rated export, and an
 * order flagged VAT-exempt (valid EU B2B reverse charge) carries no VAT.
 * Pure arithmetic with bcmath — nothing is saved here.
 */
class AdminOrderCalculator
{
    public function __construct(
        private readonly TaxRateService $taxRates,
        private readonly ShippingService $shipping,
        private readonly CouponService $coupons,
        private readonly ViesService $vies,
    ) {}

    /**
     * @param  list<array{quantity?: mixed, unit_price?: mixed}>  $items
     * @return array{
     *     subtotal: string, discount_amount: string, shipping_cost: string, vat_amount: string,
     *     grand_total: string, vat_rate: string, vat_reason: string, shipping_error: ?string, warnings: list<string>
     * }
     */
    public function compute(
        array $items,
        ?string $countryCode,
        ?int $shippingMethodId,
        ?int $couponId,
        ?int $userId = null,
        ?string $email = null,
        bool $vatExempt = false,
        string $urgentProcessingFee = '0.00',
        string $handlingFee = '0.00',
    ): array {
        $warnings = [];
        $shippingError = null;

        $subtotal = '0.00';
        foreach ($items as $item) {
            $line = bcmul($this->money($item['quantity'] ?? 0), $this->money($item['unit_price'] ?? 0), 2);
            $subtotal = bcadd($subtotal, $line, 2);
        }

        // Discount
        $discount = '0.00';
        if ($couponId && ($coupon = Coupon::find($couponId))) {
            $result = $this->coupons->validateCoupon($coupon, $subtotal, $userId, $email);
            if ($result['valid']) {
                $discount = (string) $result['discount'];
            } else {
                $warnings[] = 'Coupon not applied: '.($result['message'] ?? 'not valid for this order.');
            }
        }

        // Shipping
        $shippingCost = '0.00';
        if ($shippingMethodId) {
            try {
                $shippingCost = $this->shipping->calculateCostForSubtotal($shippingMethodId, $subtotal, $countryCode);
            } catch (\RuntimeException $e) {
                $shippingError = $e->getMessage();
                $warnings[] = $shippingError;
            }
        }

        // VAT
        $discounted = bcsub($subtotal, $discount, 2);
        if (bccomp($discounted, '0.00', 2) === -1) {
            $discounted = '0.00';
        }
        $taxableBase = bcadd(bcadd(bcadd($discounted, $shippingCost, 2), $this->money($urgentProcessingFee), 2), $this->money($handlingFee), 2);

        $isExport = filled($countryCode) && ! $this->vies->isEuCountry((string) $countryCode);

        if ($vatExempt) {
            $rate = '0.00';
            $reason = 'VAT-exempt (reverse charge)';
        } elseif ($isExport) {
            $rate = '0.00';
            $reason = 'Zero-rated export (destination outside the EU)';
        } else {
            $rate = $this->taxRates->resolve($countryCode);
            $reason = 'Standard rate for '.($countryCode ?: 'the default country');
        }

        $vat = bcmul($taxableBase, bcdiv($rate, '100', 4), 2);
        $grandTotal = bcadd($taxableBase, $vat, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'shipping_cost' => $shippingCost,
            'vat_amount' => $vat,
            'grand_total' => $grandTotal,
            'vat_rate' => $rate,
            'vat_reason' => $reason,
            'shipping_error' => $shippingError,
            'warnings' => $warnings,
        ];
    }

    /** Normalise user-typed money/quantity input ("12,5", null, "") into a bcmath-safe string. */
    private function money(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string) $value));

        return is_numeric($value) && bccomp($value, '0', 2) >= 0 ? bcadd($value, '0', 2) : '0.00';
    }
}
