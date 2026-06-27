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
        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => null,
                'message' => 'Invalid coupon code.',
            ];
        }

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

        // 5. Usage limit
        if ($coupon->usage_limit !== null) {
            try {
                DB::transaction(function () use ($coupon) {
                    $usageCount = CouponUsage::where('coupon_id', $coupon->id)->lockForUpdate()->count();
                    if ($coupon->usage_limit && $usageCount >= $coupon->usage_limit) {
                        throw new \Exception('Coupon usage limit reached');
                    }
                });
            } catch (\Exception $e) {
                return [
                    'valid' => false,
                    'coupon' => $coupon,
                    'discount' => null,
                    'message' => 'Coupon usage limit reached.',
                ];
            }
        }

        // 6. Usage limit per user
        if ($coupon->usage_limit_per_user !== null && $userId !== null) {
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
                $usageCount = CouponUsage::where('coupon_id', $coupon->id)->lockForUpdate()->count();
                if ($coupon->usage_limit !== null && $usageCount >= $coupon->usage_limit) {
                    throw new \Exception('Coupon usage limit reached');
                }

                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'user_id'   => $order->user_id,
                    'order_id'  => $order->id,
                    'used_at'   => now(),
                ]);
            });
        } catch (\Exception $e) {
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