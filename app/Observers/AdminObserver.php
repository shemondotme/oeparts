<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminObserver
{
    public function created(Admin $admin): void
    {
        $this->log($admin, 'created', [], $admin->getAttributes());
        $this->invalidateCache();
    }

    public function updated(Admin $admin): void
    {
        $original = $admin->getOriginal();
        $changes = $admin->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($admin, 'updated', $original, $changes);
        }

        $this->invalidateCache();
    }

    public function deleted(Admin $admin): void
    {
        $this->log($admin, 'deleted', $admin->getAttributes(), []);
        $this->invalidateCache();
    }

    protected function invalidateCache(): void
    {
        try {
            Cache::forget('admin:dashboard:admin_count');
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(Admin $admin, string $action, array $old, array $new): void
    {
        try {
            $authAdmin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $authAdmin?->id,
                'action' => $action,
                'model_type' => get_class($admin),
                'model_id' => $admin->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
