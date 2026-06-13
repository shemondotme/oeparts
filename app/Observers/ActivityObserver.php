<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityObserver
{
    public function created(Model $model): void
    {
        if ($model instanceof ActivityLog) return;
        $this->log($model, 'created', [], $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        if ($model instanceof ActivityLog) return;
        $original = $model->getOriginal();
        $changes = $model->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($model, 'updated', $original, $changes);
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof ActivityLog) return;
        $this->log($model, 'deleted', $model->getAttributes(), []);
    }

    protected function log(Model $model, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail — never break CRUD operations
        }
    }
}
