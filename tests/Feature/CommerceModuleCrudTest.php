<?php

namespace Tests\Feature;

use App\Filament\Resources\CarrierResource\Pages\CreateCarrier;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\SeoMetaResource\Pages\ListSeoMetas;
use App\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use App\Models\Admin;
use App\Models\Carrier;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for 3 real, confirmed Commerce-module bugs found via
 * a live real-user CRUD sweep (2026-07-15).
 */
class CommerceModuleCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class]);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');
    }

    /**
     * carriers.tracking_url is NOT NULL (migration 2026_03_26_100006) but
     * the field is legitimately optional (a carrier without online tracking
     * is valid) — leaving it blank submitted null and crashed with a raw
     * SQLSTATE constraint failure instead of saving, confirmed live.
     */
    #[Test]
    public function carrier_can_be_created_without_a_tracking_url(): void
    {
        Livewire::test(CreateCarrier::class)
            ->fillForm(['name' => 'Test Carrier', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $carrier = Carrier::where('name', 'Test Carrier')->first();
        $this->assertNotNull($carrier);
        $this->assertSame('', $carrier->tracking_url);
    }

    /**
     * coupons.created_by is a NOT NULL foreign key (migration
     * 2026_03_26_100019) with no form field anywhere and no
     * mutateFormDataBeforeCreate hook to set it — every single coupon
     * creation crashed with a raw SQLSTATE NOT NULL constraint failure,
     * confirmed live. The entire "create coupon" feature was non-functional.
     */
    #[Test]
    public function coupon_creation_sets_created_by_from_the_authenticated_admin(): void
    {
        $admin = auth('admin')->user();

        Livewire::test(CreateCoupon::class)
            ->fillForm([
                'code' => 'TEST10',
                'name' => 'Test Coupon',
                'discount_type' => 'percentage',
                'discount_value' => '10',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $coupon = Coupon::where('code', 'TEST10')->first();
        $this->assertNotNull($coupon, 'Coupon creation failed');
        $this->assertSame($admin->id, $coupon->created_by);
    }

    /**
     * ShippingZone::$casts had 'name' => 'array' on a plain varchar column
     * — a cast only affects Eloquent attribute hydration/dehydration, never
     * query builder where() clauses, so the raw stored value was always
     * JSON-quoted (e.g. '"Europe"') while any ::where('name', 'Europe')
     * compared against the unquoted string and could never match, confirmed
     * live (this dev DB's one real zone was already corrupted this way).
     * Fixed by migration 2026_07_20_000001 (un-quotes existing rows) +
     * removing the cast (the form only ever collected a plain string here —
     * this was never a genuine multilang field).
     */
    #[Test]
    public function shipping_zone_name_is_stored_and_queryable_as_a_plain_string(): void
    {
        Livewire::test(CreateShippingZone::class)
            ->fillForm(['name' => 'Test Zone', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(ShippingZone::where('name', 'Test Zone')->exists());
        $this->assertSame('Test Zone', \DB::table('shipping_zones')->value('name'));
    }

    /**
     * OrderResource's shipping_method_id Select used the relationship's raw
     * 'name' column as the option label — but ShippingMethod.name is
     * multilang JSON (array cast), so Filament's default option-label
     * resolution handed the array to Select::getOptionLabel(), which
     * requires ?string and threw immediately. Manual order creation was
     * completely broken. Fixed with the same getOptionLabelFromRecordUsing
     * override ProductResource's manufacturer_id select already used.
     * Also covers a second bug found in the same flow: urgent_processing_fee
     * has a DB-level default ('0.00') but Filament always submits every
     * schema-declared field, so the untouched (readOnly, no form default)
     * TextInput submitted an explicit null that overrode the DB default.
     */
    #[Test]
    public function order_can_be_manually_created_with_a_shipping_method_selected(): void
    {
        $method = ShippingMethod::factory()->create();

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'order_number' => 'ORD-PLACEHOLDER', // overwritten by SequenceService in mutateFormDataBeforeCreate, but still validated as required first
                'guest_email' => 'manual-order@example.com',
                'shipping_name' => 'Test Customer',
                'shipping_address_line1' => 'Teststrasse 1',
                'shipping_city' => 'Berlin',
                'shipping_postal_code' => '10115',
                'shipping_country_code' => 'DE',
                'shipping_method_id' => $method->id,
                'status' => 'pending',
                'payment_method' => 'bank_transfer',
                'payment_status' => 'pending',
                'subtotal' => '100.00',
                'shipping_cost' => '10.00',
                'vat_amount' => '21.00',
                'grand_total' => '131.00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::where('guest_email', 'manual-order@example.com')->first();
        $this->assertNotNull($order, 'Order creation failed');
        $this->assertSame('0.00', $order->urgent_processing_fee);
    }

    /**
     * seo_meta.metable_type/metable_id are NOT NULL polymorphic-target
     * columns with no way to set them via the create form (they only ever
     * render as read-only context fields for an EXISTING record) — clicking
     * "New" on the SEO Metadata list and saving always crashed with a raw
     * SQLSTATE NOT NULL constraint failure. Removed the reachable-but-
     * guaranteed-broken create entry point (button + route).
     */
    #[Test]
    public function seo_meta_list_no_longer_offers_a_broken_create_action(): void
    {
        Livewire::test(ListSeoMetas::class)->assertActionDoesNotExist('create');

        $this->assertArrayNotHasKey('create', \App\Filament\Resources\SeoMetaResource::getPages());
    }
}
