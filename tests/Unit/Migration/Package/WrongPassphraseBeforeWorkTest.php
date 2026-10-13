<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

/**
 * A wrong passphrase must be told from a damaged file, and it must cost the reader no more than
 * the header: no table of contents, no entry, no hashing of the file. The package is read through
 * a stream wrapper that counts the bytes the reader actually pulls from the file.
 */
class WrongPassphraseBeforeWorkTest extends TestCase
{
    use BuildsPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();

        if (! in_array('oepcount', stream_get_wrappers(), true)) {
            stream_wrapper_register('oepcount', CountingStreamWrapper::class);
        }
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    #[Test]
    public function a_wrong_passphrase_reads_only_the_start_of_the_file(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('files/big.bin', 'files', random_bytes(3_000_000));
        $writer->seal();

        CountingStreamWrapper::$target = $this->packagePath();
        CountingStreamWrapper::$bytesRead = 0;

        try {
            PackageReader::open('oepcount://package', 'this is not the passphrase', $this->weakKdf());
            $this->fail('opened with a wrong passphrase');
        } catch (WrongPassphraseException) {
            $this->addToAssertionCount(1);
        }

        $this->assertLessThanOrEqual(16384, CountingStreamWrapper::$bytesRead, 'bytes pulled from a 3 MB file before saying "wrong passphrase"');
        $this->assertGreaterThan(0, CountingStreamWrapper::$bytesRead, 'the counter really counts');
    }

    #[Test]
    public function the_right_passphrase_does_read_the_rest(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('files/big.bin', 'files', random_bytes(300_000));
        $writer->seal();

        CountingStreamWrapper::$target = $this->packagePath();
        CountingStreamWrapper::$bytesRead = 0;

        $reader = PackageReader::open('oepcount://package', $this->passphrase, $this->weakKdf());
        $reader->verifyAll();

        $this->assertGreaterThan(300_000, CountingStreamWrapper::$bytesRead);
    }
}

/** Delivers a fixed file through a stream wrapper and counts what was read. */
final class CountingStreamWrapper
{
    public static int $bytesRead = 0;

    public static string $target = '';

    /** @var resource|null */
    public $context;

    /** @var resource|false */
    private $handle = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->handle = fopen(self::$target, 'rb');

        return $this->handle !== false;
    }

    public function stream_read(int $count): string|false
    {
        $data = fread($this->handle, $count);
        self::$bytesRead += $data === false ? 0 : strlen($data);

        return $data;
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    /** @return array<int|string, int>|false */
    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    public function stream_seek(int $offset, int $whence): bool
    {
        return fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_tell(): int|false
    {
        return ftell($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    /** @return array<int|string, int>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        return @stat(self::$target);
    }
}
