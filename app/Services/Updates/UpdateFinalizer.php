<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Artisan;

/**
 * UpdateFinalizer (Module 21, Chunk 3.4) — the post-swap boot.
 *
 * Runs on a FRESH request AFTER the file swap (rule #46), so the NEW code is
 * booted — opcache still holds the OLD classes in the swapping request, so this
 * must never run in the same request as the swap. Brings the DB + app state in
 * line with the new code:
 *   migrate --force (idempotent) → package:discover / filament:upgrade /
 *   storage:link → vendor:publish → idempotent seeders → config/route/view/event
 *   cache rebuild (NEVER Cache::flush(), rule #5) → queue:restart (if workers).
 *
 * `migrate` is critical: its failure throws so the apply orchestrator (3.6) can
 * roll back (restore DB + reverse swap). Everything else is best-effort by
 * default (recorded, not fatal). The plan is config-driven so a release can tune
 * it and tests can exercise the orchestration without side effects.
 *
 * NOTE: `lang/` is replaced by the swap (it's a core path); modified-override
 * detection needs the per-file sha256 manifest from the CI pipeline (Phase 5).
 */
class UpdateFinalizer
{
    public function run(): FinalizeReport
    {
        $report = new FinalizeReport;

        foreach ($this->plan() as $step) {
            $this->runStep($report, $step);
        }

        return $report;
    }

    /**
     * The ordered list of steps. Exposed so it can be inspected/tested.
     *
     * @return array<int,array{key:string,command:string,params:array,critical:bool}>
     */
    public function plan(): array
    {
        $steps = [];

        // 1. Migrations — always first, always critical.
        $steps[] = $this->step('migrate', 'migrate', ['--force' => true], critical: true);

        // 2. Package discovery / asset republish / storage link.
        foreach ((array) config('updates.post_swap.artisan', []) as $entry) {
            $steps[] = $this->step(
                (string) $entry['command'], (string) $entry['command'],
                (array) ($entry['params'] ?? []), (bool) ($entry['critical'] ?? false)
            );
        }

        // 3. vendor:publish for configured tags.
        foreach ((array) config('updates.post_swap.vendor_publish_tags', []) as $tag) {
            $steps[] = $this->step('vendor-publish:'.$tag, 'vendor:publish',
                ['--tag' => $tag, '--force' => true], false);
        }

        // 4. Idempotent reference seeders.
        foreach ((array) config('updates.post_swap.seeders', []) as $seeder) {
            $steps[] = $this->step('seed:'.class_basename($seeder), 'db:seed',
                ['--class' => $seeder, '--force' => true], false);
        }

        // 5. Framework cache rebuild (clear then cache) — NOT app Cache::flush().
        if ((bool) config('updates.post_swap.rebuild_cache', true)) {
            foreach (['config', 'route', 'view', 'event'] as $cache) {
                $steps[] = $this->step($cache.':clear', $cache.':clear', [], false);
                $steps[] = $this->step($cache.':cache', $cache.':cache', [], false);
            }
        }

        // 6. queue:restart — only when a real worker driver is in use.
        if ((bool) config('updates.post_swap.restart_queue', true) && config('queue.default') !== 'sync') {
            $steps[] = $this->step('queue:restart', 'queue:restart', [], false);
        }

        return $steps;
    }

    private function runStep(FinalizeReport $report, array $step): void
    {
        try {
            $code   = Artisan::call($step['command'], $step['params']);
            $output = trim(Artisan::output());

            if ($code === 0) {
                $report->add($step['key'], 'ok', $output);

                return;
            }

            $report->add($step['key'], 'fail', $output !== '' ? $output : 'exit code '.$code);
            if ($step['critical']) {
                throw new UpdateException('Post-swap step ['.$step['command'].'] failed (exit '.$code.').');
            }
        } catch (UpdateException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $report->add($step['key'], 'fail', $e->getMessage());
            if ($step['critical']) {
                throw new UpdateException('Post-swap step ['.$step['command'].'] failed: '.$e->getMessage());
            }
        }
    }

    private function step(string $key, string $command, array $params, bool $critical): array
    {
        return ['key' => $key, 'command' => $command, 'params' => $params, 'critical' => $critical];
    }
}
