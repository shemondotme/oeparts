<?php

namespace App\Services;

use App\Models\Admin;
use App\Notifications\SeoAuditAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs the SEO audit unattended and tells the admins when something
 * CRITICAL appears — so a wrong APP_ENV or a "noindex" default is noticed the
 * day it happens, not weeks later in the traffic graph.
 *
 * One alert per problem: it is announced when it first shows up, then only
 * repeated as a reminder after REMINDER_DAYS if still open. What was seen
 * before is kept in the cache — a cleared cache costs at worst one repeated
 * alert, never a missed one.
 */
class SeoAuditMonitor
{
    private const STATE_KEY = 'seo-audit:alert-state';

    private const REMINDER_DAYS = 7;

    public function __construct(private readonly SeoAuditService $audit) {}

    /**
     * @return array{critical: int, announced: list<string>, resolved: list<string>, notified: int}
     */
    public function run(bool $notify = true): array
    {
        $report = $this->audit->run(skipHeavy: true);
        $now = now();
        // Alerts switched off (setting) or a --no-notify run: nothing is sent, so nothing may be marked as announced.
        $willSend = $notify && $this->alertsEnabled();

        /** @var array<string, array{since: string, notified: ?string}> $previous */
        $previous = (array) Cache::get(self::STATE_KEY, []);
        $next = [];
        $toAnnounce = [];

        foreach ($report['checks'] as $check) {
            if ($check['severity'] !== SeoAuditService::CRITICAL) {
                continue;
            }

            $id = $check['id'];
            $seen = $previous[$id] ?? null;
            $isNew = $seen === null;
            $reminderDue = ! $isNew
                && ($seen['notified'] ?? null) !== null
                && Carbon::parse($seen["notified"])->diffInDays($now) >= self::REMINDER_DAYS;
            $neverNotified = ! $isNew && ($seen['notified'] ?? null) === null;

            $entry = ['since' => $seen['since'] ?? $now->toIso8601String(), 'notified' => $seen['notified'] ?? null];

            if ($isNew || $reminderDue || $neverNotified) {
                $toAnnounce[] = ['id' => $id, 'title' => $check['title'], 'detail' => $check['detail'], 'still' => ! $isNew && ! $neverNotified];
                $entry['notified'] = $willSend ? $now->toIso8601String() : $entry['notified'];
            }

            $next[$id] = $entry;
        }

        $resolved = array_values(array_diff(array_keys($previous), array_keys($next)));

        // Only remember state we actually acted on: a --no-notify run must not swallow the alert.
        Cache::forever(self::STATE_KEY, $next);

        $notified = 0;
        if ($willSend && $toAnnounce !== []) {
            $notified = $this->send($toAnnounce);
        }

        return [
            'critical' => count($next),
            'announced' => array_column($toAnnounce, 'id'),
            'resolved' => $resolved,
            'notified' => $notified,
        ];
    }

    private function alertsEnabled(): bool
    {
        return filter_var(settings('seo.audit_alerts_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** @param list<array{id: string, title: string, detail: string, still: bool}> $issues */
    private function send(array $issues): int
    {
        try {
            $admins = Admin::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin']))
                ->get();

            if ($admins->isEmpty()) {
                return 0;
            }

            $alert = new SeoAuditAlert(
                array_map(fn (array $i) => ['title' => $i['title'], 'detail' => $i['detail'], 'still' => $i['still']], $issues),
                (string) settings('general.site_name', config('app.name', 'OeParts'))
            );

            foreach ($admins as $admin) {
                $admin->notify($alert);
            }

            return $admins->count();
        } catch (\Throwable $e) {
            // An alert must never break the scheduler.
            Log::warning('SEO audit alert could not be sent: '.$e->getMessage());

            return 0;
        }
    }
}
