<?php

namespace App\Services;

use App\Models\TaxRate;

/**
 * Single source of truth for "what VAT/tax rate applies to this order" —
 * used by CheckoutService (order creation), CheckoutController (checkout
 * sidebar live total), and CartService (cart-page estimate). Country-based
 * VAT is opt-in (tax.country_based_vat_enabled, default off) so enabling
 * this system never silently changes what existing stores charge until an
 * operator explicitly turns it on and reviews the seeded rates.
 */
class TaxRateService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * Resolve the VAT/tax percent to apply, given a customer's (shipping)
     * country code. Falls back to the flat tax.default_vat_rate when
     * country-based VAT is disabled, the country is unknown, or no active
     * rate is configured for that country.
     */
    public function resolve(?string $countryCode): string
    {
        $default = (string) $this->settings->get('tax.default_vat_rate', '21.00');

        $enabled = filter_var(
            $this->settings->get('tax.country_based_vat_enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        if (! $enabled || blank($countryCode)) {
            return $default;
        }

        $rate = TaxRate::where('country_code', strtoupper($countryCode))
            ->where('is_active', true)
            ->value('rate');

        return $rate !== null ? (string) $rate : $default;
    }
}
