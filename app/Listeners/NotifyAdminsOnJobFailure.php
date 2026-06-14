<?php

namespace App\Listeners;

use App\Enums\AdminNotificationCategory;
use App\Models\Admin;
use App\Services\AdminNotificationService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class NotifyAdminsOnJobFailure
{
    public function __construct(
        private readonly AdminNotificationService $notificationService,
    ) {}

    public function handle(JobFailed $event): void
    {
        try {
            $jobName = class_basename($event->job->resolveName());

            $this->notificationService->createForAll(
                category:  AdminNotificationCategory::System,
                title:     "Queue job failed: {$jobName}",
                detail:    substr($event->exception->getMessage(), 0, 120),
                actionUrl: '/admin/system/failed-jobs',
            );
        } catch (\Throwable $e) {
            Log::error('NotifyAdminsOnJobFailure: ' . $e->getMessage());
        }
    }
}
