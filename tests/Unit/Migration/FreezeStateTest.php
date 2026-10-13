<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Export\FreezeState;
use App\Support\AtomicJsonFile;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.7b: the freeze record. One definition of "is this freeze still alive?" for the lock custody
 * rule, the watchdog and the request that lifts an expired freeze.
 */
class FreezeStateTest extends TestCase
{
    private string $path = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-freeze-'.getmypid().'-'.uniqid().'.json';
        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ([$this->path, $this->path.'.prev', $this->path.'.lock'] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function file(): AtomicJsonFile
    {
        return new AtomicJsonFile($this->path);
    }

    private function freeze(int $runId = 7): FreezeState
    {
        return FreezeState::begin($runId, now(), now()->addMinutes(180), ['present' => true, 'enabled' => 0], ['critical', 'default', 'low']);
    }

    #[Test]
    public function a_freeze_survives_being_written_and_read_back(): void
    {
        $this->freeze()->write($this->file());

        $read = FreezeState::read($this->file());

        $this->assertSame($this->freeze()->toArray(), $read->toArray());
        $this->assertSame(7, $read->runId);
        $this->assertSame(['critical', 'default', 'low'], $read->pausedQueues);
        $this->assertSame(['present' => true, 'enabled' => 0], $read->previousMaintenance);
    }

    #[Test]
    public function the_file_has_exactly_the_documented_schema(): void
    {
        $this->freeze()->write($this->file());

        $data = json_decode((string) file_get_contents($this->path), true);

        $this->assertSame(
            ['v', 'run_id', 'phase', 'engaged_at', 'export_deadline_at', 'hold_deadline_at', 'previous_maintenance', 'paused_queues', 'released_at'],
            array_keys($data)
        );
        $this->assertSame(1, $data['v']);
        $this->assertSame('export', $data['phase']);
        $this->assertNull($data['hold_deadline_at']);
        $this->assertNull($data['released_at']);
        $this->assertSame('2026-10-14T10:00:00+00:00', $data['engaged_at']);
        $this->assertSame('2026-10-14T13:00:00+00:00', $data['export_deadline_at']);
    }

    #[Test]
    public function there_is_no_freeze_when_there_is_no_file(): void
    {
        $this->assertNull(FreezeState::read($this->file()));
    }

    #[Test]
    public function a_file_torn_by_a_crash_falls_back_to_the_previous_copy(): void
    {
        $this->freeze(7)->write($this->file());
        $this->freeze(8)->write($this->file()); // the second write keeps the first as .prev

        file_put_contents($this->path, '{"v":1,"run_id":8,"pha');

        $this->assertSame(7, FreezeState::read($this->file())->runId);
    }

    #[Test]
    public function a_file_that_cannot_be_read_at_all_counts_as_no_freeze(): void
    {
        file_put_contents($this->path, 'garbage that is not json');

        $this->assertNull(FreezeState::read($this->file()));
    }

    #[Test]
    public function a_file_with_the_wrong_shape_counts_as_no_freeze(): void
    {
        foreach ([
            ['v' => 2, 'run_id' => 1],
            ['v' => 1, 'run_id' => '7', 'phase' => 'export'],
            ['v' => 1, 'run_id' => 7, 'phase' => 'sleeping'],
            ['v' => 1, 'run_id' => 7, 'phase' => 'export', 'previous_maintenance' => 'yes'],
            ['v' => 1, 'run_id' => 7, 'phase' => 'export', 'previous_maintenance' => ['present' => true, 'enabled' => 0]],
        ] as $data) {
            file_put_contents($this->path, json_encode($data));
            @unlink($this->path.'.prev');

            $this->assertNull(FreezeState::read($this->file()), json_encode($data));
        }
    }

    #[Test]
    public function the_deadline_is_the_export_cap_until_the_package_is_sealed_then_the_hold_deadline(): void
    {
        $freeze = $this->freeze();
        $this->assertTrue($freeze->currentDeadline()->equalTo(now()->addMinutes(180)));

        $held = $freeze->toHold(now()->addMinutes(200)->addMinutes(120));
        $this->assertSame(FreezeState::PHASE_HOLD, $held->phase);
        $this->assertTrue($held->currentDeadline()->equalTo(now()->addMinutes(320)));
    }

    #[Test]
    public function a_freeze_is_live_until_its_current_deadline_and_not_after(): void
    {
        $freeze = $this->freeze();

        $this->assertTrue($freeze->isLive());
        $this->assertTrue($freeze->isLive(now()->addMinutes(179)));
        $this->assertFalse($freeze->isLive(now()->addMinutes(180)), 'at the deadline it is over');
        $this->assertTrue($freeze->isExpired(now()->addMinutes(181)));
    }

    #[Test]
    public function the_clock_that_matters_switches_when_the_phase_does(): void
    {
        $exporting = $this->freeze();
        $held = $exporting->toHold(now()->addMinutes(500));

        $at = now()->addMinutes(300); // past the export cap, inside the hold

        $this->assertFalse($exporting->isLive($at));
        $this->assertTrue($held->isLive($at));
    }

    #[Test]
    public function a_hold_without_a_deadline_lasts_until_it_is_lifted_by_hand(): void
    {
        $held = $this->freeze()->toHold(null);

        $this->assertNull($held->currentDeadline());
        $this->assertTrue($held->isLive(now()->addYears(5)));
        $this->assertFalse($held->isExpired(now()->addYears(5)));
    }

    #[Test]
    public function a_released_freeze_is_never_live_whatever_the_clock_says(): void
    {
        $released = $this->freeze()->released(now()->addMinutes(5));

        $this->assertFalse($released->isLive());

        $released->write($this->file());
        $this->assertFalse(FreezeState::read($this->file())->isLive());
    }

    #[Test]
    public function it_knows_which_run_it_belongs_to(): void
    {
        $this->assertTrue($this->freeze(7)->belongsToRun(7));
        $this->assertFalse($this->freeze(7)->belongsToRun(8));
    }

    #[Test]
    public function the_previous_maintenance_value_can_say_the_setting_did_not_exist(): void
    {
        $freeze = FreezeState::begin(3, now(), now()->addHour(), ['present' => false, 'enabled' => null]);
        $freeze->write($this->file());

        $this->assertSame(['present' => false, 'enabled' => null], FreezeState::read($this->file())->previousMaintenance);
    }
}
