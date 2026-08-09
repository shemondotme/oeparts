<?php

namespace Tests\Feature;

use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Api\ShippingController::index() previously returned every active method
 * across every zone with no way to narrow by destination — a mobile client
 * had no server-side way to show only methods that actually serve the
 * customer's country.
 */
class ApiShippingControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function without_country_code_returns_every_active_method(): void
    {
        $baltic = ShippingZone::create(['name' => 'Baltic', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $baltic->id, 'country_code' => 'LT', 'country_name' => 'Lithuania']);
        ShippingMethod::create([
            'zone_id' => $baltic->id, 'name' => ['en' => 'Baltic Standard'],
            'flat_rate' => '4.99', 'is_active' => true,
            'estimated_days_min' => 1, 'estimated_days_max' => 3,
        ]);

        $western = ShippingZone::create(['name' => 'Western Europe', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $western->id, 'country_code' => 'FR', 'country_name' => 'France']);
        ShippingMethod::create([
            'zone_id' => $western->id, 'name' => ['en' => 'Western Standard'],
            'flat_rate' => '9.99', 'is_active' => true,
            'estimated_days_min' => 2, 'estimated_days_max' => 5,
        ]);

        $response = $this->getJson('/api/shipping-methods');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    #[Test]
    public function with_country_code_only_returns_methods_serving_that_country(): void
    {
        $baltic = ShippingZone::create(['name' => 'Baltic', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $baltic->id, 'country_code' => 'LT', 'country_name' => 'Lithuania']);
        ShippingMethod::create([
            'zone_id' => $baltic->id, 'name' => ['en' => 'Baltic Standard'],
            'flat_rate' => '4.99', 'is_active' => true,
            'estimated_days_min' => 1, 'estimated_days_max' => 3,
        ]);

        $western = ShippingZone::create(['name' => 'Western Europe', 'is_active' => true]);
        ShippingCountry::create(['zone_id' => $western->id, 'country_code' => 'FR', 'country_name' => 'France']);
        ShippingMethod::create([
            'zone_id' => $western->id, 'name' => ['en' => 'Western Standard'],
            'flat_rate' => '9.99', 'is_active' => true,
            'estimated_days_min' => 2, 'estimated_days_max' => 5,
        ]);

        $response = $this->getJson('/api/shipping-methods?country_code=LT');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Baltic Standard', $data[0]['name']);
    }
}
