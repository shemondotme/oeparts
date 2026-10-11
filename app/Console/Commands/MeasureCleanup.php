<?php

namespace App\Console\Commands;

use App\Services\Cleanup\CleanupMeasurer;
use App\Services\Cleanup\TargetRegistry;
use Illuminate\Console\Command;

/**
 * oeparts:cleanup:measure — refresh the cached sizes shown on the Cleanup page and record a disk sample for
 * the growth trend. Daily; the page never measures while it renders.
 */
class MeasureCleanup extends Command
{
    protected $signature = 'oeparts:cleanup:measure
        {target? : Measure only this target key}
        {--budget=5 : Seconds each target / folder may take (results beyond it are left "unknown", not guessed)}
        {--no-disk : Skip the disk sample}';

    protected $description = 'Refresh the Cleanup page size figures and record a disk-usage sample';

    public function handle(CleanupMeasurer $measurer, TargetRegistry $registry): int
    {
        $budget = max(0.5, (float) $this->option('budget'));

        if ($this->argument('target')) {
            try {
                $measurer->measureTarget($registry->get((string) $this->argument('target')), $budget);
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info('Measured '.$this->argument('target').'.');

            return self::SUCCESS;
        }

        $count = $measurer->measureAll($budget);
        $this->info("Measured {$count} cleanup target(s).");

        if (! $this->option('no-disk')) {
            $sample = $measurer->sampleDisk($budget);
            $this->info(sprintf('Disk sample recorded: %s used of %s.', $this->bytes($sample->used_bytes), $this->bytes($sample->total_bytes)));
        }

        return self::SUCCESS;
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;

        return round($bytes / (1024 ** $i), 1).' '.$units[$i];
    }
}
