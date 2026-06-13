<?php

namespace Tests\Unit;

use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Services\ShippingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShippingService $service;
    private ShippingZone $zone;
    private ShippingMethod $standardMethod;
    private ShippingMethod $premiumMethod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShippingService::class);

        $this->zone = ShippingZone::factory()->create(['name' => 'Western Europe', 'is_active' => true, 'sort_order' => 1]);

        ShippingCountry::create([
            'zone_id'      => $this->zone->id,
            'country_code' => 'DE',
            'country_name' => 'Germany',
        ]);

        $this->standardMethod = ShippingMethod::create([
            'zone_id'                  => $this->zone->id,
            'name'                     => json_encode(['en' => 'Standard Shipping']),
            'flat_rate'                => '9.90',
            'free_shipping_threshold'  => '100.00',
            'estimated_days_min'       => 3,
            'estimated_days_max'       => 5,
            'is_active'                => true,
            'sort_order'               => 1,
        ]);

        $this->premiumMethod = ShippingMethod::create([
            'zone_id'                 => $this->zone->id,
            'name'                    => json_encode(['en' => 'Express Shipping']),
            'flat_rate'               => '19.90',
            'free_shipping_threshold' => null,
            'estimated_days_min'      => 1,
            'estimated_days_max'      => 2,
            'is_active'               => true,
            'sort_order'              => 2,
        ]);
    }

    // -------------------------------------------------------------------------
    // findZoneForCountry()
    // -------------------------------------------------------------------------

    #[Test]
    public function finds_zone_for_known_country(): void
    {
        $zone = $this->service->findZoneForCountry('DE');

        $this->assertNotNull($zone);
        $this->assertEquals($this->zone->id, $zone->id);
    }

    #[Test]
    public function returns_null_for_unknown_country(): void
    {
        $zone = $this->service->findZoneForCountry('XX');

        $this->assertNull($zone);
    }

    #[Test]
    public function country_lookup_is_case_insensitive(): void
    {
        $zone = $this->service->findZoneForCountry('de');

        $this->assertNotNull($zone);
    }

    // -------------------------------------------------------------------------
    // getMethodsForCountry()
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_active_methods_for_country(): void
    {
        $methods = $this->service->getMethodsForCountry('DE');

        $this->assertCount(2, $methods);
    }

    #[Test]
    public function inactive_methods_are_excluded(): void
    {
        $this->standardMethod->update(['is_active' => false]);

        $methods = $this->service->getMethodsForCountry('DE');

        $this->assertCount(1, $methods);
        $this->assertEquals($this->premiumMethod->id, $methods->first()->id);
    }

    #[Test]
    public function returns_empty_collection_for_unserviced_country(): void
    {
        $methods = $this->service->getMethodsForCountry('AU');

        $this->assertTrue($methods->isEmpty());
    }

    // -------------------------------------------------------------------------
    // getEstimatedDelivery()
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_correct_delivery_range(): void
    {
        $delivery = $this->service->getEstimatedDelivery($this->standardMethod->id);

        $this->assertEquals(['min' => 3, 'max' => 5], $delivery);
    }

    #[Test]
    public function returns_null_for_invalid_method_id(): void
    {
        $delivery = $this->service->getEstimatedDelivery(99999);

        $this->assertNull($delivery);
    }

    // -------------------------------------------------------------------------
    // getMethodSnapshot()
    // -------------------------------------------------------------------------

    #[Test]
    public function snapshot_returns_correct_structure(): void
    {
        $snapshot = $this->service->getMethodSnapshot($this->standardMethod->id);

        $this->assertNotNull($snapshot);
        $this->assertEquals($this->standardMethod->id, $snapshot['id']);
        $this->assertEquals('9.90', $snapshot['flat_rate']);
        $this->assertEquals(3, $snapshot['min_days']);
        $this->assertEquals(5, $snapshot['max_days']);
    }

    #[Test]
    public function snapshot_returns_null_for_missing_method(): void
    {
        $snapshot = $this->service->getMethodSnapshot(99999);

        $this->assertNull($snapshot);
    }

    // -------------------------------------------------------------------------
    // getAllActiveZones()
    // -------------------------------------------------------------------------

    #[Test]
    public function returns_only_active_zones(): void
    {
        ShippingZone::factory()->create(['is_active' => false]);

        $zones = $this->service->getAllActiveZones();

        foreach ($zones as $zone) {
            $this->assertTrue($zone->is_active);
        }
    }
}
