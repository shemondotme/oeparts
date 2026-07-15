<?php

namespace Tests\Feature;

use App\Filament\Resources\CarrierResource\Pages\CreateCarrier;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\ShippingZoneResource\Pages\CreateShippingZone;
use App\Models\Admin;
use App\Models\Carrier;
use App\Models\Coupon;
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
}
