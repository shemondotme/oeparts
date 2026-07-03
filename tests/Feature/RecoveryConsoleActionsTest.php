<?php

namespace Tests\Feature;

use App\Models\BackupRun;
use App\Services\Backup\BackupCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Recovery Console destructive actions (Module 21, Chunk 4.2). Still framework-free:
 * the OeRecoveryConsole class reverses a swap, decrypts + applies a DB backup, clears
 * the maintenance flag, and resets OPcache — driven directly (no HTTP, no Kernel).
 * The DB-backed cases share the test connection's PDO so the console mutates the same
 * database; the restore case encrypts with the REAL BackupCipher to prove the console's
 * decrypt port is byte-compatible with the engine.
 */
class RecoveryConsoleActionsTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private string $state;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('public/oe-recovery.php');

        $this->base  = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-recov-act-'.getmypid();
        $this->state = $this->base.'/storage/app/updates';
        $this->disk  = $this->base.'/backupdisk';
        @mkdir($this->state, 0775, true);
        @mkdir($this->disk, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function console(array $env = []): \OeRecoveryConsole
    {
        return new \OeRecoveryConsole($this->base, $env, $this->state);
    }

    private function writeFile(string $path, string $contents): void
    {
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $contents);
    }

    /* ---- File rollback ------------------------------------------------- */

    #[Test]
    public function rollback_files_reverses_an_interrupted_swap(): void
    {
        $root       = $this->base.'/root';
        $backupDir  = $this->base.'/swap-backup';
        $stagingDir = $this->base.'/staging';

        // Post-swap live state: NEW code is in root, the ORIGINAL is parked in swap-backup.
        $this->writeFile($root.'/app/NewClass.php', '<?php // new');
        $this->writeFile($backupDir.'/app/OldClass.php', '<?php // old');

        file_put_contents($this->state.'/last-swap.json', json_encode([
            'version'     => '1.1.0',
            'root'        => $root,
            'backup_dir'  => $backupDir,
            'staging_dir' => $stagingDir,
            'completed'   => true,
            'swapped'     => [['path' => 'app', 'had_original' => true]],
        ]));

        $result = $this->console()->rollbackFiles();

        $this->assertTrue($result['ok'], json_encode($result));
        // Original restored to root; new code parked back in staging.
        $this->assertFileExists($root.'/app/OldClass.php');
        $this->assertFileDoesNotExist($root.'/app/NewClass.php');
        $this->assertFileExists($stagingDir.'/app/NewClass.php');
        // Recovery state cleared once reversed.
        $this->assertFileDoesNotExist($this->state.'/last-swap.json');
    }

    #[Test]
    public function rollback_files_reports_when_there_is_nothing_to_reverse(): void
    {
        $result = $this->console()->rollbackFiles();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No interrupted file swap', $result['message']);
    }

    /* ---- Database restore ---------------------------------------------- */

    #[Test]
    public function restore_db_decrypts_and_applies_the_pre_update_backup(): void
    {
        $key = (string) config('backup.encryption.key');
        $this->assertNotSame('', $key, 'phpunit must provide OE_BACKUP_KEY for this test');

        // A live table we will damage, then recover from the backup.
        DB::statement('CREATE TABLE `recovery_widgets` (id INTEGER PRIMARY KEY, name TEXT)');
        DB::table('recovery_widgets')->insert(['id' => 1, 'name' => 'live-value']);

        $run = BackupRun::create([
            'profile'    => BackupRun::PROFILE_UPDATE_SAFETY,
            'status'     => BackupRun::STATUS_SUCCESS,
            'trigger'    => BackupRun::TRIGGER_PRE_UPDATE,
            'disk'       => 'local',
            'encrypted'  => true,
            'part_count' => 2,
        ]);
        $run->manifest_path = 'backups/'.$run->id.'/manifest.json';
        $run->save();

        // Build two encrypted parts with the REAL cipher (proves decrypt parity).
        $schemaSql = "DROP TABLE IF EXISTS `recovery_widgets`;\n"
            ."CREATE TABLE `recovery_widgets` (id INTEGER PRIMARY KEY, name TEXT);\n";
        $dataSql = "INSERT INTO `recovery_widgets` (`id`,`name`) VALUES (1,'restored-value');\n";

        $schemaPart = $this->encryptPart($run->id, $schemaSql, 'recovery_widgets.schema.sql.gz.enc', 'recovery_widgets', 'schema');
        $dataPart   = $this->encryptPart($run->id, $dataSql, 'recovery_widgets.data.0.sql.gz.enc', 'recovery_widgets', 'data');

        // Manifest lists DATA before SCHEMA on purpose — the console must still run
        // schema first so the table exists before its rows.
        $this->writeManifest($run->id, [$dataPart, $schemaPart]);

        // Damage the install.
        DB::statement('DROP TABLE `recovery_widgets`');

        $console = $this->console(['OE_BACKUP_KEY' => $key]);
        $console->setDiskRoot('local', $this->disk);
        $console->setPdo(DB::connection()->getPdo());

        $result = $console->restoreDatabase();

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(2, $result['detail']['parts_applied']);
        $this->assertSame(1, $result['detail']['tables']);
        $this->assertSame('restored-value', DB::table('recovery_widgets')->where('id', 1)->value('name'));
    }

    #[Test]
    public function restore_db_refuses_without_a_backup_key(): void
    {
        $console = $this->console([]); // no OE_BACKUP_KEY
        $console->setPdo(DB::connection()->getPdo());

        $result = $console->restoreDatabase();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('OE_BACKUP_KEY', $result['message']);
    }

    #[Test]
    public function restore_db_reports_when_no_backup_exists(): void
    {
        $console = $this->console(['OE_BACKUP_KEY' => 'k']);
        $console->setPdo(DB::connection()->getPdo());

        $result = $console->restoreDatabase();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No successful pre-update backup', $result['message']);
    }

    /* ---- Maintenance + OPcache ----------------------------------------- */

    #[Test]
    public function force_maintenance_off_clears_the_settings_flag(): void
    {
        DB::table('settings')->insert([
            'group' => 'maintenance', 'key' => 'enabled', 'value' => '1',
            'type' => 'boolean', 'is_encrypted' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $console = $this->console();
        $console->setPdo(DB::connection()->getPdo());

        $result = $console->forceMaintenanceOff();

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertSame(1, $result['detail']['rows_updated']);
        $this->assertSame('0', DB::table('settings')->where('group', 'maintenance')->where('key', 'enabled')->value('value'));
    }

    #[Test]
    public function reset_opcache_returns_a_structured_result(): void
    {
        $result = $this->console()->resetOpcache();

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('opcache', $result['detail']);
        $this->assertIsBool($result['detail']['opcache']);
    }

    #[Test]
    public function handle_dispatches_a_posted_action_and_shows_a_result_banner(): void
    {
        file_put_contents($this->state.'/arm.flag', json_encode(['to_version' => '1.1.0']));

        $console = $this->console(['OE_RECOVERY_KEY' => 'k']);

        [$status, $state, $html] = $console->handle('k', '127.0.0.1', 'opcache_reset');

        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_READY, $state);
        $this->assertStringContainsString('class="banner ok"', $html);
        $this->assertStringContainsString('realpath cache cleared', $html);
    }

    /* ---- Helpers ------------------------------------------------------- */

    /** Encrypt a SQL string into a backup part and return its manifest entry. */
    private function encryptPart(int $runId, string $sql, string $file, string $name, string $kind): array
    {
        $rel = 'backups/'.$runId.'/db/'.$file;
        $abs = $this->disk.'/'.$rel;
        @mkdir(dirname($abs), 0775, true);

        $plainTmp = $abs.'.plain';
        file_put_contents($plainTmp, (string) gzencode($sql, 6));

        $meta = app(BackupCipher::class)->encryptFile($plainTmp, $abs);
        @unlink($plainTmp);

        return [
            'type'   => 'db',
            'name'   => $name,
            'disk'   => 'local',
            'path'   => $rel,
            'sha256' => $meta['enc_sha256'],
            'meta'   => [
                'kind'         => $kind,
                'encrypted'    => true,
                'cipher'       => $meta['cipher'],
                'frames'       => $meta['frames'],
                'plain_sha256' => $meta['plain_sha256'],
            ],
        ];
    }

    private function writeManifest(int $runId, array $parts): void
    {
        $path = $this->disk.'/backups/'.$runId.'/manifest.json';
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, json_encode([
            'schema'  => 1,
            'run_id'  => $runId,
            'profile' => 'update_safety',
            'parts'   => $parts,
        ], JSON_PRETTY_PRINT));
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
