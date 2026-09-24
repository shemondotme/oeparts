<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Services\Checkout\CheckoutStateStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CouponService
{
    /**
     * Validate a coupon code against all eligibility rules.
     */
    public function validate(string $code, string $subtotal, ?int $userId, ?string $email = null, ?string $ipAddress = null): array
    {
        // 1. Coupon exists
        $coupon = app(CacheService::class)->rememberCouponByCode(
            $code,
            fn () => Coupon::where('code', $code)->first(),
        );
        if (! $coupon) {
            return [
                'valid' => false,
                'coupon' => null,
                'discount' => null,
                'message' => 'Invalid coupon code.',
            ];
        }

        return $this->validateCoupon($coupon, $subtotal, $userId, $email, $ipAddress);
    }

    /**
     * Same eligibility checks as validate(), against an already-resolved
     * Coupon rather than a code. Split out so CheckoutService::createOrder()
     * can re-run every check (including a fresh discount calculation)
     * against the cart's subtotal at the moment of order creation, not the
     * subtotal captured whenever the coupon was first applied earlier in
     * checkout — the customer may have changed cart contents since then,
     * which would otherwise leave a stale discount amount on the order.
     *
     * $email/$ipAddress are optional, additional identity signals for the
     * per-user usage-limit check below (multi-account abuse mitigation) —
     * callers that don't have them yet (e.g. the cart-page coupon preview,
     * before a guest has entered an email) simply fall back to the
     * user_id-only check, same as before this existed.
     */
    public function validateCoupon(Coupon $coupon, string $subtotal, ?int $userId, ?string $email = null, ?string $ipAddress = null): array
    {
        // 2. Is active
        if (! $coupon->is_active) {
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
        //
        // Checked by every identity signal available, not just user_id: a
        // bare user_id check is trivially defeated by creating a new
        // account (multi-account coupon abuse — [[project_bulletproof_testing_2026_09]]
        // Phase 9 found this and the user explicitly accepted the risk at
        // the time; later revisited and closed). Email and IP address are
        // both captured on every order already (orders.guest_email/
        // ip_address), so this needs no new columns — just checking more
        // of what's already there.
        if ($coupon->usage_limit_per_user !== null && $coupon->usage_limit_per_user > 0
            && ($userId !== null || $email !== null || $ipAddress !== null)) {
            $userUsageCount = $this->usageCountForIdentity($coupon->id, $userId, $email, $ipAddress);
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
     * How many times this coupon has already been used by whichever of
     * $userId/$email/$ipAddress are given — email and IP are matched via
     * the usage's linked order (orders.guest_email/ip_address, or the
     * order's own user's email for an account that later signed in under
     * a different identity than $userId).
     */
    private function usageCountForIdentity(int $couponId, ?int $userId, ?string $email, ?string $ipAddress): int
    {
        return CouponUsage::where('coupon_id', $couponId)
            ->where(function ($query) use ($userId, $email, $ipAddress) {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }
                if ($email !== null || $ipAddress !== null) {
                    $query->orWhereHas('order', function ($orderQuery) use ($email, $ipAddress) {
                        $orderQuery->where(function ($q) use ($email, $ipAddress) {
                            if ($email !== null) {
                                $q->where('guest_email', $email)
                                    ->orWhereHas('user', fn ($uq) => $uq->where('email', $email));
                            }
                            if ($ipAddress !== null) {
                                $q->orWhere('ip_address', $ipAddress);
                            }
                        });
                    });
                }
            })
            ->count();
    }

    /**
     * Calculate discount amount based on coupon type.
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
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'used_at' => now(),
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
     * Remove the applied coupon from the checkout state.
     */
    public function remove(string $checkoutId): void
    {
        app(CheckoutStateStore::class)->mutate($checkoutId, function (array &$state): bool {
            unset($state['data']['coupon_id'], $state['data']['discount_amount']);

            return true;
        });
    }
}
