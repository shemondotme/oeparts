<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\LimitExceededException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\PackageLimits;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;
use Tests\Unit\Migration\Package\Concerns\ForgesPackages;

/**
 * A package is untrusted input, and the property that matters is absolute: NO single changed byte
 * and NO shortened or extended file may be accepted, and none may crash the reader with anything
 * but a PackageException the caller can turn into a message. The matrix below flips every byte
 * of a real package (header, entry headers, frame headers, ciphertext, table of contents, trailer),
 * cuts it at every length, and rearranges its entries.
 */
class PackageTamperTest extends TestCase
{
    use BuildsPackages;
    use ForgesPackages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();

        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', str_repeat('A', 200));
        $writer->addEntryFromString('report/b.json', 'report', str_repeat('B', 200));
        $gz = gzencode(str_repeat('row;', 300)).gzencode(str_repeat('end;', 100));
        $writer->addEntryFromString('db/x.sql', 'db', $gz, ['codec' => 'gzip', 'raw_len' => 1600]);
        $writer->addSecrets("{\"key\":\"k\",\"value\":\"v\"}\n");
        $writer->seal();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    /** Opens and fully verifies; returns normally only if the file was accepted. */
    private function accept(string $path): void
    {
        $reader = PackageReader::open($path, $this->passphrase, $this->weakKdf());
        $reader->verifyAll();
        $reader->close();
    }

    private function assertRejected(string $path, string $what): void
    {
        try {
            $this->accept($path);
        } catch (PackageException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("{$what} was accepted");
    }

    #[Test]
    public function the_untouched_package_is_accepted(): void
    {
        $this->accept($this->packagePath());
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function changing_any_single_byte_anywhere_is_caught(): void
    {
        $original = (string) file_get_contents($this->packagePath());
        $size = strlen($original);
        $tampered = $this->packagePath('tampered.oeparts');

        for ($i = 0; $i < $size; $i++) {
            $bytes = $original;
            $bytes[$i] = chr(ord($bytes[$i]) ^ 0x01);
            file_put_contents($tampered, $bytes);

            $this->assertRejected($tampered, "a flipped bit at byte {$i} of {$size}");
        }
    }

    #[Test]
    public function changing_any_byte_with_the_trailer_hash_repaired_is_still_caught_by_authentication(): void
    {
        $original = (string) file_get_contents($this->packagePath());
        $tampered = $this->packagePath('tampered.oeparts');

        // Everything between the header and the trailer: the entries and the table of contents.
        for ($i = 128; $i < strlen($original) - PackageLimits::TRAILER_LEN; $i++) {
            $bytes = $original;
            $bytes[$i] = chr(ord($bytes[$i]) ^ 0x80);
            file_put_contents($tampered, $bytes);
            $this->refreshTrailerHash($tampered);

            $this->assertRejected($tampered, "a changed byte {$i} with a repaired file hash");
        }
    }

    #[Test]
    public function a_file_cut_at_any_length_is_caught(): void
    {
        $original = (string) file_get_contents($this->packagePath());
        $cut = $this->packagePath('cut.oeparts');

        for ($length = 0; $length < strlen($original); $length++) {
            file_put_contents($cut, substr($original, 0, $length));

            $this->assertRejected($cut, "a file cut at {$length} bytes");
        }
    }

    #[Test]
    public function bytes_added_at_either_end_are_caught(): void
    {
        $original = (string) file_get_contents($this->packagePath());
        $changed = $this->packagePath('changed.oeparts');

        foreach (['after' => $original."\0", 'garbage after' => $original.random_bytes(500), 'before' => "\0".$original, 'a second copy' => $original.$original] as $what => $bytes) {
            file_put_contents($changed, $bytes);

            $this->assertRejected($changed, "bytes {$what}");
        }
    }

    #[Test]
    public function two_equally_sized_entries_swapped_in_place_are_caught(): void
    {
        $walk = $this->walk($this->packagePath());
        $a = $walk['entries'][1];
        $b = $walk['entries'][2];
        $this->assertSame($a['enc_len'], $b['enc_len'], 'the premise: same size');

        $bytes = (string) file_get_contents($this->packagePath());
        $swapped = substr($bytes, 0, $a['offset'])
            .substr($bytes, $b['offset'], $b['enc_len'])
            .substr($bytes, $a['offset'], $a['enc_len'])
            .substr($bytes, $b['offset'] + $b['enc_len']);

        $path = $this->packagePath('swapped.oeparts');
        file_put_contents($path, $swapped);
        $this->refreshTrailerHash($path);

        $this->assertRejected($path, 'entries swapped in place');
    }

    #[Test]
    public function an_entry_replaced_by_a_copy_of_another_is_caught(): void
    {
        $walk = $this->walk($this->packagePath());
        $a = $walk['entries'][1];
        $b = $walk['entries'][2];

        $bytes = (string) file_get_contents($this->packagePath());
        $path = $this->packagePath('copied.oeparts');
        file_put_contents($path, substr($bytes, 0, $b['offset']).substr($bytes, $a['offset'], $a['enc_len']).substr($bytes, $b['offset'] + $b['enc_len']));
        $this->refreshTrailerHash($path);

        $this->assertRejected($path, 'an entry duplicated over another');
    }

    #[Test]
    public function the_same_package_with_a_reordered_table_of_contents_is_caught(): void
    {
        $path = $this->copyPackage($this->packagePath(), 'reordered.oeparts');
        $this->forgeToc($path, function (array $toc): array {
            $toc['entries'] = array_reverse($toc['entries']);

            return $toc;
        });

        $this->assertRejected($path, 'a reordered table of contents');
    }

    #[Test]
    public function a_bit_flipped_in_a_ciphertext_is_reported_as_corruption_naming_the_part(): void
    {
        $walk = $this->walk($this->packagePath());
        $entry = $walk['entries'][1];
        $bytes = (string) file_get_contents($this->packagePath());
        $at = $entry['offset'] + 24 + 32 + 5;
        $bytes[$at] = chr(ord($bytes[$at]) ^ 0x04);

        $path = $this->packagePath('flipped.oeparts');
        file_put_contents($path, $bytes);
        $this->refreshTrailerHash($path);

        try {
            $this->accept($path);
            $this->fail('accepted');
        } catch (CorruptException $e) {
            $this->assertSame('entry 1, frame 0', $e->params()['part']);
        }
    }

    #[Test]
    public function an_entry_announcing_a_smaller_size_than_it_unpacks_to_is_stopped(): void
    {
        $writer = $this->newWriter('bomb.oeparts');
        $writer->addEntryFromString('db/bomb.sql', 'db', gzencode(str_repeat("\0", 5_000_000), 9), ['codec' => 'gzip', 'raw_len' => 1000]);
        $writer->seal();

        $reader = PackageReader::open($this->packagePath('bomb.oeparts'), $this->passphrase, $this->weakKdf());

        $produced = 0;
        try {
            $reader->readEntry('db/bomb.sql', function (string $part) use (&$produced): void {
                $produced += strlen($part);
            });
            $this->fail('the bomb was inflated');
        } catch (LimitExceededException) {
            $this->assertLessThan(8 * 1024 * 1024, $produced);
        }

        $this->expectException(LimitExceededException::class);
        $reader->verifyAll();
    }

    #[Test]
    public function an_entry_announcing_a_larger_size_than_it_unpacks_to_is_corruption(): void
    {
        $writer = $this->newWriter('short.oeparts');
        $writer->addEntryFromString('db/short.sql', 'db', gzencode('only a little'), ['codec' => 'gzip', 'raw_len' => 99999]);
        $writer->seal();

        $reader = PackageReader::open($this->packagePath('short.oeparts'), $this->passphrase, $this->weakKdf());

        $this->expectException(CorruptException::class);
        $reader->verifyAll();
    }
}
