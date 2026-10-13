<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Package\Kdf;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * oeparts:migrate:inspect, and through it the two shared command-line traits: where a passphrase
 * may come from (ReadsPassphrase) and what a script sees on standard output (EmitsJson).
 */
class MigrationInspectCommandTest extends TestCase
{
    use BuildsPackages;

    /** @var array<string, string> */
    private array $sealed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
        $this->app->instance(Kdf::class, $this->weakKdf());

        putenv('OEPARTS_PASSPHRASE');
        putenv('OE_MIGRATE_PASSPHRASE');

        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', '{"a":1}', ['group' => 'settings']);
        $writer->addEntryFromString('files/blob.bin', 'files', str_repeat('x', 5000), ['group' => 'files']);
        $writer->addSecrets("{\"key\":\"k\",\"value\":\"v\"}\n");
        $this->sealed = $writer->seal();
    }

    protected function tearDown(): void
    {
        putenv('OEPARTS_PASSPHRASE');
        putenv('OE_MIGRATE_PASSPHRASE');
        $this->removeWorkDir();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{0: int, 1: string}
     */
    private function inspect(array $options = [], ?string $path = null): array
    {
        $buffer = new BufferedOutput;
        $exit = Artisan::call('oeparts:migrate:inspect', ['package' => $path ?? $this->packagePath(), '--no-interaction' => true] + $options, $buffer);

        return [$exit, $buffer->fetch()];
    }

    private function passphraseFile(string $content, int $mode = 0600): string
    {
        $path = $this->workDir.'/pass.txt';
        file_put_contents($path, $content);
        chmod($path, $mode);

        return $path;
    }

    /** @return array<int, array<string, mixed>> */
    private function events(string $output): array
    {
        $events = [];

        foreach (array_filter(explode("\n", $output), fn (string $l): bool => trim($l) !== '') as $line) {
            $decoded = json_decode($line, true);
            $this->assertIsArray($decoded, "every standard-output line is JSON under --json, got: {$line}");
            $events[] = $decoded;
        }

        return $events;
    }

    #[Test]
    public function the_header_alone_is_shown_without_any_passphrase(): void
    {
        [$exit, $out] = $this->inspect(['--header-only' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('AES-256-GCM', $out);
        $this->assertStringContainsString('PBKDF2', $out);
        $this->assertStringNotContainsString('INCOMPLETE', $out);
    }

    #[Test]
    public function with_the_passphrase_it_shows_the_fingerprint_and_the_contents(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase."\n"), '--files' => true]);

        $this->assertSame(0, $exit, $out);
        $this->assertStringContainsString($this->sealed['fingerprint'], $out);
        $this->assertStringContainsString('old.example', $out);
        $this->assertStringContainsString('report/a.json', $out);
        $this->assertStringContainsString('files/blob.bin', $out);
        $this->assertStringNotContainsString('secrets', strtolower(str_replace('Settings with secrets', '', $out)), 'the secrets entry is not listed');
        $this->assertStringContainsString('included, behind a separate unlock', $out);
        $this->assertStringNotContainsString($this->passphrase, $out);
    }

    #[Test]
    public function verify_checks_the_whole_package(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase), '--verify' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('The package is intact.', $out);
    }

    #[Test]
    public function a_damaged_package_fails_verification_with_exit_four(): void
    {
        $bytes = (string) file_get_contents($this->packagePath());
        $bytes[300] = chr(ord($bytes[300]) ^ 1);
        file_put_contents($this->packagePath('damaged.oeparts'), $bytes);

        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase), '--verify' => true], $this->packagePath('damaged.oeparts'));

        $this->assertSame(4, $exit, $out);
        $this->assertStringNotContainsString('The package is intact.', $out);
    }

    #[Test]
    public function a_wrong_passphrase_exits_five_in_plain_words(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile('this is definitely wrong')]);

        $this->assertSame(5, $exit);
        $this->assertStringContainsString('What to do:', $out);
        $this->assertStringNotContainsString('Exception', $out);
    }

    #[Test]
    public function an_incomplete_package_says_so_and_exits_four_with_a_passphrase_but_zero_for_the_header(): void
    {
        file_put_contents($this->packagePath('cut.oeparts'), substr((string) file_get_contents($this->packagePath()), 0, 700));

        [$exit, $out] = $this->inspect(['--header-only' => true], $this->packagePath('cut.oeparts'));
        $this->assertSame(0, $exit);
        $this->assertStringContainsString('INCOMPLETE', $out);

        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase)], $this->packagePath('cut.oeparts'));
        $this->assertSame(4, $exit);
        $this->assertStringContainsString('What to do:', $out);
    }

    #[Test]
    public function something_that_is_not_a_package_exits_four_and_a_missing_file_exits_two(): void
    {
        file_put_contents($this->packagePath('text.oeparts'), str_repeat('hello ', 100));

        [$exit] = $this->inspect(['--header-only' => true], $this->packagePath('text.oeparts'));
        $this->assertSame(4, $exit);

        [$exit, $out] = $this->inspect(['--header-only' => true], $this->packagePath('nope.oeparts'));
        $this->assertSame(2, $exit);
        $this->assertStringContainsString('no readable file', $out);
    }

    #[Test]
    public function json_mode_prints_only_json_lines_ending_in_one_result(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase), '--files' => true, '--verify' => true, '--json' => true]);

        $events = $this->events($out);
        $names = array_column($events, 'event');

        $this->assertSame(0, $exit);
        $this->assertContains('package', $names);
        $this->assertContains('entry', $names);
        $this->assertContains('progress', $names);
        $this->assertSame('result', end($names));
        $this->assertSame(1, count(array_filter($names, fn ($n): bool => $n === 'result')));

        $result = end($events);
        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['exit_code']);
        $this->assertSame($this->sealed['fingerprint'], $result['package']['fingerprint']);
        $this->assertSame(2, count(array_filter($names, fn ($n): bool => $n === 'entry')) - 1, 'the meta plus two entries, the secrets entry hidden');
        $this->assertStringNotContainsString($this->passphrase, $out);
    }

    #[Test]
    public function json_mode_reports_errors_as_events_with_the_catalogue_code(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile('wrong wrong wrong wrong'), '--json' => true]);

        $events = $this->events($out);

        $this->assertSame(5, $exit);
        $this->assertSame('error', $events[0]['event']);
        $this->assertSame('passphrase_wrong', $events[0]['code']);
        $this->assertNotSame('', $events[0]['fix']);
        $this->assertFalse(end($events)['ok']);
        $this->assertSame(5, end($events)['exit_code']);
    }

    // ---- where the passphrase may come from ------------------------------------------------

    #[Test]
    public function a_passphrase_on_the_command_line_is_refused_and_never_echoed(): void
    {
        [$exit, $out] = $this->inspect(['--passphrase' => $this->passphrase]);

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('not accepted', $out);
        $this->assertStringNotContainsString($this->passphrase, $out);
    }

    #[Test]
    public function without_any_source_a_non_interactive_run_exits_two_and_says_what_to_do(): void
    {
        [$exit, $out] = $this->inspect();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('OEPARTS_PASSPHRASE', $out);
        $this->assertStringContainsString('--passphrase-file', $out);
    }

    #[Test]
    public function the_environment_is_a_source_and_the_file_beats_it(): void
    {
        putenv('OE_MIGRATE_PASSPHRASE='.$this->passphrase);
        [$exit] = $this->inspect();
        $this->assertSame(0, $exit, 'OE_MIGRATE_PASSPHRASE');

        putenv('OE_MIGRATE_PASSPHRASE=wrong wrong wrong wrong');
        putenv('OEPARTS_PASSPHRASE='.$this->passphrase);
        [$exit] = $this->inspect();
        $this->assertSame(0, $exit, 'OEPARTS_PASSPHRASE wins over OE_MIGRATE_PASSPHRASE');

        putenv('OEPARTS_PASSPHRASE=wrong wrong wrong wrong');
        [$exit] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase)]);
        $this->assertSame(0, $exit, 'the file wins over the environment');
    }

    #[Test]
    public function only_one_line_ending_is_removed_from_a_passphrase_file(): void
    {
        foreach (['
', '
'] as $ending) {
            [$exit] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase.$ending)]);
            $this->assertSame(0, $exit);
        }

        [$exit] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase.'

')]);
        $this->assertSame(5, $exit, 'a second line ending is part of the text, so it is not the passphrase');
    }

    #[Test]
    public function an_empty_missing_or_oversized_passphrase_file_exits_two(): void
    {
        foreach ([$this->passphraseFile(''), $this->passphraseFile("\n"), $this->workDir.'/missing.txt', $this->passphraseFile(str_repeat('x', 2000))] as $file) {
            [$exit] = $this->inspect(['--passphrase-file' => $file]);
            $this->assertSame(2, $exit, $file);
        }
    }

    #[Test]
    public function a_passphrase_file_other_users_can_read_is_warned_about(): void
    {
        if (DIRECTORY_SEPARATOR !== '/') {
            $this->markTestSkipped('POSIX file modes only.');
        }

        [$exit, $out] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase, 0644)]);

        $this->assertSame(0, $exit, 'a warning, not a refusal');
        $this->assertStringContainsString('chmod 600', $out);

        [, $quiet] = $this->inspect(['--passphrase-file' => $this->passphraseFile($this->passphrase, 0600)]);
        $this->assertStringNotContainsString('chmod 600', $quiet);
    }

    #[Test]
    public function an_interactive_run_asks_for_the_passphrase_with_a_hidden_prompt(): void
    {
        // Under test Laravel replaces the terminal prompt by its console-question fallback; the
        // real terminal prompt is covered by the container rehearsal.
        $this->artisan('oeparts:migrate:inspect', ['package' => $this->packagePath()])
            ->expectsQuestion('Passphrase', $this->passphrase)
            ->assertExitCode(0);
    }
}
