<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\Section;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class SectionObserver
{
    public function created(Section $section): void
    {
        $this->log($section, 'created', [], $section->getAttributes());
        $this->invalidateCache($section);
    }

    public function updated(Section $section): void
    {
        $original = $section->getOriginal();
        $changes = $section->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($section, 'updated', $original, $changes);
        }

        $this->invalidateCache($section);
    }

    public function deleted(Section $section): void
    {
        $this->log($section, 'deleted', $section->getAttributes(), []);
        $this->invalidateCache($section);
    }

    protected function invalidateCache(Section $section): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("section.{$section->id}");

            if ($section->location) {
                $cache->forgetSections($section->location->value);
            }
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(Section $section, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($section),
                'model_id' => $section->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
