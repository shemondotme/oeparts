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

    /**
     * Jobs whose failure must never trigger a new admin notification —
     * both of these ARE the notification-delivery mechanism itself, so
     * notifying about their failure risks a self-perpetuating loop: job
     * fails -> notify admins -> that notification's own delivery job
     * fails for the same underlying reason -> notify admins again. Confirmed
     * this actually happened in practice (a transient log-write contention
     * issue made Filament's own DatabaseNotificationsSent broadcast job
     * fail repeatedly, each failure spawning 5 more "Queue job failed"
     * notifications per active admin). A failure here is still recorded in
     * failed_jobs either way — that's what admins should check.
     */
    private const EXCLUDED_JOB_NAMES = [
        'DatabaseNotificationsSent',
        'SendQueuedNotifications',
    ];

    public function handle(JobFailed $event): void
    {
        try {
            $jobName = class_basename($event->job->resolveName());

            if (in_array($jobName, self::EXCLUDED_JOB_NAMES, true)) {
                return;
            }

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
