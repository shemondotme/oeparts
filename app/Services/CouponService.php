<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CouponService
{
    /**
     * Validate a coupon code against all eligibility rules.
     *
     * @param string $code
     * @param string $subtotal
     * @param int|null $userId
     * @return array
     */
    public function validate(string $code, string $subtotal, ?int $userId): array
    {
        // 1. Coupon exists
        $coupon = app(CacheService::class)->rememberCouponByCode(
            $code,
            fn () => Coupon::where('code', $code)->first(),
        );
        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => null,
                'message' => 'Invalid coupon code.',
            ];
        }

        return $this->validateCoupon($coupon, $subtotal, $userId);
    }

    /**
     * Same eligibility checks as validate(), against an already-resolved
     * Coupon rather than a code. Split out so CheckoutService::createOrder()
     * can re-run every check (including a fresh discount calculation)
     * against the cart's subtotal at the moment of order creation, not the
     * subtotal captured whenever the coupon was first applied earlier in
     * checkout — the customer may have changed cart contents since then,
     * which would otherwise leave a stale discount amount on the order.
     */
    public function validateCoupon(Coupon $coupon, string $subtotal, ?int $userId): array
    {
        // 2. Is active
        if (!$coupon->is_active) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => null,
                'message' => 'This coupon is no longer active.',
            ];
        }

        // 2b. Personal coupon — restricted to one customer
        if ($coupon->user_id !== null) {
            if ($userId === null) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount' => null,
                    'message' => 'This coupon requires you to be signed in.',
                ];
            }
            if ((int) $coupon->user_id !== $userId) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount' => null,
                    'message' => 'This coupon is not valid for your account.',
                ];
            }
        }

        // 3. Not expired
        if ($coupon->expires_at && now()->gt($coupon->expires_at)) {
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => null,
                'message' => 'This coupon has expired.',
            ];
        }

        // 4. Minimum order amount
        if ($coupon->min_order_amount !== null &&
            bccomp($subtotal, (string) $coupon->min_order_amount, 2) === -1) {
            $min = number_format($coupon->min_order_amount, 2);
            return [
                'valid' => false,
                'coupon' => $coupon,
                'discount' => null,
                'message' => "Minimum order of €{$min} required.",
            ];
        }

        // 5. Usage limit — the admin form documents 0 the same as null:
        // "Maximum total times this coupon can be used. 0 = unlimited."
        if ($coupon->usage_limit !== null && $coupon->usage_limit > 0) {
            $usageCount = CouponUsage::where('coupon_id', $coupon->id)->count();
            if ($usageCount >= $coupon->usage_limit) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount' => null,
                    'message' => 'Coupon usage limit reached.',
                ];
            }
        }

        // 6. Usage limit per user — same "0 = unlimited" convention. Without
        // the > 0 guard this treated 0 as "0 uses allowed", blocking every
        // customer (including first-time ones) from a coupon the admin
        // intended to leave uncapped per-user.
        if ($coupon->usage_limit_per_user !== null && $coupon->usage_limit_per_user > 0 && $userId !== null) {
            $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                ->where('user_id', $userId)
                ->count();
            if ($userUsageCount >= $coupon->usage_limit_per_user) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount' => null,
                    'message' => 'You have already used this coupon.',
                ];
            }
        }

        // All rules passed — calculate discount
        $discount = $this->calculateDiscount($coupon, $subtotal);

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount' => $discount,
            'message' => null,
        ];
    }

    /**
     * Calculate discount amount based on coupon type.
     *
     * @param Coupon $coupon
     * @param string $subtotal
     * @return string
     */
    private function calculateDiscount(Coupon $coupon, string $subtotal): string
    {
        if ($coupon->discount_type === DiscountType::Percentage) {
            $rate = bcdiv((string) $coupon->discount_value, '100', 4);
            return bcmul($subtotal, $rate, 2);
        }

        // Fixed discount — cap at subtotal so it never goes negative
        if (bccomp((string) $coupon->discount_value, $subtotal, 2) >= 0) {
            return $subtotal;
        }

        return (string) $coupon->discount_value;
    }

    /**
     * Record coupon usage after an order is placed.
     *
     * @param Coupon $coupon
     * @param Order $order
     * @return void
     */
    public function apply(Coupon $coupon, Order $order): void
    {
        try {
            DB::transaction(function () use ($coupon, $order) {
                // Lock the Coupon row itself, not the CouponUsage children —
                // locking rows that may not exist yet locks nothing at all.
                // A brand-new usage_limit=1 coupon has zero CouponUsage rows,
                // so two concurrent first-time redemptions each counted 0
                // existing (locked) rows and both proceeded to insert,
                // silently allowing 2 uses of a single-use coupon. The Coupon
                // row always exists, so locking it actually serializes
                // concurrent apply() calls for the same coupon.
                $lockedCoupon = Coupon::where('id', $coupon->id)->lockForUpdate()->first();

                // usage_limit/usage_limit_per_user: 0 means unlimited, same
                // as validateCoupon() and the admin form's own documented
                // convention ("0 = unlimited") — a bare `!== null` check
                // would reject every single redemption of a coupon left at
                // its default 0.
                if ($lockedCoupon->usage_limit !== null && $lockedCoupon->usage_limit > 0) {
                    $usageCount = CouponUsage::where('coupon_id', $coupon->id)->count();
                    if ($usageCount >= $lockedCoupon->usage_limit) {
                        throw new \Exception('Coupon usage limit reached');
                    }
                }

                if ($lockedCoupon->usage_limit_per_user !== null && $lockedCoupon->usage_limit_per_user > 0 && $order->user_id !== null) {
                    $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)
                        ->where('user_id', $order->user_id)
                        ->count();
                    if ($userUsageCount >= $lockedCoupon->usage_limit_per_user) {
                        throw new \Exception('Coupon per-user usage limit reached');
                    }
                }

                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id'   => $order->user_id,
                    'order_id'  => $order->id,
                    'used_at'   => now(),
                ]);
            });
        } catch (\Exception $e) {
            // Not rethrown: by this point the order is already created with
            // this coupon's discount baked into grand_total (CheckoutService
            // re-validates and recomputes the discount immediately before
            // this call), so failing loudly here would mean charging the
            // customer the discounted price but then blowing up their
            // otherwise-successful checkout over a bookkeeping write. A
            // failure here (in practice, only a genuine last-slot race) means
            // usage tracking under-counts for this coupon — worth paging
            // someone, not just a log line easy to miss.
            report($e);
            Log::warning('Failed to record coupon usage', [
                'coupon_id' => $coupon->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove coupon from session.
     *
     * @param string $checkoutId
     * @return void
     */
    public function remove(string $checkoutId): void
    {
        Session::forget("checkout.{$checkoutId}.data.coupon_id");
        Session::forget("checkout.{$checkoutId}.data.discount_amount");
    }
}