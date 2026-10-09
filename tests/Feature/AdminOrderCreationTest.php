<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\SequenceType;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Admin;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Condition;
use App\Models\Coupon;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sequence;
use App\Models\ShippingCountry;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\AdminOrderCalculator;
use App\Services\CheckoutService;
use App\Services\TaxRateService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin "New order" used to have no items at all (they could only be typed in
 * afterwards, free-hand, unlinked to the catalog) and every total was typed by
 * hand. It now builds the order from catalog parts and calculates the totals
 * with the storefront's own rules; the customer phone and the extra address
 * fields are persisted (checkout used to collect the phone and then drop it).
 */
class AdminOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private Product $partA;

    private Product $partB;

    private ShippingMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

        $condition = Condition::firstOrCreate(
            ['slug' => 'used'],
            ['name' => 'Used', 'bg_color' => '#fff', 'text_color' => '#000', 'is_active' => true]
        );
        $manufacturer = Manufacturer::create(['name' => 'Bosch', 'slug' => 'bosch', 'country_code' => 'DE', 'is_active' => true]);

        $this->partA = Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'A2024101247', 'normalized_oem' => 'A2024101247',
            'name' => 'Parts kit', 'description' => 'x', 'price' => 100.00, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);
        $this->partB = Product::create([
            'manufacturer_id' => $manufacturer->id, 'oem_number' => 'B777', 'normalized_oem' => 'B777',
            'name' => 'Other part', 'description' => 'x', 'price' => 50.00, 'condition_id' => $condition->id,
            'is_in_stock' => true, 'is_active' => true,
        ]);

        $zone = ShippingZone::create(['name' => 'Everywhere', 'is_active' => true]);
        foreach (['DE', 'LT', 'JP'] as $code) {
            ShippingCountry::create(['zone_id' => $zone->id, 'country_code' => $code, 'country_name' => $code]);
        }
        $this->method = ShippingMethod::create([
            'zone_id' => $zone->id, 'name' => ['en' => 'Courier'], 'flat_rate' => '12.50',
            'estimated_days_min' => 2, 'estimated_days_max' => 5, 'is_active' => true,
        ]);

        Sequence::create(['type' => SequenceType::Order, 'value' => 0, 'month' => now()->format('Ym')]);
    }

    /** @return array<string, mixed> */
    private function baseForm(array $overrides = []): array
    {
        return array_merge([
            'guest_email' => 'buyer@example.com',
            'customer_phone' => '+81-463-53-3001',
            'shipping_name' => 'Tomoko Spivey',
            'shipping_address_line1' => '2-2-24 Ogami Hiratsuka',
            'shipping_address_line2' => 'Building 3',
            'shipping_city' => 'Hiratsuka',
            'shipping_state' => 'Kanagawa',
            'shipping_postal_code' => '2540012',
            'shipping_country_code' => 'DE',
            'shipping_method_id' => $this->method->id,
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'line_items' => [
                ['product_id' => $this->partA->id, 'quantity' => 1, 'unit_price' => '100.00'],
                ['product_id' => $this->partB->id, 'quantity' => 2, 'unit_price' => '50.00'],
            ],
        ], $overrides);
    }

    #[Test]
    public function an_order_is_built_from_catalog_parts_with_server_calculated_totals(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();

        $this->assertCount(2, $order->items);
        $this->assertEqualsCanonicalizing([$this->partA->id, $this->partB->id], $order->items->pluck('product_id')->all());
        $this->assertSame('A2024101247', $order->items->firstWhere('product_id', $this->partA->id)->oem_number_snapshot);
        $this->assertSame('Bosch', $order->items->firstWhere('product_id', $this->partA->id)->manufacturer_snapshot);
        $this->assertSame('100.00', $order->items->firstWhere('product_id', $this->partB->id)->total_price);

        // 200.00 + 12.50 shipping = 212.50; VAT at the default rate on that
        $rate = (string) app(TaxRateService::class)->resolve('DE');
        $vat = bcmul('212.50', bcdiv($rate, '100', 4), 2);
        $this->assertSame('200.00', $order->subtotal);
        $this->assertSame('12.50', $order->shipping_cost);
        $this->assertSame($vat, $order->vat_amount);
        $this->assertSame(bcadd('212.50', $vat, 2), $order->grand_total);
    }

    #[Test]
    public function the_phone_and_the_extra_address_fields_are_saved(): void
    {
        Livewire::test(CreateOrder::class)->fillForm($this->baseForm())->call('create')->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('+81-463-53-3001', $order->customer_phone);
        $this->assertSame('Building 3', $order->shipping_address_line2);
        $this->assertSame('Kanagawa', $order->shipping_state);
        $this->assertNull($order->billing_address_line1, 'billing stays empty = same as shipping');
    }

    #[Test]
    public function a_separate_billing_address_is_saved_only_when_asked_for(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm([
                'billing_different' => true,
                'billing_name' => 'Sakamoto Engineering Co. Ltd.',
                'billing_address_line1' => 'Head office 1',
                'billing_city' => 'Tokyo',
                'billing_country_code' => 'JP',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('Head office 1', $order->billing_address_line1);
        $this->assertSame('JP', $order->billing_country_code);
        $this->assertSame('Sakamoto Engineering Co. Ltd.', $order->billing_name);
    }

    #[Test]
    public function totals_sent_by_the_browser_are_ignored_unless_totals_are_adjusted_manually(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm(['subtotal' => '1.00', 'grand_total' => '1.00', 'vat_amount' => '0.00', 'shipping_cost' => '0.00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('200.00', $order->subtotal, 'a tampered preview value must never be saved');
    }

    #[Test]
    public function manual_totals_are_saved_exactly_as_typed_and_need_no_items(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm([
                'line_items' => [],
                'manual_totals' => true,
                'subtotal' => '100.00', 'shipping_cost' => '10.00', 'vat_amount' => '21.00', 'grand_total' => '131.00',
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('131.00', $order->grand_total);
        $this->assertCount(0, $order->items);
    }

    #[Test]
    public function an_order_needs_at_least_one_item_unless_totals_are_manual(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm(['line_items' => []]))
            ->call('create')
            ->assertHasFormErrors(['line_items']);

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function a_shipping_method_that_does_not_serve_the_country_is_rejected(): void
    {
        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm(['shipping_country_code' => 'FR']))
            ->call('create')
            ->assertHasFormErrors(['shipping_method_id']);

        $this->assertSame(0, Order::count());
    }

    #[Test]
    public function sold_parts_are_marked_out_of_stock_and_the_customer_is_not_emailed_by_default(): void
    {
        Queue::fake();

        Livewire::test(CreateOrder::class)->fillForm($this->baseForm())->call('create')->assertHasNoFormErrors();

        $this->assertFalse($this->partA->refresh()->is_in_stock);
        $this->assertFalse($this->partB->refresh()->is_in_stock);
        Queue::assertNotPushed(SendOrderConfirmationEmail::class);
    }

    #[Test]
    public function the_confirmation_email_is_sent_when_the_admin_asks_for_it(): void
    {
        Queue::fake();

        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm(['send_confirmation' => true]))
            ->call('create')
            ->assertHasNoFormErrors();

        Queue::assertPushed(SendOrderConfirmationEmail::class);
    }

    #[Test]
    public function a_coupon_discount_is_applied_with_the_storefront_rules_and_recorded(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by' => Admin::first()->id, 'code' => 'ADMIN10', 'discount_type' => DiscountType::Percentage,
            'discount_value' => 10, 'is_active' => true, 'usage_limit' => null, 'usage_limit_per_user' => null,
            'min_order_amount' => null, 'expires_at' => now()->addDays(10),
        ]);

        Livewire::test(CreateOrder::class)
            ->fillForm($this->baseForm(['coupon_id' => $coupon->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();
        $this->assertSame('20.00', $order->discount_amount);
        $this->assertSame($coupon->id, $order->coupon_id);
    }

    #[Test]
    public function editing_an_order_keeps_working_and_can_add_a_billing_address(): void
    {
        Livewire::test(CreateOrder::class)->fillForm($this->baseForm())->call('create')->assertHasNoFormErrors();
        $order = Order::where('guest_email', 'buyer@example.com')->firstOrFail();

        Livewire::test(EditOrder::class, ['record' => $order->getKey()])
            ->fillForm([
                'customer_phone' => '+370 600 00000',
                'billing_different' => true,
                'billing_address_line1' => 'Invoices dept',
                'billing_city' => 'Vilnius',
                'billing_country_code' => 'LT',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $order->refresh();
        $this->assertSame('+370 600 00000', $order->customer_phone);
        $this->assertSame('Invoices dept', $order->billing_address_line1);
    }

    // ---- calculator rules ---------------------------------------------------

    #[Test]
    public function an_export_outside_the_eu_is_zero_rated(): void
    {
        $r = app(AdminOrderCalculator::class)->compute(
            [['quantity' => 1, 'unit_price' => '296.87']], 'JP', $this->method->id, null,
        );

        $this->assertSame('0.00', $r['vat_amount']);
        $this->assertSame('309.37', $r['grand_total']); // 296.87 + 12.50 shipping, no VAT
        $this->assertStringContainsString('export', $r['vat_reason']);
    }

    #[Test]
    public function a_vat_exempt_order_carries_no_vat_inside_the_eu(): void
    {
        $r = app(AdminOrderCalculator::class)->compute(
            [['quantity' => 2, 'unit_price' => '50.00']], 'DE', $this->method->id, null, vatExempt: true,
        );

        $this->assertSame('0.00', $r['vat_amount']);
        $this->assertSame('112.50', $r['grand_total']);
    }

    #[Test]
    public function free_shipping_applies_above_the_method_threshold(): void
    {
        $this->method->update(['free_shipping_threshold' => '150.00']);

        $r = app(AdminOrderCalculator::class)->compute(
            [['quantity' => 2, 'unit_price' => '100.00']], 'DE', $this->method->id, null, vatExempt: true,
        );

        $this->assertSame('0.00', $r['shipping_cost']);
    }

    // ---- storefront checkout ------------------------------------------------

    #[Test]
    public function the_phone_and_address_line_two_collected_at_checkout_are_stored_on_the_order(): void
    {
        $user = User::factory()->create();
        $cart = Cart::create(['user_id' => $user->id, 'expires_at' => now()->addDays(7)]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->partA->id, 'quantity' => 1, 'price_at_add' => $this->partA->price]);

        $service = app(CheckoutService::class);
        $checkoutId = $service->start($cart);
        $service->update($checkoutId, [
            'step' => 5,
            'contact_email' => $user->email,
            'contact_phone' => ' +49 30 1234567 ',
            'terms_accepted' => true,
            'shipping_address' => [
                'first_name' => 'Anna', 'last_name' => 'Muster', 'street' => 'Teststrasse 1',
                'address_line2' => 'c/o Muster GmbH', 'city' => 'Berlin', 'postal_code' => '10115', 'country_code' => 'DE',
            ],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'bank_transfer',
        ]);

        $order = $service->createOrder($checkoutId, $user->id);

        $this->assertSame('+49 30 1234567', $order->customer_phone);
        $this->assertSame('c/o Muster GmbH', $order->shipping_address_line2);
    }
}
