<?php

namespace Tests\Feature;

use App\Models\Carrier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront (cart, footer badge, shipping section, product FAQ) lists the
 * carriers enabled under Carriers in the admin, in the admin's order.
 */
class ActiveCarriersStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function makeCarrier(string $name, bool $active = true, int $order = 0): Carrier
    {
        return Carrier::create([
            'name' => $name,
            'tracking_url' => 'https://example.test/track/{tracking_number}',
            'is_active' => $active,
            'sort_order' => $order,
        ]);
    }

    public function test_only_active_carriers_are_listed_in_admin_order(): void
    {
        $this->makeCarrier('GLS', true, 3);
        $this->makeCarrier('DHL', true, 1);
        $this->makeCarrier('UPS', false, 2);
        $this->makeCarrier('DPD', true, 2);

        $this->assertSame(['DHL', 'DPD', 'GLS'], Carrier::activeNames());
    }

    public function test_list_follows_admin_changes_straight_away(): void
    {
        $dhl = $this->makeCarrier('DHL', true, 1);
        $ups = $this->makeCarrier('UPS', false, 2);

        $this->assertSame(['DHL'], Carrier::activeNames()); // primes the cache

        $ups->update(['is_active' => true]);
        $this->assertSame(['DHL', 'UPS'], Carrier::activeNames());

        $dhl->update(['is_active' => false]);
        $this->assertSame(['UPS'], Carrier::activeNames());

        $ups->delete();
        $this->assertSame([], Carrier::activeNames());
    }

    public function test_no_active_carrier_gives_an_empty_list(): void
    {
        $this->makeCarrier('FedEx', false);

        $this->assertSame([], Carrier::activeNames());
    }
}
