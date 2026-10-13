<?php

namespace Tests\Unit;

use App\Support\AtomicJsonFile;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AtomicJsonFileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-atomic-'.getmypid().'-'.uniqid();
        mkdir($this->dir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            } elseif (is_dir($file) && ! in_array(basename($file), ['.', '..'], true)) {
                @rmdir($file);
            }
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function file(string $name = 'state.json'): AtomicJsonFile
    {
        return new AtomicJsonFile($this->dir.DIRECTORY_SEPARATOR.$name);
    }

    #[Test]
    public function it_round_trips_a_document_and_creates_missing_directories(): void
    {
        $file = new AtomicJsonFile($this->dir.'/a/b/state.json');

        $file->write(['step' => 'db_data', 'name' => 'Ünïcode / slash', 'n' => 3]);

        $this->assertSame(['step' => 'db_data', 'name' => 'Ünïcode / slash', 'n' => 3], $file->read());

        @unlink($this->dir.'/a/b/state.json');
        @unlink($this->dir.'/a/b/state.json.lock');
        @unlink($this->dir.'/a/b/state.json.prev');
        @rmdir($this->dir.'/a/b');
        @rmdir($this->dir.'/a');
    }

    #[Test]
    public function a_missing_file_reads_as_null_not_as_an_error(): void
    {
        $this->assertNull($this->file()->read());
    }

    #[Test]
    public function the_previous_good_version_is_kept_and_no_temp_file_is_left_behind(): void
    {
        $file = $this->file();
        $file->write(['v' => 1]);
        $file->write(['v' => 2]);

        $this->assertSame(['v' => 2], $file->read());
        $this->assertSame(['v' => 1], json_decode((string) file_get_contents($file->path().'.prev'), true));
        $this->assertSame([], glob($this->dir.'/*.tmp') ?: [], 'the temp file was renamed away');
    }

    #[Test]
    public function a_torn_write_falls_back_to_the_previous_version(): void
    {
        $file = $this->file();
        $file->write(['v' => 1]);
        $file->write(['v' => 2]);

        // A crash left the main file half written.
        file_put_contents($file->path(), '{"v": 3, "step": "db_da');

        $this->assertSame(['v' => 1], $file->read(), 'the last complete version is used, not an empty state');
    }

    #[Test]
    public function an_empty_main_file_also_falls_back(): void
    {
        $file = $this->file();
        $file->write(['v' => 1]);
        $file->write(['v' => 2]);
        file_put_contents($file->path(), '');

        $this->assertSame(['v' => 1], $file->read());
    }

    #[Test]
    public function when_a_file_exists_but_nothing_is_readable_it_throws_instead_of_pretending_the_state_is_empty(): void
    {
        $file = $this->file();
        file_put_contents($file->path(), 'garbage');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be read as JSON');

        $file->read();
    }

    #[Test]
    public function a_failed_rename_falls_back_to_writing_in_place(): void
    {
        $file = new class($this->dir.'/state.json') extends AtomicJsonFile
        {
            protected function renameFile(string $from, string $to): bool
            {
                return false;
            }
        };

        $file->write(['v' => 1]);
        $file->write(['v' => 2]);

        $this->assertSame(['v' => 2], $file->read(), 'the new state is on disk, not the stale one');
        $this->assertSame([], glob($this->dir.'/*.tmp') ?: []);
    }

    #[Test]
    public function when_the_rename_and_the_fallback_both_fail_it_throws(): void
    {
        // The target path is a directory: nothing can be written there.
        mkdir($this->dir.'/blocked.json');
        $file = new class($this->dir.'/blocked.json') extends AtomicJsonFile
        {
            protected function renameFile(string $from, string $to): bool
            {
                return false;
            }
        };

        try {
            $file->write(['v' => 1]);
            $this->fail('A write that could not be stored must throw, never report success.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rename() and the in-place fallback both failed', $e->getMessage());
        } finally {
            @unlink($this->dir.'/blocked.json.lock');
            @rmdir($this->dir.'/blocked.json');
        }

        $this->assertSame([], glob($this->dir.'/*.tmp') ?: [], 'no temp file is left behind on failure');
    }

    #[Test]
    public function update_gives_the_mutator_the_current_state_and_stores_the_result(): void
    {
        $file = $this->file();

        $first = $file->update(fn (array $d) => ['n' => ($d['n'] ?? 0) + 1]);
        $second = $file->update(fn (array $d) => ['n' => $d['n'] + 1, 'seen' => array_keys($d)]);

        $this->assertSame(['n' => 1], $first);
        $this->assertSame(['n' => 2, 'seen' => ['n']], $second);
        $this->assertSame($second, $file->read());
    }

    #[Test]
    public function concurrent_processes_do_not_lose_each_others_updates(): void
    {
        $path = $this->dir.'/counter.json';
        $script = 'require $argv[1]; $f = new App\\Support\\AtomicJsonFile($argv[2]); for ($i = 0; $i < 25; $i++) { $f->update(fn ($d) => ["n" => ($d["n"] ?? 0) + 1]); }';

        $processes = [];
        for ($i = 0; $i < 6; $i++) {
            $p = new Process([PHP_BINARY, '-r', $script, base_path('vendor/autoload.php'), $path]);
            $p->start();
            $processes[] = $p;
        }

        foreach ($processes as $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
        }

        $this->assertSame(['n' => 150], (new AtomicJsonFile($path))->read(), '6 writers x 25 increments, none lost');
    }

    #[Test]
    public function waiting_for_a_lock_that_is_never_released_times_out_with_a_clear_error(): void
    {
        $file = new AtomicJsonFile($this->dir.'/locked.json', 0.2);

        $holder = fopen($this->dir.'/locked.json.lock', 'cb');
        flock($holder, LOCK_EX);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Timed out waiting for the lock');

            $file->write(['v' => 1]);
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }
    }
}
