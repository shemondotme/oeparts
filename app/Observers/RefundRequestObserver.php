<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\RefundRequest;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class RefundRequestObserver
{
    public function created(RefundRequest $refundRequest): void
    {
        $this->log($refundRequest, 'created', [], $refundRequest->getAttributes());
        $this->invalidateCache($refundRequest);
    }

    public function updated(RefundRequest $refundRequest): void
    {
        $original = $refundRequest->getOriginal();
        $changes = $refundRequest->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($refundRequest, 'updated', $original, $changes);
        }

        $this->invalidateCache($refundRequest);
    }

    public function deleted(RefundRequest $refundRequest): void
    {
        $this->log($refundRequest, 'deleted', $refundRequest->getAttributes(), []);
        $this->invalidateCache($refundRequest);
    }

    protected function invalidateCache(RefundRequest $refundRequest): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("order.{$refundRequest->order_id}");
            $cache->forget("refund_request.{$refundRequest->id}");
            $cache->forget('admin:dashboard:refund_requests');
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(RefundRequest $refundRequest, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($refundRequest),
                'model_id' => $refundRequest->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
