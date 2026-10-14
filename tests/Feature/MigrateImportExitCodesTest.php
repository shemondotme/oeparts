<?php

namespace Tests\Feature;

use App\Console\Commands\Migration\MigrateImport;
use App\Services\Backup\BackupLock;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStateStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\BuildsImportPackages;
use Tests\TestCase;

/**
 * Phase 8.2: every exit code of the import command means one thing, and the JSON a script reads says the same as the exit code.
 */
class MigrateImportExitCodesTest extends TestCase
{
    use BuildsImportPackages;
    use RefreshDatabase;

    private string $passphraseFile = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();
        $this->passphraseFile = $this->workDir.'/passphrase.txt';
        file_put_contents($this->passphraseFile, $this->passphrase);
        chmod($this->passphraseFile, 0600);
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        app(BackupLock::class)->release();
        $this->cleanUpImportTest();

        parent::tearDown();
    }

    /** @return array{0: int, 1: string} */
    private function cli(array $args): array
    {
        $buffer = new BufferedOutput;
        $exit = Artisan::call('oeparts:migrate:import', $args + ['--no-interaction' => true, '--passphrase-file' => $this->passphraseFile], $buffer);

        return [$exit, $buffer->fetch()];
    }

    /** @return array<int, array<string, mixed>> */
    private function events(string $text): array
    {
        return array_values(array_filter(array_map(fn (string $l): mixed => json_decode($l, true), explode("\n", trim($text))), 'is_array'));
    }

    private function allFlags(string $package, array $more = []): array
    {
        return ['source' => $package, '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE', '--replace-populated' => true, '--accept-warnings' => true] + $more;
    }

    #[Test]
    public function the_exit_codes_are_the_eleven_the_plan_names_and_nothing_else(): void
    {
        $this->assertSame([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10], array_map(fn (ExitCode $c): int => $c->value, ExitCode::cases()));
    }

    #[Test]
    public function a_system_that_is_busy_exits_with_six_when_the_confirmation_finds_the_shared_lock_taken(): void
    {
        $package = $this->buildImportPackage('busy.oeparts');
        $run = $this->runToPreflight($package, ['door' => 'cli']);
        $this->assertSame('awaiting_confirmation', $run['progress']['status']);

        app(BackupLock::class)->acquire('backup:someone-else'); // a backup starts after the checks and before the confirmation

        [$exit, $text] = $this->cli(['--resume' => true, '--run' => $run['state']->id] + $this->allFlags($package));

        $this->assertSame(ExitCode::SystemBusy->value, $exit, $text);
        $this->assertFalse((new ImportMarker)->isActive(), 'nothing was changed');
    }

    #[Test]
    public function warnings_alone_exit_with_nine_only_under_strict(): void
    {
        $package = $this->buildImportPackage('warns.oeparts'); // SQLite always warns that it is only meant for tests

        [$normal] = $this->cli(['source' => $package, '--preflight-only' => true]);
        [$strict, $text] = $this->cli(['source' => $package, '--preflight-only' => true, '--strict' => true]);

        $this->assertSame(ExitCode::Ok->value, $normal);
        $this->assertSame(ExitCode::FinishedWithWarnings->value, $strict, $text);
    }

    #[Test]
    public function ctrl_c_stops_between_steps_with_eight_and_says_how_to_carry_on(): void
    {
        $this->app->make(Kernel::class)->registerCommand(new class extends MigrateImport
        {
            public function __construct()
            {
                parent::__construct();
                $this->stop = true; // what the signal handler does
            }
        });

        [$exit, $text] = $this->cli($this->allFlags($this->buildImportPackage('stopped.oeparts')));

        $this->assertSame(ExitCode::Cancelled->value, $exit, $text);
        $this->assertStringContainsString('--resume', $text);
        $this->assertFalse((new ImportMarker)->isActive(), 'stopped before the site was touched');
    }

    #[Test]
    public function the_last_json_line_always_agrees_with_the_exit_code_and_nothing_but_json_is_on_standard_output(): void
    {
        $cases = [
            'usage' => ['--json' => true],
            'unreadable' => ['source' => $this->workDir.'/missing.oeparts', '--launch' => 'live', '--preset' => 'full_live', '--json' => true],
            'checks ok' => ['source' => $this->buildImportPackage('ok.oeparts'), '--preflight-only' => true, '--json' => true],
            'blocked' => ['source' => $this->buildImportPackage('blocked.oeparts', ['migrations' => array_slice($this->ownMigrations(), 0, -2)]), '--launch' => 'live', '--preset' => 'full_live', '--confirm' => 'REPLACE', '--json' => true],
            'not confirmed' => ['source' => $this->buildImportPackage('unconfirmed.oeparts'), '--launch' => 'live', '--preset' => 'full_live', '--json' => true],
        ];

        foreach ($cases as $name => $args) {
            [$exit, $text] = $this->cli($args);
            $events = $this->events($text);

            $this->assertNotEmpty($events, $name);
            $this->assertCount(count(array_filter(explode("\n", trim($text)))), $events, "{$name}: every line of standard output is a JSON event");
            $last = end($events);
            $this->assertSame('result', $last['event'], $name);
            $this->assertSame($exit, $last['exit_code'], $name);
            $this->assertSame($exit === 0, $last['ok'], $name);
        }
    }

    #[Test]
    public function a_package_that_cannot_be_opened_never_leaves_a_run_behind(): void
    {
        file_put_contents($this->passphraseFile, 'WRONG-0000-1111-2222-3333-4444');
        $before = array_map(fn ($s): string => $s->id, (new ImportStateStore)->all());

        [$exit] = $this->cli(['source' => $this->buildImportPackage('locked.oeparts'), '--launch' => 'live', '--preset' => 'full_live']);

        $this->assertSame(ExitCode::WrongPassphrase->value, $exit);
        $this->assertSame($before, array_map(fn ($s): string => $s->id, (new ImportStateStore)->all()));
    }
}
