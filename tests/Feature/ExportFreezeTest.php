<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\Instance\BlockLevel;
use App\Services\Instance\SystemGate;
use App\Services\Migration\Export\ExportFreeze;
use App\Services\Migration\Export\ExportPlan;
use App\Services\Migration\Export\FenceProbe;
use App\Services\Migration\Export\FreezeState;
use App\Services\Migration\Export\Stages\ExportPlanStage;
use App\Services\Migration\Export\Stages\ExportVerifyStage;
use App\Services\SettingsService;
use App\Services\Updates\PreflightCheck;
use App\Services\Updates\PreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesExportStage;
use Tests\TestCase;

/**
 * Phase 3.11: holding the shop still for a freeze export and putting it back exactly as it was.
 */
class ExportFreezeTest extends TestCase
{
    use DrivesExportStage;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clearFreeze();
        config(['migration.queues.customer' => ['critical', 'default', 'low'], 'migration.queues.migration' => 'migration']);
    }

    protected function tearDown(): void
    {
        $this->clearFreeze();
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function clearFreeze(): void
    {
        $path = FreezeState::file()->path();
        foreach ([$path, $path.'.prev', $path.'.lock'] as $file) {
            @unlink($file);
        }
    }

    private function freeze(): ExportFreeze
    {
        return new ExportFreeze(app(SettingsService::class), app(QueueManager::class));
    }

    private function maintenanceRow(): ?Setting
    {
        return Setting::query()->where('group', 'maintenance')->where('key', 'enabled')->first();
    }

    private function setMaintenance(?string $value): void
    {
        Setting::query()->where('group', 'maintenance')->where('key', 'enabled')->delete();

        if ($value !== null) {
            Setting::create(['group' => 'maintenance', 'key' => 'enabled', 'value' => $value, 'type' => 'boolean', 'is_encrypted' => false]);
        }

        app(SettingsService::class)->forget('maintenance');
    }

    private function maintenanceOn(): bool
    {
        app(SettingsService::class)->forget('maintenance');

        return (bool) app(SettingsService::class)->get('maintenance.enabled', false);
    }

    // ---- engage and release -------------------------------------------------------------------------

    #[Test]
    public function engaging_puts_the_shop_in_maintenance_and_records_how_to_undo_it(): void
    {
        $this->setMaintenance('0');

        $state = $this->freeze()->engage(7, 180);

        $this->assertTrue($this->maintenanceOn());
        $this->assertSame(7, $state->runId);
        $this->assertSame(['present' => true, 'enabled' => 0], $state->previousMaintenance);
        $this->assertTrue($state->isLive());
        $this->assertTrue(FreezeState::read()->belongsToRun(7));
        $this->assertTrue($state->exportDeadlineAt->equalTo($state->engagedAt->addMinutes(180)));
    }

    #[Test]
    public function releasing_puts_the_maintenance_setting_back_to_exactly_what_the_operator_had(): void
    {
        foreach (['0' => false, '1' => true] as $before => $expectedAfter) {
            $this->setMaintenance($before);
            $this->freeze()->engage(7, 60);
            $this->assertTrue($this->maintenanceOn());

            $this->assertTrue($this->freeze()->release('test'));

            $this->assertSame($expectedAfter, $this->maintenanceOn(), "maintenance was {$before} before the freeze");
            $this->assertFileDoesNotExist(FreezeState::file()->path());
        }
    }

    #[Test]
    public function a_setting_that_did_not_exist_before_the_freeze_does_not_exist_after_it(): void
    {
        $this->setMaintenance(null);
        $this->assertNull($this->maintenanceRow());

        $this->freeze()->engage(7, 60);
        $this->assertNotNull($this->maintenanceRow());

        $this->freeze()->release('test');
        $this->assertNull($this->maintenanceRow());
        $this->assertFalse($this->maintenanceOn());
    }

    #[Test]
    public function release_is_safe_to_call_twice_and_when_nothing_is_frozen(): void
    {
        $this->assertFalse($this->freeze()->release('nothing to do'));

        $this->setMaintenance('0');
        $this->freeze()->engage(7, 60);

        $this->assertTrue($this->freeze()->release('first'));
        $this->assertFalse($this->freeze()->release('second'));
        $this->assertFalse($this->maintenanceOn());
    }

    #[Test]
    public function a_driver_that_steps_again_after_a_crash_finds_the_freeze_already_in_place(): void
    {
        $this->setMaintenance('0');
        $first = $this->freeze()->engage(7, 60);
        Carbon::setTestNow(now()->addMinutes(10));

        $again = $this->freeze()->engage(7, 60);

        $this->assertSame($first->engagedAt->getTimestamp(), $again->engagedAt->getTimestamp(), 'the original record, not a new one');
        $this->assertSame(['present' => true, 'enabled' => 0], $again->previousMaintenance, 'and it still remembers the operator\'s value, not our own switch');
    }

    #[Test]
    public function a_second_export_cannot_freeze_over_a_live_freeze(): void
    {
        $this->setMaintenance('1');
        $this->freeze()->engage(7, 60);

        $this->expectException(\LogicException::class);
        $this->freeze()->engage(8, 60);
    }

    #[Test]
    public function an_expired_freeze_is_over_for_the_gate_but_still_needs_releasing(): void
    {
        $this->setMaintenance('0');
        $freeze = $this->freeze();
        $freeze->engage(7, 30);

        $this->assertSame(BlockLevel::Everything, $freeze->level());
        $this->assertFalse($freeze->isExpired());

        Carbon::setTestNow(now()->addMinutes(31));

        $this->assertSame(BlockLevel::None, $freeze->level(), 'the gate stops blocking at the deadline');
        $this->assertTrue($freeze->isExpired(), 'but the shop is still in maintenance until someone releases it');
        $this->assertTrue($this->maintenanceOn());

        $freeze->release('expired');
        $this->assertFalse($this->maintenanceOn());
    }

    // ---- queues --------------------------------------------------------------------------------------

    #[Test]
    public function the_customer_queues_are_paused_on_real_connections_and_the_migration_queue_never_is(): void
    {
        config(['queue.connections.database.driver' => 'database']);
        $queues = app(QueueManager::class);

        $state = $this->freeze()->engage(7, 60);

        foreach (['critical', 'default', 'low'] as $queue) {
            $this->assertTrue($queues->isPaused('database', $queue), "{$queue} is paused");
            $this->assertContains("database:{$queue}", $state->pausedQueues);
        }
        $this->assertFalse($queues->isPaused('database', 'migration'), 'the export that the freeze is for must be able to run');
        $this->assertFalse($queues->isPaused('sync', 'default'), 'a sync connection has no workers to pause');

        $this->freeze()->release('test');

        foreach (['critical', 'default', 'low'] as $queue) {
            $this->assertFalse($queues->isPaused('database', $queue), "{$queue} runs again after the release");
        }
    }

    #[Test]
    public function a_configuration_that_lists_the_migration_queue_among_the_customer_queues_is_corrected(): void
    {
        config(['queue.connections.database.driver' => 'database', 'migration.queues.customer' => ['default', 'migration']]);

        $this->freeze()->engage(7, 60);

        $this->assertTrue(app(QueueManager::class)->isPaused('database', 'default'));
        $this->assertFalse(app(QueueManager::class)->isPaused('database', 'migration'));
    }

    // ---- the gate and the webhooks -----------------------------------------------------------------------

    #[Test]
    public function while_frozen_the_scheduler_gate_holds_everything_but_the_always_class(): void
    {
        $gate = new SystemGate;
        $freeze = $this->freeze();
        $gate->register($freeze);

        $this->assertTrue($gate->allowsCommand('oeparts:newsletter:send-due'), 'nothing frozen yet');

        $this->setMaintenance('0');
        $freeze->engage(7, 60);

        $this->assertFalse($gate->allowsCommand('oeparts:newsletter:send-due'));
        $this->assertFalse($gate->allowsCommand('sitemap:generate'));
        $this->assertTrue($gate->allowsCommand('scheduler:heartbeat'));

        $freeze->release('test');
        $this->assertTrue($gate->allowsCommand('oeparts:newsletter:send-due'));
    }

    #[Test]
    public function the_payment_webhooks_answer_503_with_retry_after_only_while_frozen(): void
    {
        $this->setMaintenance('0');

        $free = $this->postJson('/webhooks/bank-transfer-confirm', []);
        $this->assertNotSame(503, $free->status());

        $this->freeze()->engage(7, 60);

        foreach (['/webhooks/airwallex', '/webhooks/paysera', '/webhooks/bank-transfer-confirm'] as $url) {
            $response = $this->postJson($url, []);
            $this->assertSame(503, $response->status(), $url);
            $this->assertSame('300', $response->headers->get('Retry-After'));
        }

        $this->freeze()->release('test');
        $this->assertNotSame(503, $this->postJson('/webhooks/bank-transfer-confirm', [])->status());
    }

    #[Test]
    public function an_update_or_restore_is_refused_by_the_preflight_while_the_shop_is_frozen(): void
    {
        $preflight = app(PreflightService::class);

        $this->assertSame(PreflightCheck::PASS, $preflight->checkNoExportFreeze()->status);

        $this->setMaintenance('0');
        $this->freeze()->engage(7, 60);

        $check = $preflight->checkNoExportFreeze();
        $this->assertSame(PreflightCheck::FAIL, $check->status);
        $this->assertStringContainsString('oeparts:migrate:unfreeze', $check->message);

        $this->freeze()->release('test');
        $this->assertSame(PreflightCheck::PASS, $preflight->checkNoExportFreeze()->status);
    }

    // ---- the fence probe and the stages ---------------------------------------------------------------------

    #[Test]
    public function the_fence_probe_reports_exactly_what_changed_between_two_pictures(): void
    {
        $probe = new FenceProbe;
        $before = $probe->snapshot(['sequences', 'settings']);

        DB::table('sequences')->insert(['type' => 'quote', 'current_value' => 1, 'resets_monthly' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $after = $probe->snapshot(['sequences', 'settings']);

        $drift = FenceProbe::drift($before, $after);

        $this->assertSame(['sequences'], array_keys($drift), 'settings did not move');
        $this->assertSame([0, 1], $drift['sequences']['count']);
        $this->assertSame([null, 1], $drift['sequences']['max_key']);
        $this->assertSame([], FenceProbe::drift($after, $after));
    }

    #[Test]
    public function a_big_table_is_not_counted_but_its_key_and_last_update_still_watch_it(): void
    {
        $snapshot = (new FenceProbe)->snapshot(['sequences'], ['sequences' => ['rows' => 5_000_000, 'approximate' => true]]);

        $this->assertNull($snapshot['sequences']['count']);
        $this->assertArrayHasKey('max_key', $snapshot['sequences']);
    }

    private function stages(): array
    {
        return [
            'plan' => new ExportPlanStage($this->freeze(), new FenceProbe),
            'verify' => new ExportVerifyStage($this->freeze(), new FenceProbe),
        ];
    }

    private function freezePlan(array $over = []): ExportPlan
    {
        return $this->planOf(['sequences'], array_merge(['consistency' => 'freeze', 'freeze_cap_minutes' => 180, 'hold' => false], $over));
    }

    #[Test]
    public function the_freeze_engages_in_the_first_step_of_the_run_and_a_live_copy_never_freezes(): void
    {
        $this->setMaintenance('0');
        $stages = $this->stages();

        $live = $this->run_($this->planOf(['sequences']));
        $stages['plan']->step($live, []);
        $this->assertFalse($this->maintenanceOn());
        $this->assertNull(FreezeState::read());

        $run = $this->run_($this->freezePlan());
        $this->assertNull(FreezeState::read(), 'creating the run froze nothing');
        $stages['plan']->step($run, []);
        $this->assertTrue($this->maintenanceOn());
        $this->assertTrue(FreezeState::read()->belongsToRun($run->id));
    }

    #[Test]
    public function an_untouched_freeze_is_reported_consistent_and_released_unless_held(): void
    {
        $this->setMaintenance('0');
        $stages = $this->stages();
        $run = $this->run_($this->freezePlan());
        $run->meta = array_merge($run->meta, ['export_report' => ['db' => ['rows_by_table' => ['sequences' => 0]]]]);

        $stages['plan']->step($run, []);
        $stages['verify']->step($run, []);

        $this->assertSame('consistent', $run->meta['export_report']['consistency']);
        $this->assertSame([], $run->meta['export_report']['fence']['drift']);
        $this->assertFalse($this->maintenanceOn(), 'released at the end');
        $this->assertNull(FreezeState::read());
    }

    #[Test]
    public function a_write_that_slipped_through_the_freeze_makes_the_copy_not_consistent_and_says_where(): void
    {
        $this->setMaintenance('0');
        $stages = $this->stages();
        $run = $this->run_($this->freezePlan());
        $run->meta = array_merge($run->meta, ['export_report' => ['db' => ['rows_by_table' => ['sequences' => 0]]]]);

        $stages['plan']->step($run, []);
        DB::table('sequences')->insert(['type' => 'quote', 'current_value' => 1, 'resets_monthly' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $stages['verify']->step($run, []);

        $report = $run->meta['export_report'];
        $this->assertSame('not_consistent', $report['consistency']);
        $this->assertArrayHasKey('sequences', $report['fence']['drift']);
        $this->assertSame(['written' => 0, 'in_database' => 1], $report['reconciliation']['mismatches']['sequences']);
    }

    #[Test]
    public function a_live_copy_with_drift_is_just_eventually_consistent(): void
    {
        $stages = $this->stages();
        $run = $this->run_($this->planOf(['sequences']));
        $run->meta = array_merge($run->meta, ['export_report' => ['db' => ['rows_by_table' => ['sequences' => 0]]]]);

        $stages['plan']->step($run, []);
        DB::table('sequences')->insert(['type' => 'quote', 'current_value' => 1, 'resets_monthly' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $stages['verify']->step($run, []);

        $this->assertSame('eventual', $run->meta['export_report']['consistency']);
        $this->assertNotEmpty($run->meta['export_report']['fence']['drift']);
    }

    #[Test]
    public function a_held_freeze_stays_in_place_after_verification(): void
    {
        $this->setMaintenance('0');
        $stages = $this->stages();
        $run = $this->run_($this->freezePlan(['hold' => true]));
        $run->meta = array_merge($run->meta, ['export_report' => ['db' => ['rows_by_table' => ['sequences' => 0]]]]);

        $stages['plan']->step($run, []);
        $stages['verify']->step($run, []);

        $this->assertTrue($this->maintenanceOn(), 'the old site stays frozen until the package is sealed and the hold timer takes over');
        $this->assertTrue(FreezeState::read()->isLive());
    }

    #[Test]
    public function the_hold_replaces_the_export_cap_with_its_own_deadline(): void
    {
        $this->setMaintenance('0');
        $freeze = $this->freeze();
        $freeze->engage(7, 60);

        $held = $freeze->hold(45);

        $this->assertSame(FreezeState::PHASE_HOLD, $held->phase);
        $this->assertEqualsWithDelta(now()->addMinutes(45)->getTimestamp(), $held->currentDeadline()->getTimestamp(), 2);

        $forever = $freeze->hold(0);
        $this->assertNull($forever->currentDeadline(), '0 minutes means until lifted by hand');
        $this->assertTrue($forever->isLive(now()->addYear()));
    }
}
