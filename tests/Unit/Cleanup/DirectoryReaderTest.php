<?php

namespace Tests\Unit\Cleanup;

use App\Services\Cleanup\DirectoryReader;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Plain readdir()-based listing. The engine must never use SPL's directory iterators: on a Docker Desktop bind mount they
 * silently dropped the first 1 021 entries of a 13 000-file folder (found in the real-filesystem rehearsal).
 */
class DirectoryReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-dirreader-'.getmypid();
        $this->rrmdir($this->dir);
        mkdir($this->dir.'/sub/deeper', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->dir);
        parent::tearDown();
    }

    private function rrmdir(string $path): void
    {
        if (is_link($path) || (file_exists($path) && ! is_dir($path))) {
            @unlink($path);

            return;
        }
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $e) {
                if ($e !== '.' && $e !== '..') {
                    $this->rrmdir($path.DIRECTORY_SEPARATOR.$e);
                }
            }
            @rmdir($path);
        }
    }

    #[Test]
    public function it_lists_every_direct_child_without_dots_even_in_a_large_folder(): void
    {
        for ($i = 0; $i < 2500; $i++) { // well past the 1 021 entries that went missing on the bad filesystem
            touch($this->dir.'/f'.$i);
        }

        $names = iterator_to_array(DirectoryReader::names($this->dir), false);

        $this->assertCount(2500 + 1, $names, '2500 files + the "sub" folder');
        $this->assertCount(2501, array_unique($names));
        $this->assertNotContains('.', $names);
        $this->assertNotContains('..', $names);
    }

    #[Test]
    public function leaves_returns_files_at_every_depth_and_never_enters_a_linked_folder(): void
    {
        touch($this->dir.'/top.txt');
        touch($this->dir.'/sub/mid.txt');
        touch($this->dir.'/sub/deeper/deep.txt');
        $outside = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-dirreader-outside-'.getmypid();
        @mkdir($outside);
        touch($outside.'/not-ours.txt');
        $linked = @symlink($outside, $this->dir.'/linked-dir');

        $paths = array_map(fn ($e) => str_replace(DIRECTORY_SEPARATOR, '/', substr($e->getPathname(), strlen($this->dir) + 1)), iterator_to_array(DirectoryReader::leaves($this->dir), false));
        sort($paths);

        $expected = ['sub/deeper/deep.txt', 'sub/mid.txt', 'top.txt'];
        if ($linked) {
            $expected[] = 'linked-dir'; // the link itself is an entry, its contents are not
            sort($expected);
        }
        $this->assertSame($expected, $paths);

        @unlink($this->dir.'/linked-dir');
        @unlink($outside.'/not-ours.txt');
        @rmdir($outside);
    }

    #[Test]
    public function a_missing_folder_yields_nothing_instead_of_failing(): void
    {
        $this->assertSame([], iterator_to_array(DirectoryReader::names($this->dir.'/nope'), false));
        $this->assertSame([], iterator_to_array(DirectoryReader::leaves($this->dir.'/nope'), false));
    }
}
