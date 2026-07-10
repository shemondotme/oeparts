<?php

namespace Tests\Feature;

use App\Mail\OrderShipped;
use App\Models\Carrier;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCarrier(array $attrs = []): Carrier
    {
        return Carrier::create(array_merge([
            'name'         => 'DHL',
            'tracking_url' => 'https://www.dhl.com/track?trackingNo={tracking_no}',
            'is_active'    => true,
            'sort_order'   => 0,
        ], $attrs));
    }

    public function test_tracking_url_is_built_from_carrier_template(): void
    {
        $carrier = $this->makeCarrier();
        $order = Order::factory()->create([
            'carrier_id'      => $carrier->id,
            'tracking_number' => 'JD014600003RF',
        ]);

        $this->assertSame(
            'https://www.dhl.com/track?trackingNo=JD014600003RF',
            $order->tracking_url,
        );
    }

    public function test_tracking_url_is_null_without_carrier_or_number(): void
    {
        $carrier = $this->makeCarrier();

        $noCarrier = Order::factory()->create(['tracking_number' => 'X1']);
        $noNumber = Order::factory()->create(['carrier_id' => $carrier->id, 'tracking_number' => null]);
        $noTemplate = Order::factory()->create([
            'carrier_id'      => $this->makeCarrier(['name' => 'Pickup', 'tracking_url' => ''])->id,
            'tracking_number' => 'X2',
        ]);

        $this->assertNull($noCarrier->tracking_url);
        $this->assertNull($noNumber->tracking_url);
        $this->assertNull($noTemplate->tracking_url);
    }

    public function test_carrier_name_prefers_relation_and_falls_back_to_legacy_string(): void
    {
        $carrier = $this->makeCarrier(['name' => 'GLS']);

        $withRelation = Order::factory()->create(['carrier_id' => $carrier->id, 'carrier' => 'Old Text']);
        $legacyOnly = Order::factory()->create(['carrier_id' => null, 'carrier' => 'DPD (manual)']);
        $neither = Order::factory()->create(['carrier_id' => null, 'carrier' => null]);

        $this->assertSame('GLS', $withRelation->carrier_name);
        $this->assertSame('DPD (manual)', $legacyOnly->carrier_name);
        $this->assertNull($neither->carrier_name);
    }

    public function test_shipped_email_renders_tracking_link_and_carrier_name(): void
    {
        $carrier = $this->makeCarrier();
        $order = Order::factory()->create([
            'carrier_id'      => $carrier->id,
            'tracking_number' => 'JD014600003RF',
        ]);

        $html = (new OrderShipped($order))->render();

        $this->assertStringContainsString('https://www.dhl.com/track?trackingNo=JD014600003RF', $html);
        $this->assertStringContainsString('DHL', $html);
        $this->assertStringNotContainsString('Standard Courier', $html);
    }

    public function test_order_totals_recalculate_from_items_with_bcmath(): void
    {
        $order = Order::factory()->create([
            'subtotal'        => '100.00',
            'discount_amount' => '10.00',
            'shipping_cost'   => '15.00',
            'vat_amount'      => '12.48',
            'grand_total'     => '117.48',
        ]);

        $order->items()->create([
            'product_id'            => \App\Models\Product::factory()->create()->id,
            'oem_number_snapshot'   => 'OEM-1',
            'manufacturer_snapshot' => 'BMW',
            'condition_snapshot'    => 'New',
            'quantity'              => 2,
            'unit_price'            => '36.62',
            'total_price'           => '73.24',
        ]);

        app(OrderService::class)->recalculateTotals($order);
        $order->refresh();

        $this->assertSame('73.24', (string) $order->subtotal);
        // 73.24 + 15.00 + 12.48 − 10.00
        $this->assertSame('90.72', (string) $order->grand_total);
    }
}
