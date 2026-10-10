<?php

namespace App\Support;

/**
 * Which payment methods the storefront offers, from Settings → Checkout &
 * Payments → "Allowed Payment Methods" (a multi-select: any combination of
 * card, paysera and bank_transfer).
 *
 * The setting used to be a free-text tags box that only the server-side
 * validation looked at, while the checkout pages printed all three radios no
 * matter what — so an admin could not actually hide a method, and a customer
 * could pick one the shop then refused. Every place that shows or accepts a
 * payment method now asks this class.
 */
class CheckoutPaymentMethods
{
    /** Every method the shop can take, in display order. */
    public const ALL = ['card', 'paysera', 'bank_transfer'];

    /** What an unset / unusable setting falls back to (the long-standing store default). */
    private const FALLBACK = ['card', 'bank_transfer'];

    /**
     * The enabled methods, in display order. Never empty — a checkout with no
     * way to pay is worse than ignoring a broken setting.
     *
     * @return array<int, string>
     */
    public static function enabled(): array
    {
        $raw = settings('checkout.allowed_payment_methods', self::FALLBACK);

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        $enabled = array_values(array_intersect(self::ALL, array_map('strval', (array) $raw)));

        return $enabled !== [] ? $enabled : self::FALLBACK;
    }

    public static function isEnabled(string $method): bool
    {
        return in_array($method, self::enabled(), true);
    }

    /** The method pre-selected at checkout: the configured default if it is enabled, else the first enabled. */
    public static function default(): string
    {
        $default = (string) settings('checkout.default_payment_method', 'card');

        return self::isEnabled($default) ? $default : self::enabled()[0];
    }

    /** A previously chosen method if it is still offered, otherwise the default. */
    public static function resolve(?string $method): string
    {
        return $method !== null && self::isEnabled($method) ? $method : self::default();
    }

    /** Validation rule value for a payment_method field. */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::enabled());
    }
}
