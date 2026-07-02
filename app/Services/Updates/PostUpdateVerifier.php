<?php

namespace App\Services\Updates;

use App\Services\HealthCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PostUpdateVerifier (Module 21, Chunk 3.6) — runs after finalize to confirm the
 * update actually landed cleanly. ANY failed check makes the apply FSM auto-roll
 * back (reverse the swap + restore the DB from the pre-update backup).
 *
 * Three layers (config('updates.verify')): schema assertions (expected tables
 * exist), referential-integrity spot-checks (no orphaned critical rows), and an
 * in-process smoke test (DB reachable + every critical table readable — the new
 * code can query its data against the migrated schema).
 */
class PostUpdateVerifier
{
    public function verify(): VerifyReport
    {
        $report = new VerifyReport;

        $this->checkTables($report);
        $this->checkReferentialIntegrity($report);

        if ((bool) config('updates.verify.smoke', true)) {
            $this->checkSmoke($report);
        }

        return $report;
    }

    private function checkTables(VerifyReport $report): void
    {
        foreach ((array) config('updates.verify.required_tables', []) as $table) {
            Schema::hasTable($table)
                ? $report->add('table:'.$table, 'ok')
                : $report->add('table:'.$table, 'fail', 'Expected table ['.$table.'] is missing after migration.');
        }
    }

    private function checkReferentialIntegrity(VerifyReport $report): void
    {
        foreach ((array) config('updates.verify.referential', []) as $rel) {
            [$child, $fk, $parent, $parentKey] = $rel;

            if (! Schema::hasTable($child) || ! Schema::hasTable($parent)) {
                continue; // the table check already flagged the missing table
            }

            $orphans = DB::table($child)
                ->whereNotNull($fk)
                ->whereNotExists(function ($q) use ($parent, $parentKey, $child, $fk) {
                    $q->from($parent)->whereColumn($parent.'.'.$parentKey, $child.'.'.$fk);
                })
                ->count();

            $orphans === 0
                ? $report->add('ref:'.$child.'.'.$fk, 'ok')
                : $report->add('ref:'.$child.'.'.$fk, 'fail', $orphans.' orphaned row(s) in '.$child.'.'.$fk.'.');
        }
    }

    private function checkSmoke(VerifyReport $report): void
    {
        // DB reachable (mirrors the /health database check).
        app(HealthCheckService::class)->checkDatabase() === 'ok'
            ? $report->add('smoke:db', 'ok')
            : $report->add('smoke:db', 'fail', 'Database is not reachable after the update.');

        // Every critical table is readable by the new code against the new schema.
        try {
            foreach ((array) config('updates.verify.required_tables', []) as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->limit(1)->get();
                }
            }
            $report->add('smoke:read', 'ok');
        } catch (\Throwable $e) {
            $report->add('smoke:read', 'fail', 'A critical table could not be read: '.$e->getMessage());
        }
    }
}
