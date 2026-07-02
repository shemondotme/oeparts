<?php

namespace Tests\Feature;

use App\Services\Backup\BackupLock;
use App\Services\Updates\PreflightCheck;
use App\Services\Updates\PreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update pre-flight gate (Module 21, Chunk 3.1). Drives each check against a
 * controlled fixture root so the repo's own .git / permissions don't interfere.
 */
class PreflightServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $base;
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-preflight-'.getmypid();
        $this->root = $this->base.DIRECTORY_SEPARATOR.'root';
        @mkdir($this->root.'/app', 0775, true);
        @mkdir($this->root.'/config', 0775, true);
        @mkdir($this->base.'/state', 0775, true);
        file_put_contents($this->root.'/.env', "APP_KEY=base64:x\nOE_BACKUP_KEY=base64:y\n");

        config(['updates.root_path' => $this->root]);
        config(['updates.state_path' => $this->base.'/state']);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function service(): PreflightService
    {
        return app(PreflightService::class);
    }

    /** A manifest that should pass every check in the fixture environment. */
    private function cleanManifest(array $overrides = []): array
    {
        return array_merge([
            'min_php'                     => '8.2',
            'required_extensions'         => ['json'],
            'min_version_to_update_from'  => '0.0.0',
            'size_bytes'                  => 1024,
            'new_env_keys'                => [],
        ], $overrides);
    }

    #[Test]
    public function php_version_gate_passes_and_fails(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkPhpVersion(['min_php' => '8.2'])->status);
        $this->assertSame(PreflightCheck::FAIL, $this->service()->checkPhpVersion(['min_php' => '99.0'])->status);
    }

    #[Test]
    public function extension_gate_detects_missing_extensions(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkExtensions(['required_extensions' => ['json']])->status);

        $fail = $this->service()->checkExtensions(['required_extensions' => ['totally_missing_ext']]);
        $this->assertSame(PreflightCheck::FAIL, $fail->status);
        $this->assertContains('totally_missing_ext', $fail->meta['missing']);
    }

    #[Test]
    public function version_gate_enforces_min_version_to_update_from(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkVersionGate(['min_version_to_update_from' => '0.0.0'])->status);
        $this->assertSame(PreflightCheck::FAIL, $this->service()->checkVersionGate(['min_version_to_update_from' => '99.0.0'])->status);
    }

    #[Test]
    public function it_blocks_when_the_shared_lock_is_held(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkLock()->status);

        app(BackupLock::class)->acquire('update:running');
        $this->assertSame(PreflightCheck::FAIL, $this->service()->checkLock()->status);

        app(BackupLock::class)->release();
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkLock()->status);
    }

    #[Test]
    public function it_blocks_a_git_managed_deployment(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkDeploymentType()->status);

        @mkdir($this->root.'/.git');
        $this->assertSame(PreflightCheck::FAIL, $this->service()->checkDeploymentType()->status);
    }

    #[Test]
    public function disk_space_check_fails_when_the_release_is_larger_than_free_space(): void
    {
        $free = (int) disk_free_space($this->root);

        // Needing 3× the entire free space cannot be satisfied.
        $fail = $this->service()->checkDiskSpace(['size_bytes' => $free]);
        $this->assertSame(PreflightCheck::FAIL, $fail->status);

        $pass = $this->service()->checkDiskSpace(['size_bytes' => 1024]);
        $this->assertSame(PreflightCheck::PASS, $pass->status);
    }

    #[Test]
    public function new_env_keys_are_reported_as_a_warning(): void
    {
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkEnvKeys(['new_env_keys' => []])->status);
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkEnvKeys(['new_env_keys' => ['APP_KEY']])->status);

        $warn = $this->service()->checkEnvKeys(['new_env_keys' => ['OE_BRAND_NEW_KEY']]);
        $this->assertSame(PreflightCheck::WARN, $warn->status);
        $this->assertContains('OE_BRAND_NEW_KEY', $warn->meta['keys']);
    }

    #[Test]
    public function schema_drift_passes_when_the_fingerprint_matches(): void
    {
        $fingerprint = $this->service()->schemaFingerprint();

        $this->assertSame(PreflightCheck::PASS, $this->service()->checkSchemaDrift([])->status, 'no baseline = skipped');
        $this->assertSame(PreflightCheck::PASS, $this->service()->checkSchemaDrift(['schema_fingerprint_from' => $fingerprint])->status);
        $this->assertSame(PreflightCheck::WARN, $this->service()->checkSchemaDrift(['schema_fingerprint_from' => 'deadbeef'])->status);
    }

    #[Test]
    public function run_aggregates_and_can_proceed_when_clean(): void
    {
        $report = $this->service()->run($this->cleanManifest());

        $this->assertTrue($report->canProceed(), 'clean environment should proceed. Failures: '
            .implode(', ', array_map(fn ($c) => $c->key.':'.$c->message, $report->failures())));
        $this->assertSame(PreflightCheck::PASS, $report->get('deployment')->status);
    }

    #[Test]
    public function run_cannot_proceed_with_a_failing_check(): void
    {
        $report = $this->service()->run($this->cleanManifest(['min_php' => '99.0']));

        $this->assertFalse($report->canProceed());
        $this->assertNotEmpty($report->failures());
        $this->assertSame(PreflightCheck::FAIL, $report->get('php')->status);
    }
}
