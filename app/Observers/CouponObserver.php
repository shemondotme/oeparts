<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Coupon;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class CouponObserver
{
    public function created(Coupon $coupon): void
    {
        $this->log($coupon, 'created', [], $coupon->getAttributes());
        $this->invalidateCache($coupon);
    }

    public function updated(Coupon $coupon): void
    {
        $original = $coupon->getOriginal();
        $changes = $coupon->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($coupon, 'updated', $original, $changes);
        }

        $this->invalidateCache($coupon);
    }

    public function deleted(Coupon $coupon): void
    {
        $this->log($coupon, 'deleted', $coupon->getAttributes(), []);
        $this->invalidateCache($coupon);
    }

    protected function invalidateCache(Coupon $coupon): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("coupon.{$coupon->id}");
            $cache->forget("coupon.code.{$coupon->code}");
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(Coupon $coupon, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($coupon),
                'model_id' => $coupon->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
