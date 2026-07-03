<?php

namespace Tests\Feature;

use App\Services\Updates\RecoveryArm;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Recovery arm-flag lifecycle (Module 21, Chunk 4.1). The flag opens/closes the
 * app-independent Recovery Console's operating window; it lives in the framework-
 * independent state dir beside last-swap.json and lock.
 */
class RecoveryArmTest extends TestCase
{
    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        $this->state = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-arm-'.getmypid();
        @mkdir($this->state, 0775, true);
        config(['updates.state_path' => $this->state]);
    }

    protected function tearDown(): void
    {
        @array_map('unlink', glob($this->state.DIRECTORY_SEPARATOR.'*') ?: []);
        @rmdir($this->state);
        parent::tearDown();
    }

    #[Test]
    public function arm_writes_a_flag_with_context_in_the_state_dir(): void
    {
        $arm = app(RecoveryArm::class);

        $this->assertFalse($arm->isArmed());

        $arm->arm(['history_id' => 42, 'from_version' => '1.0.0', 'to_version' => '1.1.0']);

        $this->assertTrue($arm->isArmed());
        $this->assertSame($this->state.'/arm.flag', $arm->flagFile());
        $this->assertFileExists($this->state.'/arm.flag');

        $payload = $arm->read();
        $this->assertSame(42, $payload['history_id']);
        $this->assertSame('1.0.0', $payload['from_version']);
        $this->assertSame('1.1.0', $payload['to_version']);
        $this->assertArrayHasKey('armed_at', $payload);
        $this->assertSame(PHP_VERSION, $payload['php_version']);
    }

    #[Test]
    public function disarm_removes_the_flag_and_is_safe_when_already_absent(): void
    {
        $arm = app(RecoveryArm::class);
        $arm->arm();
        $this->assertTrue($arm->isArmed());

        $arm->disarm();
        $this->assertFalse($arm->isArmed());
        $this->assertNull($arm->read());

        // Idempotent: disarming again does not error.
        $arm->disarm();
        $this->assertFalse($arm->isArmed());
    }
}
