<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\ExitCode;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationProgress;
use App\Services\Migration\Ux\MigrationText;
use App\Support\PhpSize;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 1.3: the shared vocabulary of the migration feature (error codes, exit codes, progress,
 * text lookup, size parsing, config, log channels). Nothing here changes behaviour anywhere yet.
 */
class MigrationFoundationsTest extends TestCase
{
    // ---- error catalogue and text ---------------------------------------------------------

    #[Test]
    public function every_error_code_has_a_title_a_message_and_a_fix_in_plain_english(): void
    {
        $catalogue = require lang_path('en/migration.php');

        foreach (MigrationErrorCode::cases() as $code) {
            foreach (['title', 'message', 'fix'] as $part) {
                $text = $catalogue['error'][$code->value][$part] ?? null;

                $this->assertIsString($text, "{$code->value}.{$part} is missing");
                $this->assertNotSame('', trim($text), "{$code->value}.{$part} is empty");
                $this->assertStringNotContainsString('Exception', $text, "{$code->value}.{$part} must not leak technical wording");
            }
        }
    }

    #[Test]
    public function the_catalogue_has_no_text_for_a_code_that_does_not_exist(): void
    {
        $catalogue = require lang_path('en/migration.php');
        $known = array_map(fn (MigrationErrorCode $c) => $c->value, MigrationErrorCode::cases());

        $this->assertSame([], array_values(array_diff(array_keys($catalogue['error']), $known)), 'orphan text without an enum case');
    }

    #[Test]
    public function the_error_code_values_are_unique_snake_case_and_map_to_their_lang_key(): void
    {
        $values = array_map(fn (MigrationErrorCode $c) => $c->value, MigrationErrorCode::cases());

        $this->assertSame($values, array_unique($values));
        foreach ($values as $value) {
            $this->assertMatchesRegularExpression('/^[a-z]+(_[a-z]+)*$/', $value);
        }
        $this->assertSame('migration.error.passphrase_wrong.fix', MigrationErrorCode::PassphraseWrong->langKey('fix'));
    }

    #[Test]
    public function placeholders_are_filled_from_the_parameters(): void
    {
        $text = new MigrationText;

        $this->assertSame(
            'Part 7 of the file is damaged.',
            $text->error(MigrationErrorCode::PackageCorrupt, ['part' => 7])['message']
        );
        $this->assertStringContainsString('Wait 45 seconds', $text->error(MigrationErrorCode::PassphraseLocked, ['seconds' => 45], installer: true)['fix']);
    }

    #[Test]
    public function an_exception_carries_its_code_and_parameters_for_the_text_lookup(): void
    {
        $exception = new MigrationException(MigrationErrorCode::KdfMemory, ['mb' => 256]);

        $this->assertSame(MigrationErrorCode::KdfMemory, $exception->errorCode);
        $this->assertSame('Migration error kdf_memory {"mb":256}', $exception->getMessage());
        $this->assertStringContainsString('256 MB', (new MigrationText)->forException($exception)['fix']);
    }

    #[Test]
    public function installer_mode_reads_the_file_without_the_translator_the_database_or_a_log_warning(): void
    {
        // The installer runs before the database exists, and the project's translator queries the
        // database for overrides (and logs a warning when it cannot). Installer mode must not use it.
        Log::spy();
        $this->app->forgetInstance('translator');
        $this->app->bind('translator', fn () => throw new \LogicException('The translator must not be used in installer mode.'));

        $text = (new MigrationText)->error(MigrationErrorCode::PackageNotFound, installer: true);

        $this->assertSame('File not found', $text['title']);
        $this->assertSame('We cannot find that file.', $text['message']);
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function an_unknown_key_comes_back_as_the_key_instead_of_failing(): void
    {
        $this->assertSame('error.nothing_here.title', (new MigrationText)->get('error.nothing_here.title', installer: true));
    }

    // ---- exit codes ------------------------------------------------------------------------

    #[Test]
    public function exit_codes_match_the_documented_contract(): void
    {
        $this->assertSame([
            'Ok' => 0, 'Failed' => 1, 'Usage' => 2, 'PreflightBlocked' => 3, 'PackageUnreadable' => 4,
            'WrongPassphrase' => 5, 'SystemBusy' => 6, 'NotConfirmed' => 7, 'Cancelled' => 8,
            'FinishedWithWarnings' => 9, 'NotFinished' => 10,
        ], collect(ExitCode::cases())->mapWithKeys(fn (ExitCode $c) => [$c->name => $c->value])->all());
    }

    // ---- progress --------------------------------------------------------------------------

    #[Test]
    public function the_bar_never_shows_100_until_the_run_has_really_succeeded(): void
    {
        $running = new MigrationProgress('r1', 'import', MigrationProgress::RUNNING, rawPercent: 100.0);
        $failed = new MigrationProgress('r1', 'import', MigrationProgress::FAILED, rawPercent: 100.0);
        $success = new MigrationProgress('r1', 'import', MigrationProgress::SUCCESS, rawPercent: 40.0);

        $this->assertSame(99, $running->percent());
        $this->assertSame(99, $failed->percent());
        $this->assertSame(100, $success->percent());
        $this->assertTrue($failed->isTerminal());
        $this->assertFalse($running->isTerminal());
    }

    #[Test]
    public function the_estimate_stays_hidden_until_there_is_enough_signal(): void
    {
        $make = fn (float $percent, int $elapsed, string $status = MigrationProgress::RUNNING) => (new MigrationProgress('r', 'import', $status, rawPercent: $percent, elapsedSeconds: $elapsed))->etaSeconds();

        $this->assertNull($make(2.0, 60), 'under 3%');
        $this->assertNull($make(50.0, 9), 'under 10 seconds');
        $this->assertNull($make(50.0, 600, MigrationProgress::SUCCESS), 'finished');
        $this->assertSame(100, $make(50.0, 100), 'half done in 100 s -> 100 s left');
        $this->assertSame(300, $make(25.0, 100));
    }

    #[Test]
    public function progress_serialises_with_the_documented_keys(): void
    {
        $array = (new MigrationProgress('r9', 'fetch', MigrationProgress::RUNNING, rawPercent: 12.7, phase: 'download', phaseIndex: 1, phaseCount: 4))->toArray();

        $this->assertSame(12, $array['percent']);
        $this->assertFalse($array['done']);
        foreach (['runId', 'kind', 'status', 'done', 'percent', 'phase', 'phaseIndex', 'phaseCount', 'message', 'counters', 'elapsedSeconds', 'etaSeconds', 'workedMs', 'working', 'error', 'warnings', 'resumable', 'canRollback', 'canRestart'] as $key) {
            $this->assertArrayHasKey($key, $array);
        }
    }

    // ---- php.ini sizes ---------------------------------------------------------------------

    /** @return array<string, array{0: string|int|false|null, 1: int}> */
    public static function sizes(): array
    {
        return [
            'plain bytes' => ['1048576', 1048576],
            'int' => [2048, 2048],
            'kilobytes' => ['512K', 512 * 1024],
            'lowercase m' => ['256m', 256 * 1024 * 1024],
            'megabytes' => ['256M', 256 * 1024 * 1024],
            'gigabytes' => ['1G', 1024 ** 3],
            'with unit b' => ['64MB', 64 * 1024 * 1024],
            'padded' => [' 128M ', 128 * 1024 * 1024],
            'unlimited' => ['-1', PHP_INT_MAX],
            'unreadable' => ['lots', 0],
            'empty' => ['', 0],
            'false' => [false, 0],
            'null' => [null, 0],
        ];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function php_ini_sizes_are_parsed_to_bytes(string|int|false|null $input, int $expected): void
    {
        $this->assertSame($expected, PhpSize::toBytes($input));
    }

    #[Test]
    public function bytes_are_formatted_for_people(): void
    {
        $this->assertSame('0 B', PhpSize::format(0));
        $this->assertSame('512 B', PhpSize::format(512));
        $this->assertSame('12 KB', PhpSize::format(12 * 1024));
        $this->assertSame('256 MB', PhpSize::format(256 * 1024 ** 2));
        $this->assertSame('1.5 GB', PhpSize::format((int) (1.5 * 1024 ** 3)));
        $this->assertSame('unlimited', PhpSize::format(PHP_INT_MAX));
    }

    // ---- config and log channels -----------------------------------------------------------

    #[Test]
    public function the_config_skeleton_has_the_settled_defaults(): void
    {
        $this->assertSame(500000, config('migration.import.fresh_max_rows'));
        $this->assertSame(500000, config('migration.import.recovery_max_rows'));
        $this->assertSame(1800, config('migration.import.heartbeat_stale_seconds'));
        $this->assertSame(7200, config('migration.import.long_stage_seconds'));
        $this->assertSame(1800, config('migration.export.stale_after_seconds'));
        $this->assertSame(180, config('migration.export.freeze_max_minutes'));
        $this->assertSame(120, config('migration.export.hold_auto_release_minutes'));
        $this->assertSame('migration', config('migration.queues.migration'));
        $this->assertSame(['critical', 'default', 'low'], config('migration.queues.customer'));
        $this->assertContains(config('migration.queues.probe'), config('migration.queues.customer'), 'the probe runs on a customer queue');
        $this->assertSame(7, config('migration.retention.export_days'));
    }

    #[Test]
    public function the_migration_and_instance_mail_log_channels_exist_and_write_to_their_own_files(): void
    {
        $this->assertSame('daily', config('logging.channels.migration.driver'));
        $this->assertStringEndsWith('logs'.DIRECTORY_SEPARATOR.'migration.log', config('logging.channels.migration.path'));
        $this->assertSame('daily', config('logging.channels.instance_mail.driver'));
        $this->assertStringEndsWith('logs'.DIRECTORY_SEPARATOR.'instance-mail.log', config('logging.channels.instance_mail.path'));
    }
}
