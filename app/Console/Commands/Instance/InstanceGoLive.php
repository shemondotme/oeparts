<?php

namespace App\Console\Commands\Instance;

use App\Services\Instance\InstancePromoter;
use App\Services\Instance\PromotionBlockedException;
use App\Services\Migration\ExitCode;
use Illuminate\Console\Command;

/**
 * Takes a staging copy live as the real shop. It refuses unless the host of the site is typed, nothing was done on the copy that
 * would be carried into the shop (or --discard-activity says to throw it away), real card payments are confirmed on purpose
 * (--confirm-live-payments), and every launch check is clear. The queue workers are restarted first and the mode is not kept
 * unless a worker confirms it.
 *
 * Exit codes: 0 live; 2 wrong options; 3 something blocks it.
 */
class InstanceGoLive extends Command
{
    protected $signature = 'oeparts:instance:go-live
        {--host= : The host of this site, typed out, to confirm}
        {--discard-activity : Delete what customers and visitors created on the copy, and put the numbering back}
        {--confirm-live-payments : Confirm that real card payments will be taken}
        {--waive=* : A check to waive, as key:reason (only checks marked overridable)}
        {--json : Print one JSON document instead of text}';

    protected $description = 'Take a staging copy live as the real shop';

    public function handle(InstancePromoter $promoter): int
    {
        $waivers = GoLiveOptions::waivers((array) $this->option('waive'));

        if ($waivers === null || (string) $this->option('host') === '') {
            $this->components->error('Type the host with --host=your.site.example, and waive with --waive=key:reason.');

            return ExitCode::Usage->value;
        }

        try {
            $result = $promoter->goLive((string) $this->option('host'), [
                'discard_activity' => (bool) $this->option('discard-activity'),
                'confirm_payments' => (bool) $this->option('confirm-live-payments'),
                'waivers' => $waivers,
            ], 'command line');
        } catch (PromotionBlockedException $e) {
            if ($this->option('json')) {
                $this->line((string) json_encode(['live' => false, 'reason' => $e->reason, 'message' => $e->getMessage(), 'details' => $e->details], JSON_UNESCAPED_SLASHES));
            } else {
                $this->components->error($e->getMessage());
            }

            return ExitCode::PreflightBlocked->value;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['live' => true] + $result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('The site is live.');
        }

        return ExitCode::Ok->value;
    }
}
