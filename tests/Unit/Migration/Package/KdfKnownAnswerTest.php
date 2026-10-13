<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Known-answer tests: the expected values below were produced by INDEPENDENT implementations
 * (Python argon2-cffi, hashlib.pbkdf2_hmac and cryptography's HKDF; the HKDF cases are the
 * published RFC 5869 vectors), never by this code. If PHP, libsodium or OpenSSL ever disagree
 * with them, a package written on one machine could not be opened on another.
 */
class KdfKnownAnswerTest extends TestCase
{
    private const PASSPHRASE = 'correct horse battery staple';

    private function salt(): string
    {
        return implode('', array_map('chr', range(0, 15)));
    }

    #[Test]
    public function argon2id_matches_an_independent_implementation(): void
    {
        if (! Kdf::sodiumAvailable()) {
            $this->markTestSkipped('ext-sodium is not available, so Argon2id cannot be checked on this PHP build (PBKDF2 and HKDF still are).');
        }

        // argon2-cffi: Argon2id v1.3, t=3, m=64 MiB, p=1, 32-byte tag.
        $key = (new Kdf)->derive(self::PASSPHRASE, PackageFormat::KDF_ARGON2ID, $this->salt(), 3, 64 * 1024);

        $this->assertSame('0d1a3c6523c8f06e4e0af9c515aa5b5448cfebd6838f2d52c3d8b6ef8ddc3c2e', bin2hex($key));
    }

    #[Test]
    public function pbkdf2_sha512_matches_an_independent_implementation(): void
    {
        // Python hashlib.pbkdf2_hmac('sha512', ..., 600000, 32).
        $key = (new Kdf)->derive(self::PASSPHRASE, PackageFormat::KDF_PBKDF2_SHA512, $this->salt(), 600_000, 0);

        $this->assertSame('1cf30a518878f44aecb75c0e0d0d69a02ac5f9181a53b5292092e10a3c0cbb41', bin2hex($key));
        $this->assertSame(32, strlen($key), 'a raw 32-byte key (hash_pbkdf2 counts hex characters unless raw output is requested)');
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: int, 4: string}> */
    public static function rfc5869(): array
    {
        return [
            'test case 1' => [
                str_repeat("\x0b", 22),
                implode('', array_map('chr', range(0x00, 0x0C))),
                implode('', array_map('chr', range(0xF0, 0xF9))),
                42,
                '3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865',
            ],
            'test case 2 (long inputs)' => [
                implode('', array_map('chr', range(0x00, 0x4F))),
                implode('', array_map('chr', range(0x60, 0xAF))),
                implode('', array_map('chr', range(0xB0, 0xFF))),
                82,
                'b11e398dc80327a1c8e7f78c596a49344f012eda2d4efad8a050cc4c19afa97c59045a99cac7827271cb41c65e590e09da3275600c2f09b8367793a9aca3db71cc30c58179ec3e87c14c01d5c1f3434f1d87',
            ],
            'test case 3 (empty salt and info)' => [
                str_repeat("\x0b", 22),
                '',
                '',
                42,
                '8da4e775a563c18f715f802a063c5a31b8a11f5c5ee1879ec3454e5f3c738d2d9d201395faa4b61a96c8',
            ],
        ];
    }

    #[Test]
    #[DataProvider('rfc5869')]
    public function hkdf_sha256_reproduces_the_published_rfc_5869_vectors(string $ikm, string $salt, string $info, int $length, string $expectedHex): void
    {
        $this->assertSame($expectedHex, bin2hex(hash_hkdf('sha256', $ikm, $length, $info, $salt)));
    }

    #[Test]
    public function the_pbkdf2_iteration_count_for_a_new_package_respects_the_floor_and_the_ceiling(): void
    {
        $iterations = Kdf::calibratePbkdf2Iterations(0.05);

        $this->assertGreaterThanOrEqual(Kdf::PBKDF2_FLOOR, $iterations, 'never below one million, however fast or slow the host');
        $this->assertLessThanOrEqual(Kdf::PBKDF2_MAX, $iterations);
        $this->assertSame(Kdf::PBKDF2_FLOOR, Kdf::calibratePbkdf2Iterations(0.0), 'a zero target still gives the floor');
    }

    #[Test]
    public function the_defaults_for_a_new_package_are_inside_the_bounds_a_reader_accepts(): void
    {
        $kdf = new Kdf;

        foreach ([PackageFormat::KDF_ARGON2ID, PackageFormat::KDF_PBKDF2_SHA512] as $id) {
            $params = $kdf->defaultParams($id);
            $kdf->assertAcceptable($id, $params['p1'], $params['p2']);
        }

        $this->assertSame(['p1' => 3, 'p2' => 65536], $kdf->defaultParams(PackageFormat::KDF_ARGON2ID));
    }
}
