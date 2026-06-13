<?php

namespace App\Observers;

use App\Models\ActivityLog;
use App\Models\NewsletterCampaign;
use App\Services\CacheService;
use Illuminate\Support\Facades\Auth;

class NewsletterCampaignObserver
{
    public function created(NewsletterCampaign $campaign): void
    {
        $this->log($campaign, 'created', [], $campaign->getAttributes());
        $this->invalidateCache($campaign);
    }

    public function updated(NewsletterCampaign $campaign): void
    {
        $original = $campaign->getOriginal();
        $changes = $campaign->getChanges();

        unset($changes['updated_at']);
        unset($original['updated_at']);

        if (!empty($changes)) {
            $this->log($campaign, 'updated', $original, $changes);
        }

        $this->invalidateCache($campaign);
    }

    public function deleted(NewsletterCampaign $campaign): void
    {
        $this->log($campaign, 'deleted', $campaign->getAttributes(), []);
        $this->invalidateCache($campaign);
    }

    protected function invalidateCache(NewsletterCampaign $campaign): void
    {
        try {
            $cache = app(CacheService::class);

            $cache->forget("newsletter_campaign.{$campaign->id}");
            $cache->forget('newsletter_campaigns.active');
            $cache->forget('admin:dashboard:newsletter_campaigns');
        } catch (\Exception $e) {
            // Cache failure must not break CRUD
        }
    }

    protected function log(NewsletterCampaign $campaign, string $action, array $old, array $new): void
    {
        try {
            $admin = Auth::guard('admin')->user();

            ActivityLog::create([
                'admin_id' => $admin?->id,
                'action' => $action,
                'model_type' => get_class($campaign),
                'model_id' => $campaign->getKey(),
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Exception $e) {
            // Silently fail
        }
    }
}
