<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * App-independent Recovery Console (Module 21, Chunk 4.1). The console never boots
 * Laravel, so it is exercised as a plain class: `require` the entry file (inert under
 * CLI — it only auto-runs as an HTTP entry point) and drive the OeRecoveryConsole
 * gate + state readers directly. Read-only status in 4.1; actions land in 4.2.
 */
class RecoveryConsoleTest extends TestCase
{
    private string $base;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('public/oe-recovery.php');

        $this->base  = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-recov-'.getmypid();
        $this->state = $this->base.'/storage/app/updates';
        @mkdir($this->state, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function console(array $env = [], ?string $stateDir = null): \OeRecoveryConsole
    {
        return new \OeRecoveryConsole($this->base, $env, $stateDir ?? $this->state);
    }

    private function arm(array $payload = ['from_version' => '1.0.0', 'to_version' => '1.1.0']): void
    {
        file_put_contents($this->state.'/arm.flag', json_encode($payload));
    }

    #[Test]
    public function it_parses_env_files_ignoring_comments_and_quotes(): void
    {
        file_put_contents($this->base.'/.env', implode("\n", [
            '# a comment',
            'APP_ENV=production',
            'OE_RECOVERY_KEY="s3cr3t"',
            "DB_PASSWORD='p@ss'",
            'EMPTY=',
            'malformed-line-without-eq',
        ]));

        $env = \OeRecoveryConsole::parseEnv($this->base.'/.env');

        $this->assertSame('production', $env['APP_ENV']);
        $this->assertSame('s3cr3t', $env['OE_RECOVERY_KEY']);
        $this->assertSame('p@ss', $env['DB_PASSWORD']);
        $this->assertSame('', $env['EMPTY']);
        $this->assertArrayNotHasKey('malformed-line-without-eq', $env);
    }

    #[Test]
    public function it_is_disabled_without_a_recovery_key(): void
    {
        [$status, $state] = $this->console([])->handle('anything', '127.0.0.1');

        $this->assertSame(404, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_DISABLED, $state);
    }

    #[Test]
    public function it_reports_no_active_window_when_not_armed(): void
    {
        [$status, $state] = $this->console(['OE_RECOVERY_KEY' => 'k'])->handle('k', '127.0.0.1');

        $this->assertSame(423, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_UNARMED, $state);
    }

    #[Test]
    public function it_prompts_for_a_key_when_armed_and_rejects_a_wrong_one(): void
    {
        $this->arm();
        $console = $this->console(['OE_RECOVERY_KEY' => 'correct-horse']);

        // No key supplied → login prompt (401).
        [$status, $state] = $console->handle(null, '127.0.0.1');
        $this->assertSame(401, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_LOGIN, $state);

        // Wrong key → forbidden (403).
        [$status, $state] = $console->handle('wrong', '127.0.0.1');
        $this->assertSame(403, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_LOGIN, $state);
    }

    #[Test]
    public function the_key_check_is_constant_time_and_exact(): void
    {
        $console = $this->console(['OE_RECOVERY_KEY' => 'abc123']);

        $this->assertTrue($console->authenticate('abc123'));
        $this->assertFalse($console->authenticate('abc1234'));
        $this->assertFalse($console->authenticate('ABC123'));
        $this->assertFalse($console->authenticate(''));
        $this->assertFalse($console->authenticate(null));
    }

    #[Test]
    public function the_ip_allowlist_gates_access_when_set(): void
    {
        $this->arm();
        $console = $this->console([
            'OE_RECOVERY_KEY'           => 'k',
            'OE_RECOVERY_IP_ALLOWLIST'  => '10.0.0.1, 10.0.0.2',
        ]);

        [$status, $state] = $console->handle('k', '203.0.113.9');
        $this->assertSame(403, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_FORBIDDEN, $state);

        [$status, $state] = $console->handle('k', '10.0.0.2');
        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_READY, $state);
    }

    #[Test]
    public function an_authenticated_armed_console_renders_the_swap_and_arm_state(): void
    {
        $this->arm(['from_version' => '2.0.0', 'to_version' => '2.1.0', 'history_id' => 9]);
        file_put_contents($this->state.'/last-swap.json', json_encode([
            'version'   => '2.1.0',
            'completed' => false,
            'root'      => '/var/www',
            'swapped'   => [['path' => 'app', 'had_original' => true]],
        ]));

        $console = $this->console(['OE_RECOVERY_KEY' => 'k']);

        [$status, $state, $html] = $console->handle('k', '127.0.0.1');

        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_READY, $state);
        $this->assertStringContainsString('2.0.0 → 2.1.0', $html);
        $this->assertStringContainsString('no (interrupted)', $html); // swap not completed
        $this->assertStringContainsString('/var/www', $html);

        // Status aggregate reflects the same underlying state.
        $s = $console->status();
        $this->assertTrue($s['armed']);
        $this->assertSame(9, $s['arm_info']['history_id']);
        $this->assertFalse($s['swap_state']['completed']);
    }

    #[Test]
    public function it_reads_update_and_backup_rows_via_raw_pdo_and_degrades_gracefully(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE update_histories (id INTEGER PRIMARY KEY, from_version TEXT, to_version TEXT, status TEXT, step TEXT, error TEXT, started_at TEXT, finished_at TEXT)');
        $pdo->exec("INSERT INTO update_histories (from_version,to_version,status,step) VALUES ('1.0.0','1.1.0','success','complete')");
        $pdo->exec('CREATE TABLE backup_runs (id INTEGER PRIMARY KEY, profile TEXT, status TEXT, app_version TEXT, total_bytes INTEGER, part_count INTEGER, manifest_path TEXT, finished_at TEXT)');
        $pdo->exec("INSERT INTO backup_runs (profile,status,app_version,part_count) VALUES ('full','success','1.0.0',3)");
        $pdo->exec("INSERT INTO backup_runs (profile,status,app_version,part_count) VALUES ('full','failed','1.0.0',0)");

        $console = $this->console(['OE_RECOVERY_KEY' => 'k']);
        $console->setPdo($pdo);

        $updates = $console->recentUpdates();
        $this->assertCount(1, $updates);
        $this->assertSame('1.1.0', $updates[0]['to_version']);

        $backups = $console->restorableBackups();
        $this->assertCount(1, $backups, 'only successful backups are restorable');
        $this->assertSame('full', $backups[0]['profile']);

        // Degrade: no DB → empty lists, no throw.
        $offline = $this->console(['OE_RECOVERY_KEY' => 'k']);
        $offline->setPdo(null);
        $this->assertSame([], $offline->recentUpdates());
        $this->assertSame([], $offline->restorableBackups());
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
}
