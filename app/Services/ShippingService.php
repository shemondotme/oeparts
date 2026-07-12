<?php

namespace App\Services;

use App\Models\ShippingZone;
use App\Models\ShippingMethod;
use App\Models\ShippingCountry;
use App\Models\Cart;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * ShippingService — handles EU shipping zone detection, method selection, and cost calculation.
 *
 * Architecture:
 *   Zone → Countries → Methods
 *
 * Admin configures:
 *   Zones (e.g. Baltic States, Western Europe)
 *   Countries per zone
 *   Methods per zone (flat rate + free threshold)
 *
 * Checkout module:
 *   Detect country from address → Find zone → Show methods
 *   Apply free shipping if threshold met
 */
class ShippingService
{
    public function __construct(
        private SettingsService $settings,
        private CartService $cartService
    ) {}

    /**
     * Find the shipping zone for a given country code.
     *
     * @param string $countryCode ISO 3166-1 alpha-2 (e.g. 'DE', 'FR')
     * @return ShippingZone|null
     */
    public function findZoneForCountry(string $countryCode): ?ShippingZone
    {
        $country = ShippingCountry::where('country_code', strtoupper($countryCode))
            ->with('zone')
            ->first();

        return $country?->zone;
    }

    /**
     * Get available shipping methods for a given country.
     *
     * @param string $countryCode
     * @return Collection<int, ShippingMethod>
     */
    public function getMethodsForCountry(string $countryCode): Collection
    {
        $zone = $this->findZoneForCountry($countryCode);

        if (!$zone) {
            return collect();
        }

        return $zone->methods()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Calculate the shipping cost for a given method and cart.
     *
     * @param Cart $cart
     * @param int $methodId
     * @return string  Flat rate cost as string (e.g. "15.00"), or "0.00" for free shipping
     */
    public function calculateCost(Cart $cart, int $methodId): string
    {
        $method = ShippingMethod::find($methodId);

        if (!$method || !$method->is_active) {
            return '0.00';
        }

        // Check free shipping threshold
        if ($method->free_shipping_threshold !== null) {
            $cartSummary = $this->cartService->getSummary($cart);
            $subtotal = (string) ($cartSummary['subtotal'] ?? '0.00');

            if (bccomp($subtotal, (string) $method->free_shipping_threshold, 2) >= 0) {
                return '0.00';
            }
        }

        return (string) $method->flat_rate;
    }

    /**
     * Get the remaining amount needed for free shipping.
     * Returns null if no method has a free shipping threshold, or if already met.
     *
     * @param Cart $cart
     * @param string $countryCode
     * @return array|null  ['method_name' => string, 'remaining' => string] or null
     */
    public function getFreeShippingNudge(Cart $cart, string $countryCode): ?array
    {
        $zone = $this->findZoneForCountry($countryCode);
        if (!$zone) {
            return null;
        }

        $method = $zone->methods()
            ->where('is_active', true)
            ->whereNotNull('free_shipping_threshold')
            ->orderBy('sort_order')
            ->first();

        if (!$method) {
            return null;
        }

        $cartSummary = $this->cartService->getSummary($cart);
        $subtotal = (string) ($cartSummary['subtotal'] ?? '0.00');

        if (bccomp($subtotal, (string) $method->free_shipping_threshold, 2) >= 0) {
            return null; // Already qualifies
        }

        $remaining = bcsub((string) $method->free_shipping_threshold, $subtotal, 2);

        return [
            'method_name' => trans_field($method->name),
            'remaining'   => $remaining,
            'threshold'   => (string) $method->free_shipping_threshold,
        ];
    }

    /**
     * Get the estimated delivery range for a shipping method.
     *
     * @param int $methodId
     * @return array|null  ['min' => int, 'max' => int] or null
     */
    public function getEstimatedDelivery(int $methodId): ?array
    {
        $method = ShippingMethod::find($methodId);

        if (!$method) {
            return null;
        }

        return [
            'min' => $method->estimated_days_min,
            'max' => $method->estimated_days_max,
        ];
    }

    /**
     * Get all active shipping zones with their methods.
     *
     * @return Collection<int, ShippingZone>
     */
    public function getAllActiveZones(): Collection
    {
        return ShippingZone::with(['methods' => function ($query) {
            $query->where('is_active', true)->orderBy('sort_order');
        }])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get the shipping details as an array for order creation.
     *
     * @param int $methodId
     * @return array|null
     */
    public function getMethodSnapshot(int $methodId): ?array
    {
        $method = ShippingMethod::with('zone')->find($methodId);

        if (!$method) {
            return null;
        }

        return [
            'id'         => $method->id,
            'name'       => trans_field($method->name),
            'flat_rate'  => (string) $method->flat_rate,
            'min_days'   => $method->estimated_days_min,
            'max_days'   => $method->estimated_days_max,
            'zone_name'  => $method->zone?->name,
        ];
    }

    /**
     * Compute a real calendar-date delivery window for a method, honoring
     * the configured fulfillment cutoff time/timezone and business-day
     * list — as opposed to the raw estimated_days_min/max day-COUNT the
     * checkout/order/email views show today.
     *
     * @return array{dispatch_date: Carbon, earliest: Carbon, latest: Carbon, dispatches_today: bool}
     */
    public function getEstimatedDeliveryWindow(ShippingMethod $method, ?Carbon $now = null): array
    {
        $timezone = (string) $this->settings->get('shipping.cutoff_timezone', 'Europe/Vilnius');
        $cutoffTime = (string) $this->settings->get('shipping.cutoff_time', '15:00');
        $businessDays = $this->businessDayNumbers();

        $now = ($now ?? Carbon::now())->clone()->setTimezone($timezone);
        $cutoff = $now->clone()->setTimeFromTimeString($cutoffTime);

        $dispatchesToday = $this->isBusinessDay($now, $businessDays) && $now->lessThanOrEqualTo($cutoff);

        $dispatchDate = $now->clone()->startOfDay();
        if (! $dispatchesToday) {
            $dispatchDate->addDay();
        }
        while (! $this->isBusinessDay($dispatchDate, $businessDays)) {
            $dispatchDate->addDay();
        }

        $minDays = $method->estimated_days_min ?? 0;
        $maxDays = $method->estimated_days_max ?? $minDays;

        return [
            'dispatch_date' => $dispatchDate->clone(),
            'earliest' => $this->addBusinessDays($dispatchDate, $minDays, $businessDays),
            'latest' => $this->addBusinessDays($dispatchDate, $maxDays, $businessDays),
            'dispatches_today' => $dispatchesToday,
        ];
    }

    /**
     * shipping.business_days (TagsInput) accepts either the seeded numeric
     * Carbon dayOfWeek convention (0=Sun..6=Sat — SettingsSeeder's default)
     * or 3-letter day-name tags an operator types into the widget (its own
     * ->default()/suggestions are 'Mon'..'Sun' strings) — both are handled
     * since the two representations can coexist depending on whether the
     * page has ever been re-saved.
     */
    private function businessDayNumbers(): array
    {
        $nameMap = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6];
        $configured = (array) $this->settings->get('shipping.business_days', [1, 2, 3, 4, 5]);

        $numbers = [];
        foreach ($configured as $day) {
            if (is_numeric($day)) {
                $numbers[] = ((int) $day) % 7;
            } elseif (isset($nameMap[$day])) {
                $numbers[] = $nameMap[$day];
            }
        }

        return $numbers ?: [1, 2, 3, 4, 5];
    }

    private function isBusinessDay(Carbon $date, array $businessDays): bool
    {
        return in_array($date->dayOfWeek, $businessDays, true);
    }

    private function addBusinessDays(Carbon $start, int $days, array $businessDays): Carbon
    {
        $date = $start->clone();
        $remaining = max(0, $days);

        while ($remaining > 0) {
            $date->addDay();
            if ($this->isBusinessDay($date, $businessDays)) {
                $remaining--;
            }
        }

        return $date;
    }
}