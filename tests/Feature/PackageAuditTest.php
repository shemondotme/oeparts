<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Security\PackageAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PackageAuditTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-audit-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);

        // The audit also writes to the "migration" channel; point it at a temp file, not storage/logs.
        $this->logFile = $this->dir.DIRECTORY_SEPARATOR.'migration.log';
        config(['logging.channels.migration' => ['driver' => 'single', 'path' => $this->logFile, 'level' => 'debug']]);
        Log::forgetChannel('migration');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function audit(): PackageAudit
    {
        return new PackageAudit($this->dir.'/history.ndjson');
    }

    #[Test]
    public function record_writes_one_json_line_and_a_log_line(): void
    {
        $this->assertTrue($this->audit()->record('import.started', 'run-1', ['groups' => ['catalog', 'content'], 'mode' => 'staging']));

        $lines = file($this->dir.'/history.ndjson', FILE_IGNORE_NEW_LINES);
        $this->assertCount(1, $lines);

        $entry = json_decode($lines[0], true);
        $this->assertSame('import.started', $entry['event']);
        $this->assertSame('run-1', $entry['run_id']);
        $this->assertSame(['catalog', 'content'], $entry['groups']);
        $this->assertSame('staging', $entry['mode']);
        $this->assertNotEmpty($entry['ts']);

        $this->assertStringContainsString('import.started', (string) file_get_contents($this->logFile));
    }

    #[Test]
    public function the_unfiltered_history_reads_only_the_last_lines_of_a_huge_file_but_a_runs_own_story_is_complete(): void
    {
        $audit = $this->audit();
        $audit->record('import.armed', 'old-run');

        $lines = '';
        for ($i = 1; $i <= 3000; $i++) {
            $lines .= json_encode(['ts' => now()->toIso8601String(), 'event' => 'package.downloaded', 'run_id' => null, 'n' => $i])."
";
        }
        file_put_contents($audit->historyPath(), $lines, FILE_APPEND);

        $entries = $audit->history();

        $this->assertCount(PackageAudit::HISTORY_LINES, $entries);
        $this->assertSame(3000, end($entries)['n']);
        $this->assertSame(2501, $entries[0]['n']);
        $this->assertCount(1, $audit->history('old-run'), 'the run filter still sees its early line');
    }

    #[Test]
    public function the_same_event_for_the_same_run_is_written_once_so_a_resumed_stage_adds_no_duplicate(): void
    {
        $audit = $this->audit();

        $this->assertTrue($audit->record('import.armed', 'run-1'));
        $this->assertFalse($audit->record('import.armed', 'run-1'), 'resumed stage: nothing new written');
        $this->assertTrue($audit->record('import.armed', 'run-2'), 'another run is a different fact');
        $this->assertTrue($audit->record('import.complete', 'run-1'), 'another event is a different fact');
        $this->assertTrue($audit->record('export.share_created'), 'without a run id every call is written');
        $this->assertTrue($audit->record('export.share_created'));

        $this->assertCount(5, $audit->history());
        $this->assertCount(2, $audit->history('run-1'));
    }

    #[Test]
    public function the_record_survives_the_database_being_replaced(): void
    {
        // An import swaps the database. The durable record must be writable (and readable) without it.
        Schema::drop('activity_logs');

        $audit = $this->audit();
        $audit->record('import.armed', 'run-9', ['target' => 'populated']);

        $this->assertSame('import.armed', $audit->history('run-9')[0]['event']);
    }

    #[Test]
    public function nothing_secret_is_written(): void
    {
        $this->audit()->record('export.created', 'r', [
            'passphrase' => 'correct horse',
            'api_token' => 'abc',
            'Secret' => 's',
            'claim_id' => 'c',
            'encryption_key' => 'k',
            'nested' => ['password' => 'p', 'keep' => 'visible', 'deeper' => ['token' => 't', 'ok' => 1]],
            'source' => 'https://user:pw@shop.example.com:8443/path/x?token=abc#frag',
            'note' => 'fetched from http://old.example.org/files/a.oeparts and then verified',
            'size' => 1234,
            'flag' => true,
            'object' => new \stdClass,
        ]);

        $raw = (string) file_get_contents($this->dir.'/history.ndjson');
        $entry = json_decode($raw, true);

        foreach (['passphrase', 'api_token', 'Secret', 'claim_id', 'encryption_key'] as $dropped) {
            $this->assertArrayNotHasKey($dropped, $entry);
        }
        $this->assertSame(['keep' => 'visible', 'deeper' => ['ok' => 1]], $entry['nested']);
        $this->assertSame('https://shop.example.com/...', $entry['source']);
        $this->assertSame('fetched from http://old.example.org/... and then verified', $entry['note']);
        $this->assertSame(1234, $entry['size']);
        $this->assertTrue($entry['flag']);
        $this->assertArrayNotHasKey('object', $entry);
        foreach (['correct horse', 'pw@', 'token=abc', '8443'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw.(string) file_get_contents($this->logFile), "'{$leak}' leaked");
        }
    }

    #[Test]
    public function long_text_is_truncated(): void
    {
        $scrubbed = $this->audit()->scrub(['detail' => str_repeat('x', 900)]);

        $this->assertSame(501, mb_strlen($scrubbed['detail']));
    }

    #[Test]
    public function a_record_that_cannot_be_stored_throws_so_a_destructive_step_does_not_start_unaudited(): void
    {
        mkdir($this->dir.'/blocked.ndjson');

        try {
            (new PackageAudit($this->dir.'/blocked.ndjson'))->record('import.armed', 'run-1');
            $this->fail('An audit line that could not be written must throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('audit history', $e->getMessage());
        } finally {
            @rmdir($this->dir.'/blocked.ndjson');
        }
    }

    #[Test]
    public function concurrent_writers_never_interleave_or_lose_a_line(): void
    {
        $script = <<<'PHP'
            putenv('APP_ENV=testing');
            require $argv[1].'/vendor/autoload.php';
            $app = require $argv[1].'/bootstrap/app.php';
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            config(['logging.channels.migration' => ['driver' => 'monolog', 'handler' => Monolog\Handler\NullHandler::class]]);
            $audit = new App\Security\PackageAudit($argv[2]);
            for ($i = 0; $i < 15; $i++) { $audit->record('event.'.$argv[3].'.'.$i, 'run-'.$argv[3], ['pad' => str_repeat('x', 400)]); }
            PHP;

        $processes = [];
        foreach (range(1, 5) as $writer) {
            $p = new Process([PHP_BINARY, '-r', $script, base_path(), $this->dir.'/history.ndjson', (string) $writer]);
            $p->start();
            $processes[] = $p;
        }
        foreach ($processes as $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
        }

        $entries = $this->audit()->history();
        $this->assertCount(75, $entries, '5 writers x 15 events, every line intact and none lost');
        $this->assertCount(75, array_unique(array_map(fn ($e) => $e['event'], $entries)));
    }

    // ---- Activity Log row ---------------------------------------------------------------------

    #[Test]
    public function the_activity_log_row_names_the_admin_when_that_admin_exists(): void
    {
        $admin = Admin::factory()->create();

        $row = $this->audit()->recordActivityLog('package.import_completed', 'run-1', $admin->id, ['groups' => ['catalog'], 'passphrase' => 'x']);

        $this->assertSame($admin->id, $row->admin_id);
        $this->assertSame('package.import_completed', $row->action);
        $this->assertSame('run-1', $row->new_values['run_id']);
        $this->assertSame(['catalog'], $row->new_values['groups']);
        $this->assertArrayNotHasKey('passphrase', $row->new_values);
        $this->assertNotEmpty($row->ip_address);
    }

    #[Test]
    public function the_activity_log_row_has_no_admin_when_the_initiating_admin_is_not_in_the_imported_data(): void
    {
        // After an import replaced the admins table, the admin who started it may not exist any more.
        $this->assertNull($this->audit()->recordActivityLog('package.import_completed', 'run-1', 987654)->admin_id);
        $this->assertNull($this->audit()->recordActivityLog('package.import_completed', 'run-1', null)->admin_id);
        $this->assertSame(2, ActivityLog::where('action', 'package.import_completed')->count());
    }
}
