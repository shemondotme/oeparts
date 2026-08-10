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
        $this->log($admin, 'created', [], $this->redact($admin, $admin->getAttributes()));
        $this->invalidateCache();
    }

    public function updated(Admin $admin): void
    {
        $original = $admin->getOriginal();
        $changes = $admin->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($admin, 'updated', $this->redact($admin, $original), $this->redact($admin, $changes));
        }

        $this->invalidateCache();
    }

    public function deleted(Admin $admin): void
    {
        $this->log($admin, 'deleted', $this->redact($admin, $admin->getAttributes()), []);
        $this->invalidateCache();
    }

    /**
     * getAttributes()/getChanges()/getOriginal() bypass $hidden (unlike
     * toArray()/JSON serialization), so without this the admin's password
     * hash and remember_token would be written into activity_logs in the
     * clear — visible to any admin with "view activity logs" permission,
     * not just super_admin, and a leaked remember_token can forge a
     * persistent-login cookie for this admin outright.
     */
    protected function redact(Admin $admin, array $attributes): array
    {
        foreach ($admin->getHidden() as $key) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                $attributes[$key] = '***';
            }
        }

        return $attributes;
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
