<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Models\Admin;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $service;
    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CouponService::class);
        $this->adminId = Admin::factory()->create()->id;
    }

    // -------------------------------------------------------------------------
    // validate() — happy paths
    // -------------------------------------------------------------------------

    #[Test]
    public function valid_percentage_coupon_returns_correct_discount(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'       => $this->adminId,
            'discount_type'    => DiscountType::Percentage,
            'discount_value'   => 10,
            'min_order_amount' => null,
            'usage_limit'      => null,
            'expires_at'       => now()->addDays(30),
            'is_active'        => true,
        ]);

        $result = $this->service->validate($coupon->code, '200.00', null);

        $this->assertTrue($result['valid']);
        $this->assertEquals('20.00', $result['discount']);
    }

    #[Test]
    public function valid_fixed_coupon_returns_correct_discount(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'       => $this->adminId,
            'discount_type'    => DiscountType::Fixed,
            'discount_value'   => 25,
            'min_order_amount' => null,
            'usage_limit'      => null,
            'expires_at'       => null,
            'is_active'        => true,
        ]);

        $result = $this->service->validate($coupon->code, '100.00', null);

        $this->assertTrue($result['valid']);
        $this->assertEquals('25.00', $result['discount']);
    }

    #[Test]
    public function fixed_coupon_caps_discount_at_subtotal(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'       => $this->adminId,
            'discount_type'    => DiscountType::Fixed,
            'discount_value'   => 150,
            'min_order_amount' => null,
            'usage_limit'      => null,
            'expires_at'       => null,
            'is_active'        => true,
        ]);

        $result = $this->service->validate($coupon->code, '100.00', null);

        $this->assertTrue($result['valid']);
        $this->assertEquals('100.00', $result['discount']);
    }

    // -------------------------------------------------------------------------
    // validate() — rejection cases
    // -------------------------------------------------------------------------

    #[Test]
    public function unknown_coupon_code_is_invalid(): void
    {
        $result = $this->service->validate('DOESNOTEXIST', '100.00', null);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Invalid', $result['message']);
    }

    #[Test]
    public function inactive_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->inactive()->create(['created_by' => $this->adminId]);

        $result = $this->service->validate($coupon->code, '100.00', null);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('no longer active', $result['message']);
    }

    #[Test]
    public function expired_coupon_is_rejected(): void
    {
        $coupon = Coupon::factory()->expired()->create(['created_by' => $this->adminId, 'is_active' => true]);

        $result = $this->service->validate($coupon->code, '100.00', null);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    #[Test]
    public function coupon_below_minimum_order_amount_is_rejected(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'       => $this->adminId,
            'min_order_amount' => '200.00',
            'usage_limit'      => null,
            'expires_at'       => null,
            'is_active'        => true,
        ]);

        $result = $this->service->validate($coupon->code, '150.00', null);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order', $result['message']);
    }

    #[Test]
    public function coupon_at_usage_limit_is_rejected(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'       => $this->adminId,
            'usage_limit'      => 2,
            'min_order_amount' => null,
            'expires_at'       => null,
            'is_active'        => true,
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'used_at' => now()]);
        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'used_at' => now()]);

        $result = $this->service->validate($coupon->code, '100.00', $user->id);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('usage limit', $result['message']);
    }

    #[Test]
    public function coupon_exceeding_per_user_limit_is_rejected(): void
    {
        $coupon = Coupon::factory()->create([
            'created_by'           => $this->adminId,
            'usage_limit_per_user' => 1,
            'usage_limit'          => null,
            'min_order_amount'     => null,
            'expires_at'           => null,
            'is_active'            => true,
        ]);

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'used_at' => now()]);

        $result = $this->service->validate($coupon->code, '100.00', $user->id);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used', $result['message']);
    }

    // -------------------------------------------------------------------------
    // apply() — records usage
    // -------------------------------------------------------------------------

    #[Test]
    public function apply_records_coupon_usage(): void
    {
        $coupon = Coupon::factory()->create(['created_by' => $this->adminId, 'usage_limit' => null]);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->service->apply($coupon, $order);

        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'order_id'  => $order->id,
            'user_id'   => $user->id,
        ]);
    }

    #[Test]
    public function apply_does_not_record_usage_when_limit_already_reached(): void
    {
        $coupon = Coupon::factory()->create(['created_by' => $this->adminId, 'usage_limit' => 1]);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $user->id, 'order_id' => $order->id, 'used_at' => now()]);

        $this->service->apply($coupon, $order);

        $this->assertDatabaseCount('coupon_usages', 1);
    }
}
