<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\PackageWriter;
use App\Services\Migration\Package\WriterLedger;
use App\Support\AtomicJsonFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * A 20 GB export takes hours; the process that builds it WILL be killed, restarted or lose its
 * terminal. Every crash point of the per-entry commit sequence (frames written and fsynced, ledger
 * line appended, state.json replaced) is reproduced here, plus a real process killed in the middle
 * of an entry, and in each case resume must produce a package that holds every entry exactly once.
 */
class WriterResumeTest extends TestCase
{
    use BuildsPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    /** @return array<string, string> name => plaintext of every data entry in a sealed package */
    private function dataEntries(array $walk): array
    {
        $byName = [];
        foreach ($walk['toc']['entries'] as $entry) {
            if ($entry['id'] !== 0) {
                $byName[$entry['name']] = $walk['entries'][$entry['id']]['plain'];
            }
        }

        return $byName;
    }

    private function ledger(): WriterLedger
    {
        return new WriterLedger($this->packagePath().'.part.ledger');
    }

    private function state(): AtomicJsonFile
    {
        return $this->ledger()->state();
    }

    #[Test]
    public function a_clean_stop_resumes_where_it_left_off(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        $writer->addEntryFromString('report/b.json', 'report', 'BBB');
        unset($writer); // the process ends without sealing

        $resumed = $this->resumeWriter();
        $this->assertSame([0, 1, 2], array_keys($resumed->entryRecords()));
        $resumed->addEntryFromString('report/c.json', 'report', 'CCC');
        $result = $resumed->seal();

        $walk = $this->walk($result['path']);
        $this->assertSame(['report/a.json' => 'AAA', 'report/b.json' => 'BBB', 'report/c.json' => 'CCC'], $this->dataEntries($walk));
    }

    #[Test]
    public function bytes_written_after_the_last_commit_are_cut_off_on_resume(): void
    {
        // Crash after the frames were written but before the ledger line: junk beyond the commit point.
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        $committed = $writer->committedOffset();
        $part = $writer->partPath();
        unset($writer);

        file_put_contents($part, random_bytes(5000), FILE_APPEND);
        $this->assertGreaterThan($committed, filesize($part));

        $resumed = $this->resumeWriter();
        $this->assertSame($committed, $resumed->committedOffset());
        $resumed->addEntryFromString('report/b.json', 'report', 'BBB');
        $result = $resumed->seal();

        $walk = $this->walk($result['path']);
        $this->assertSame(['report/a.json' => 'AAA', 'report/b.json' => 'BBB'], $this->dataEntries($walk), 'the junk is gone and the entries are intact');
    }

    #[Test]
    public function a_ledger_line_the_state_never_acknowledged_is_dropped(): void
    {
        // Crash after the ledger line was appended but before state.json was replaced.
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        unset($writer);

        $this->ledger()->appendEntry(['id' => 2, 'name' => 'ghost', 'kind' => 'report', 'offset' => 99999, 'enc_len' => 10, 'plain_len' => 1, 'key_class' => 0]);

        $resumed = $this->resumeWriter();
        $this->assertSame([0, 1], array_keys($resumed->entryRecords()), 'the unacknowledged line is not an entry');
        $resumed->addEntryFromString('report/b.json', 'report', 'BBB');
        $result = $resumed->seal();

        $this->assertSame(['report/a.json' => 'AAA', 'report/b.json' => 'BBB'], $this->dataEntries($this->walk($result['path'])));
    }

    #[Test]
    public function a_torn_state_file_falls_back_to_the_previous_commit_and_the_lost_entry_is_written_again(): void
    {
        // Crash while state.json was being replaced.
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        $writer->addEntryFromString('report/b.json', 'report', 'BBB');
        $writer->addEntryFromString('report/c.json', 'report', 'CCC');
        unset($writer);

        file_put_contents($this->packagePath().'.part.ledger/state.json', '{"package_id": "ab12", "committed_off');

        $resumed = $this->resumeWriter();
        $this->assertSame([0, 1, 2], array_keys($resumed->entryRecords()), 'back to the commit after entry b: entry c was not acknowledged');

        $resumed->addEntryFromString('report/c.json', 'report', 'CCC');
        $result = $resumed->seal();

        $this->assertSame(['report/a.json' => 'AAA', 'report/b.json' => 'BBB', 'report/c.json' => 'CCC'], $this->dataEntries($this->walk($result['path'])));
    }

    #[Test]
    public function a_source_that_ends_early_leaves_the_writer_usable_and_the_file_unchanged(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        $committed = $writer->committedOffset();

        $short = fopen('php://temp', 'r+b');
        fwrite($short, str_repeat('x', 100));
        rewind($short);

        try {
            $writer->addEntryFromStream('files/vol-0000.oevol', 'files.volume', $short, 5000);
            $this->fail('A source shorter than announced must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ended', $e->getMessage());
        }

        $this->assertSame($committed, $writer->committedOffset());
        $this->assertSame($committed, filesize($writer->partPath()), 'the half-written entry was cut off');

        $writer->addEntryFromString('files/vol-0000.oevol', 'files.volume', 'second try');
        $this->assertSame(['report/a.json' => 'AAA', 'files/vol-0000.oevol' => 'second try'], $this->dataEntries($this->walk($writer->seal()['path'])));
    }

    #[Test]
    public function a_source_longer_than_announced_is_refused(): void
    {
        $writer = $this->newWriter();

        $long = fopen('php://temp', 'r+b');
        fwrite($long, str_repeat('x', 5000));
        rewind($long);

        try {
            $writer->addEntryFromStream('files/vol-0000.oevol', 'files.volume', $long, 100);
            $this->fail('A source longer than announced must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('longer', $e->getMessage());
        }

        $writer->addEntryFromString('report/ok.json', 'report', '{}');
        $this->assertSame(['report/ok.json' => '{}'], $this->dataEntries($this->walk($writer->seal()['path'])));
    }

    #[Test]
    public function a_process_killed_in_the_middle_of_an_entry_resumes_cleanly(): void
    {
        $autoload = dirname(__DIR__, 4).'/vendor/autoload.php';
        $script = <<<'PHP'
            require $argv[1];

            // A source that "kills the process" (exit 9: no cleanup, like SIGKILL) after 1.5 MiB.
            class DieStream {
                public $context; private int $pos = 0;
                public function stream_open($path, $mode, $options, &$opened) { return true; }
                public function stream_read($count) {
                    if ($this->pos >= 1_500_000) { exit(9); }
                    $this->pos += $count;
                    return str_repeat('K', $count);
                }
                public function stream_eof() { return false; }
                public function stream_stat() { return []; }
            }
            stream_wrapper_register('diestream', 'DieStream');

            $kdf = new App\Services\Migration\Package\Kdf(['passesMin'=>1,'passesMax'=>12,'memoryKibMin'=>64,'memoryKibMax'=>1<<20,'pbkdf2Min'=>1000,'pbkdf2Max'=>10000000]);
            $w = App\Services\Migration\Package\PackageWriter::create($argv[2], $argv[3], ['k'=>'v'], $kdf, kdfId: 2, kdfParams: ['p1'=>2000,'p2'=>0]);
            $w->addEntryFromString('report/a.json', 'report', 'AAA');
            $w->addEntryFromStream('files/vol-0000.oevol', 'files.volume', fopen('diestream://x', 'rb'), 3 * 1024 * 1024);
            echo 'NOT KILLED';
            PHP;

        $process = new Process([PHP_BINARY, '-r', $script, $autoload, $this->packagePath(), $this->passphrase]);
        $process->run();

        $this->assertSame(9, $process->getExitCode(), 'the process died mid-entry: '.$process->getOutput().$process->getErrorOutput());
        $this->assertFileExists($this->packagePath().'.part');
        $this->assertFileDoesNotExist($this->packagePath());

        $resumed = $this->resumeWriter();
        $this->assertSame([0, 1], array_keys($resumed->entryRecords()), 'only the entry committed before the kill survives');

        $resumed->addEntryFromString('files/vol-0000.oevol', 'files.volume', str_repeat('K', 3 * 1024 * 1024));
        $result = $resumed->seal();

        $walk = $this->walk($result['path']);
        $entries = $this->dataEntries($walk);
        $this->assertSame('AAA', $entries['report/a.json']);
        $this->assertSame(str_repeat('K', 3 * 1024 * 1024), $entries['files/vol-0000.oevol']);
        $this->assertCount(2, $entries, 'each entry exactly once');
    }

    #[Test]
    public function resuming_with_the_wrong_passphrase_is_refused(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        unset($writer);

        $this->expectException(WrongPassphraseException::class);
        PackageWriter::resume($this->packagePath(), 'NOT-THE-RIGHT-PASSPHRASE-AT-ALL', $this->weakKdf());
    }

    #[Test]
    public function there_is_nothing_to_resume_when_nothing_was_started(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no unfinished package');

        $this->resumeWriter('never-started.oeparts');
    }

    #[Test]
    public function aborting_removes_the_part_and_the_ledger_and_allows_a_fresh_start(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'AAA');
        $writer->abort();

        $this->assertFileDoesNotExist($this->packagePath().'.part');
        $this->assertDirectoryDoesNotExist($this->packagePath().'.part.ledger');

        $fresh = $this->newWriter();
        $fresh->addEntryFromString('report/b.json', 'report', 'BBB');
        $this->assertSame(['report/b.json' => 'BBB'], $this->dataEntries($this->walk($fresh->seal()['path'])));
    }

    #[Test]
    public function a_package_that_was_resumed_is_byte_for_byte_a_valid_package_of_the_same_entries(): void
    {
        // Uninterrupted and interrupted-then-resumed packages differ only in their random IVs and salt:
        // same entry names, same plaintext hashes, same layout.
        $direct = $this->newWriter('direct.oeparts');
        $direct->addEntryFromString('report/a.json', 'report', 'AAA');
        $direct->addEntryFromString('report/b.json', 'report', 'BBB');
        $directWalk = $this->walk($direct->seal()['path']);

        $broken = $this->newWriter('broken.oeparts');
        $broken->addEntryFromString('report/a.json', 'report', 'AAA');
        unset($broken);
        $resumed = $this->resumeWriter('broken.oeparts');
        $resumed->addEntryFromString('report/b.json', 'report', 'BBB');
        $resumedWalk = $this->walk($resumed->seal()['path']);

        // The meta entry holds the random package id, so it differs by design; every data entry must match.
        $names = fn (array $walk) => array_values(array_map(fn ($e) => [$e['name'], $e['plain_sha256'], $e['plain_len']], array_filter($walk['toc']['entries'], fn ($e) => $e['name'] !== 'meta')));
        $this->assertSame($names($directWalk), $names($resumedWalk));
        $this->assertSame($directWalk['size'], $resumedWalk['size'], 'same layout, same length');
    }
}
