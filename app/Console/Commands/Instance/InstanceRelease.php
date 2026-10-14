<?php

namespace App\Console\Commands\Instance;

use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstancePromoter;
use App\Services\Instance\InstanceState;
use App\Services\Instance\PromotionBlockedException;
use App\Services\Migration\ExitCode;
use Illuminate\Console\Command;

/**
 * Lets a site that an import left in quarantine out of it, once every launch check is clear: to production (a live launch) or to
 * staging (a copy). A check that can be waived is waived with --waive=key:reason, and the reason is kept; the one about the queue
 * worker's mode never can be.
 *
 * Exit codes: 0 released; 2 wrong options; 3 the checks block it (or a worker kept the old mode).
 */
class InstanceRelease extends Command
{
    protected $signature = 'oeparts:instance:release
        {--to= : production or staging (default: what the import was started for)}
        {--waive=* : A check to waive, as key:reason (only checks marked overridable)}
        {--json : Print one JSON document instead of text}';

    protected $description = 'Release a site from quarantine once the launch checks are clear';

    public function handle(InstancePromoter $promoter, InstanceState $ledger): int
    {
        $to = InstanceMode::tryFrom((string) ($this->option('to') ?: ($ledger->read()['import']['intent'] ?? 'production')));
        $waivers = GoLiveOptions::waivers((array) $this->option('waive'));

        if ($to === null || $waivers === null) {
            $this->components->error('Use --to=production or --to=staging, and waive with --waive=key:reason.');

            return ExitCode::Usage->value;
        }

        try {
            $result = $promoter->release($to, $waivers, 'command line');
        } catch (PromotionBlockedException $e) {
            return $this->blocked($e);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['released' => true] + $result, JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info("The site is now {$result['to']}.");
        }

        return ExitCode::Ok->value;
    }

    private function blocked(PromotionBlockedException $e): int
    {
        if ($this->option('json')) {
            $this->line((string) json_encode(['released' => false, 'reason' => $e->reason, 'message' => $e->getMessage(), 'details' => $e->details], JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->error($e->getMessage());
        }

        return ExitCode::PreflightBlocked->value;
    }
}
