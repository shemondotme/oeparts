<?php

namespace App\Services\Cleanup\Targets;

use App\Services\Cleanup\CleanupContext;
use App\Services\Cleanup\Contracts\CleanupTarget;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read dashboard notifications older than the retention.
 *
 * Bespoke rather than a ConfiguredDbTarget because the table's primary key is a UUID, so the engine's
 * numeric id-window walk does not apply. The table is small (admin bell notifications only) and the
 * qualifying set shrinks as it is deleted, so "pick the oldest N qualifying ids, delete them, repeat"
 * converges without a cursor.
 *
 * Only notifications that have been READ qualify: an unread one is still something an admin has not
 * seen. The `data` column can hold customer names/emails (contact messages, part inquiries, refunds),
 * so this target never archives its rows, even when "export before delete" is on.
 */
final class NotificationsTarget implements CleanupTarget
{
    public function key(): string
    {
        return 'notifications';
    }

    public function label(): string
    {
        return 'Read notifications';
    }

    public function description(): string
    {
        return 'Bell notifications the admins have already read. Unread ones are never touched.';
    }

    public function group(): string
    {
        return 'system';
    }

    public function kind(): string
    {
        return 'db';
    }

    public function defaultDays(): int
    {
        return 90;
    }

    public function floorDays(): int
    {
        return 7;
    }

    public function truncatable(): bool
    {
        return false;
    }

    public function reportOnly(): bool
    {
        return false;
    }

    public function autoByDefault(): bool
    {
        return true;
    }

    public function needsBackupLock(): bool
    {
        return false;
    }

    public function totalRows(): ?int
    {
        try {
            return DB::table('notifications')->count();
        } catch (Throwable $e) {
            Log::channel('cleanup')->warning('Could not count notifications: '.$e->getMessage());

            return null;
        }
    }

    public function begin(CleanupContext $ctx): array
    {
        return ['deleted' => 0, 'failed' => 0];
    }

    public function plan(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['targeted'] = $this->qualifying($ctx)->count();

        return true;
    }

    public function delete(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $chunk = max(200, $ctx->maxChunk);

        while (true) {
            if ($ctx->cancelRequested()) {
                return false;
            }

            $ids = $this->qualifying($ctx)->orderBy('created_at')->limit($chunk)->pluck('id')->all();
            if ($ids === []) {
                return true;
            }

            try {
                $deleted = DB::table('notifications')->whereIn('id', $ids)->delete();
            } catch (Throwable $e) {
                Log::channel('cleanup')->warning('Could not delete a chunk of notifications: '.$e->getMessage());
                $cp['failed'] = (int) $cp['failed'] + count($ids);

                return true; // give up on this item; verify will count what is left
            }

            $cp['deleted'] = (int) $cp['deleted'] + $deleted;

            if ($deleted === 0) {
                // The ids vanished between the two statements; do not spin if that keeps happening.
                $cp['failed'] = (int) $cp['failed'] + count($ids);

                return true;
            }

            if ($ctx->sleepMs > 0) {
                usleep($ctx->sleepMs * 1000);
            }

            if (microtime(true) >= $deadline) {
                return false;
            }
        }
    }

    public function verify(CleanupContext $ctx, array &$cp, float $deadline): bool
    {
        $cp['remaining'] = $this->qualifying($ctx)->count();
        $cp['late_rows'] = 0;

        return true;
    }

    /** @return Builder */
    private function qualifying(CleanupContext $ctx)
    {
        return DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $ctx->cutoff);
    }
}
