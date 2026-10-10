<?php

namespace App\Support;

use App\Services\PaymentService;

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

    /**
     * The Paysera wallets to offer: switched on by the admin AND really available
     * on the Paysera project (Paysera only returns apple-pay / google-pay for
     * projects it enabled them on). Empty when Paysera is off, unreachable, or the
     * project has none.
     *
     * @return array<string, string> wallet key => label
     */
    public static function payseraWallets(): array
    {
        if (! self::isEnabled('paysera')) {
            return [];
        }

        $wanted = array_filter([
            'apple-pay' => self::flag('checkout.paysera_apple_pay_enabled') ? 'Apple Pay' : null,
            'google-pay' => self::flag('checkout.paysera_google_pay_enabled') ? 'Google Pay' : null,
        ]);

        if ($wanted === []) {
            return [];
        }

        $available = app(PaymentService::class)->payseraMethodKeys() ?? [];

        return array_intersect_key($wanted, array_flip($available));
    }

    private static function flag(string $key): bool
    {
        return filter_var(settings($key, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** Validation rule value for a payment_method field. */
    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::enabled());
    }
}
