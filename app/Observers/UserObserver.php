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
        $this->log($user, 'created', [], $this->redact($user, $user->getAttributes()));
    }

    public function updated(User $user): void
    {
        $original = $user->getOriginal();
        $changes = $user->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($user, 'updated', $this->redact($user, $original), $this->redact($user, $changes));
        }

        Cache::forget("user:{$user->id}");
    }

    public function deleted(User $user): void
    {
        $this->log($user, 'deleted', $this->redact($user, $user->getAttributes()), []);
        Cache::forget("user:{$user->id}");
    }

    /**
     * getAttributes()/getChanges()/getOriginal() bypass $hidden (unlike
     * toArray()/JSON serialization), so without this the customer's
     * password hash and remember_token would be written into activity_logs
     * in the clear — visible to any admin with "view activity logs"
     * permission, and a leaked remember_token can forge a persistent-login
     * cookie for this account outright.
     */
    protected function redact(User $user, array $attributes): array
    {
        foreach ($user->getHidden() as $key) {
            if (array_key_exists($key, $attributes) && $attributes[$key] !== null) {
                $attributes[$key] = '***';
            }
        }

        return $attributes;
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
