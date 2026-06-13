<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserObserver
{
    public function created(User $user): void
    {
        $this->log($user, 'created', [], $user->getAttributes());
    }

    public function updated(User $user): void
    {
        $original = $user->getOriginal();
        $changes = $user->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($user, 'updated', $original, $changes);
        }

        Cache::forget("user:{$user->id}");
    }

    public function deleted(User $user): void
    {
        $this->log($user, 'deleted', $user->getAttributes(), []);
        Cache::forget("user:{$user->id}");
    }

    protected function log(User $user, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($user),
                'model_id' => $user->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
