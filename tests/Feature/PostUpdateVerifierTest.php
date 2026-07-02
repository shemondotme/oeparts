<?php

namespace Tests\Feature;

use App\Models\UpdateHistory;
use App\Services\Updates\PostUpdateVerifier;
use App\Services\Updates\UpdateApplier;
use App\Services\Updates\VerifyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Post-update verification + auto-rollback (Module 21, Chunk 3.6).
 */
class PostUpdateVerifierTest extends TestCase
{
    use RefreshDatabase;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();
        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-verify-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);
        Schema::dropIfExists('oe_pv_child');
        Schema::dropIfExists('oe_pv_parent');
        parent::tearDown();
    }

    #[Test]
    public function verification_passes_on_a_healthy_schema(): void
    {
        config(['updates.verify' => ['required_tables' => ['settings', 'admins'], 'referential' => [], 'smoke' => true]]);

        $this->assertTrue(app(PostUpdateVerifier::class)->verify()->ok());
    }

    #[Test]
    public function a_missing_expected_table_fails_verification(): void
    {
        config(['updates.verify' => ['required_tables' => ['definitely_missing_table'], 'referential' => [], 'smoke' => false]]);

        $report = app(PostUpdateVerifier::class)->verify();

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('definitely_missing_table', $report->firstFailure());
    }

    #[Test]
    public function an_orphaned_row_fails_the_referential_check(): void
    {
        Schema::create('oe_pv_parent', fn ($t) => $t->id());
        Schema::create('oe_pv_child', function ($t) {
            $t->id();
            $t->unsignedBigInteger('parent_id');
        });
        DB::table('oe_pv_child')->insert(['parent_id' => 999]); // no such parent

        config(['updates.verify' => [
            'required_tables' => [],
            'referential'     => [['oe_pv_child', 'parent_id', 'oe_pv_parent', 'id']],
            'smoke'           => false,
        ]]);

        $report = app(PostUpdateVerifier::class)->verify();

        $this->assertFalse($report->ok());
        $this->assertStringContainsString('orphaned', $report->firstFailure());
    }

    #[Test]
    public function the_fsm_auto_rolls_back_when_verification_fails(): void
    {
        app()->instance(PostUpdateVerifier::class, new class extends PostUpdateVerifier
        {
            public function verify(): VerifyReport
            {
                $r = new VerifyReport;
                $r->add('smoke:db', 'fail', 'db unreachable');

                return $r;
            }
        });

        $applier = new VerifyRollbackApplier;
        $history = $applier->run($applier->start(['version' => '1.1.0']));

        $this->assertSame(UpdateHistory::STATUS_ROLLED_BACK, $history->status);
        $this->assertTrue($applier->rolledBack);
    }

    #[Test]
    public function the_fsm_succeeds_when_verification_passes(): void
    {
        app()->instance(PostUpdateVerifier::class, new class extends PostUpdateVerifier
        {
            public function verify(): VerifyReport
            {
                $r = new VerifyReport;
                $r->add('all', 'ok');

                return $r;
            }
        });

        $applier = new VerifyRollbackApplier;
        $history = $applier->run($applier->start(['version' => '1.1.0']));

        $this->assertSame(UpdateHistory::STATUS_SUCCESS, $history->status);
        $this->assertFalse($applier->rolledBack);
    }
}

/** Applier that no-ops every step EXCEPT the real doVerify, to test verify→rollback. */
class VerifyRollbackApplier extends UpdateApplier
{
    public bool $rolledBack = false;

    protected function gate(array $manifest): void {}
    protected function enterMaintenance(): void {}
    protected function exitMaintenance(): void {}
    protected function doBackup(UpdateHistory $h): void {}
    protected function doDownload(UpdateHistory $h): void {}
    protected function doExtract(UpdateHistory $h): void {}
    protected function doSwap(UpdateHistory $h): void {}
    protected function doFinalize(UpdateHistory $h): void {}
    protected function rollback(UpdateHistory $h): void { $this->rolledBack = true; }
}
