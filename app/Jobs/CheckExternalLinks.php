<?php

namespace App\Jobs;

use App\Services\ExternalLinkChecker;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** The audit page's "Check external links now" — the slow network part, off the request. */
class CheckExternalLinks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public ?int $requestedBy = null)
    {
        $this->onQueue('default');
    }

    public function handle(ExternalLinkChecker $checker): void
    {
        $result = $checker->run();
        Cache::forget(\App\Services\SeoAuditService::LINKS_CACHE_KEY);

        try {
            $broken = count($result['broken']);

            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('External link check finished')
                    ->viewData(['push_topic' => 'task_completed'])
                    ->body($broken === 0
                        ? "Checked {$result['checked']} link(s) to other websites — none are broken."
                        : "Checked {$result['checked']} link(s) to other websites — {$broken} broken. See Settings → SEO Audit → Links.")
                    ->icon('heroicon-o-link')
                    ->iconColor($broken === 0 ? 'success' : 'warning')
            );
        } catch (Throwable $e) {
            Log::warning('Failed to send the external-link-check notification', ['error' => $e->getMessage()]);
        }
    }
}
