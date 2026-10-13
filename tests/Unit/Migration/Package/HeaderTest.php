<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\BackupPartNotPackageException;
use App\Services\Migration\Package\Exceptions\NotAPackageException;
use App\Services\Migration\Package\Exceptions\PackageException;
use App\Services\Migration\Package\Exceptions\TruncatedException;
use App\Services\Migration\Package\Exceptions\UnsupportedFormatException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HeaderTest extends TestCase
{
    private const PASSPHRASE = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    /** Real algorithms, cheap settings: the bounds are the only thing relaxed, and only here. */
    private function weakKdf(): Kdf
    {
        return new Kdf([
            'passesMin' => 1, 'passesMax' => 12, 'memoryKibMin' => 64, 'memoryKibMax' => 1024 * 1024,
            'pbkdf2Min' => 1000, 'pbkdf2Max' => 10_000_000,
        ]);
    }

    /** @return array{0: string, 1: Header} */
    private function sealed(int $kdfId = PackageFormat::KDF_PBKDF2_SHA512): array
    {
        $header = Header::create($kdfId, $kdfId === PackageFormat::KDF_ARGON2ID ? 1 : 2000, $kdfId === PackageFormat::KDF_ARGON2ID ? 64 : 0, str_repeat('S', 16), str_repeat('P', 16));
        [$sealed] = $header->seal(self::PASSPHRASE, $this->weakKdf());

        return [$sealed->toBytes(), $sealed];
    }

    #[Test]
    public function the_header_is_128_bytes_with_every_field_at_its_documented_offset(): void
    {
        [$bytes, $header] = $this->sealed();

        $this->assertSame(128, strlen($bytes));
        $this->assertSame('OEPARTS1', substr($bytes, 0, 8));
        $this->assertSame("\x0D\x0A\x1A\x0A", substr($bytes, 8, 4));
        $this->assertSame([1, 0], array_values(unpack('n2', substr($bytes, 12, 4))));
        $this->assertSame(128, unpack('n', substr($bytes, 16, 2))[1]);
        $this->assertSame(0, unpack('N', substr($bytes, 20, 4))[1], 'no critical flags in v1');
        $this->assertSame(1, ord($bytes[28]), 'AES-256-GCM');
        $this->assertSame(PackageFormat::KDF_PBKDF2_SHA512, ord($bytes[29]));
        $this->assertSame(20, ord($bytes[30]), '1 MiB blocks');
        $this->assertSame(2000, unpack('N', substr($bytes, 32, 4))[1]);
        $this->assertSame(str_repeat('S', 16), substr($bytes, 40, 16));
        $this->assertSame(str_repeat('P', 16), substr($bytes, 56, 16));
        $this->assertSame(str_repeat("\0", 24), substr($bytes, 72, 24), 'reserved bytes are zero');
        $this->assertSame($header->keycheck, substr($bytes, 96, 32));
        $this->assertSame(1 << 20, $header->blockSize());
    }

    #[Test]
    public function the_right_passphrase_opens_it_and_a_wrong_one_does_not(): void
    {
        [$bytes] = $this->sealed();

        $opened = Header::open($bytes, self::PASSPHRASE, $this->weakKdf());
        $this->assertSame(str_repeat('P', 16), $opened->header->packageId);
        $this->assertSame(32, strlen($opened->keys->data()));

        $this->expectException(WrongPassphraseException::class);
        Header::open($bytes, self::PASSPHRASE.'x', $this->weakKdf());
    }

    #[Test]
    public function it_works_the_same_with_argon2id(): void
    {
        if (! Kdf::sodiumAvailable()) {
            $this->markTestSkipped('ext-sodium is not available.');
        }

        [$bytes] = $this->sealed(PackageFormat::KDF_ARGON2ID);

        $this->assertSame(PackageFormat::KDF_ARGON2ID, Header::open($bytes, self::PASSPHRASE, $this->weakKdf())->header->kdfId);
        $this->expectException(WrongPassphraseException::class);
        Header::open($bytes, 'not the passphrase', $this->weakKdf());
    }

    #[Test]
    public function flipping_any_single_header_byte_never_opens_the_file(): void
    {
        [$bytes] = $this->sealed();

        for ($i = 0; $i < 128; $i++) {
            $tampered = $bytes;
            $tampered[$i] = $tampered[$i] ^ "\x01";

            try {
                Header::open($tampered, self::PASSPHRASE, $this->weakKdf());
                $this->fail("Flipping header byte {$i} must not give an openable header.");
            } catch (PackageException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function a_flip_in_the_salt_the_package_id_the_reserved_bytes_or_the_keycheck_is_a_failed_keycheck(): void
    {
        [$bytes] = $this->sealed();

        // These fields are accepted by the structural checks, so only the key-check can catch them.
        foreach ([...range(40, 127)] as $i) {
            $tampered = $bytes;
            $tampered[$i] = $tampered[$i] ^ "\x80";

            $this->expectedWrongPassphrase($tampered, $i);
        }
    }

    private function expectedWrongPassphrase(string $tampered, int $byte): void
    {
        try {
            Header::open($tampered, self::PASSPHRASE, $this->weakKdf());
            $this->fail("Byte {$byte}: a tampered header opened.");
        } catch (WrongPassphraseException) {
            $this->addToAssertionCount(1);
        }
    }

    #[Test]
    public function altering_the_kdf_settings_to_weaken_them_cannot_be_hidden(): void
    {
        [$bytes] = $this->sealed();

        // An attacker who rewrites the iteration count (downgrade) breaks the key-check: the subkeys depend on every header byte.
        $weakened = substr($bytes, 0, 32).pack('N', 1000).substr($bytes, 36);

        $this->expectException(WrongPassphraseException::class);
        Header::open($weakened, self::PASSPHRASE, $this->weakKdf());
    }

    #[Test]
    public function a_file_that_is_not_an_oeparts_package_is_named_as_such(): void
    {
        $this->expectException(NotAPackageException::class);
        Header::parse('PK'.str_repeat("\0", 200));
    }

    #[Test]
    public function an_empty_or_tiny_input_is_not_a_package(): void
    {
        $this->expectException(NotAPackageException::class);
        Header::parse('OEP');
    }

    #[Test]
    public function the_old_backup_part_format_is_recognised_and_rejected_with_its_own_error(): void
    {
        $this->expectException(BackupPartNotPackageException::class);
        Header::parse("OEENC1\x01".str_repeat("\0", 200));
    }

    #[Test]
    public function a_header_cut_short_is_incomplete_not_wrong(): void
    {
        [$bytes] = $this->sealed();

        $this->expectException(TruncatedException::class);
        Header::parse(substr($bytes, 0, 100));
    }

    #[Test]
    public function an_unknown_major_version_is_refused_but_a_newer_minor_is_read(): void
    {
        [$bytes] = $this->sealed();

        $major2 = substr($bytes, 0, 12).pack('nn', 2, 0).substr($bytes, 16);
        try {
            Header::parse($major2, $this->weakKdf());
            $this->fail('A newer major version must be refused.');
        } catch (UnsupportedFormatException $e) {
            $this->assertStringContainsString('2.0', $e->getMessage());
        }

        $minor5 = substr($bytes, 0, 12).pack('nn', 1, 5).substr($bytes, 16);
        $this->assertSame(5, Header::parse($minor5, $this->weakKdf())->formatMinor, 'a newer minor is accepted when every critical flag is known');
    }

    #[Test]
    public function a_critical_flag_this_reader_does_not_know_is_refused(): void
    {
        [$bytes] = $this->sealed();
        $flagged = substr($bytes, 0, 20).pack('N', 1).substr($bytes, 24);

        $this->expectException(UnsupportedFormatException::class);
        Header::parse($flagged, $this->weakKdf());
    }

    #[Test]
    public function an_unencrypted_package_cannot_be_expressed(): void
    {
        [$bytes] = $this->sealed();

        foreach ([0, 2, 255] as $cipher) {
            $changed = substr($bytes, 0, 28).chr($cipher).substr($bytes, 29);

            try {
                Header::parse($changed, $this->weakKdf());
                $this->fail("cipher id {$cipher} must not be accepted");
            } catch (UnsupportedFormatException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function block_sizes_outside_the_supported_range_are_refused(): void
    {
        [$bytes] = $this->sealed();

        foreach ([0, 15, 23, 40] as $log2) {
            try {
                Header::parse(substr($bytes, 0, 30).chr($log2).substr($bytes, 31), $this->weakKdf());
                $this->fail("block_log2 {$log2} must be refused");
            } catch (UnsupportedFormatException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function an_unsealed_header_cannot_be_written(): void
    {
        $this->expectException(\LogicException::class);

        Header::create(PackageFormat::KDF_PBKDF2_SHA512, 2000, 0)->toBytes();
    }

    #[Test]
    public function two_headers_that_differ_in_one_byte_derive_completely_different_keys(): void
    {
        $kdf = $this->weakKdf();
        $a = Header::create(PackageFormat::KDF_PBKDF2_SHA512, 2000, 0, str_repeat('S', 16), str_repeat('A', 16));
        $b = Header::create(PackageFormat::KDF_PBKDF2_SHA512, 2000, 0, str_repeat('S', 16), str_repeat('B', 16));

        [, $keysA] = $a->seal(self::PASSPHRASE, $kdf);
        [, $keysB] = $b->seal(self::PASSPHRASE, $kdf);

        foreach (['check', 'data', 'secrets', 'fingerprint'] as $name) {
            $this->assertNotSame($keysA->{$name}(), $keysB->{$name}(), "{$name} must depend on the whole header");
        }
        $this->assertNotSame($keysA->data(), $keysA->secrets(), 'the secrets key is separate from the data key');
    }
}
