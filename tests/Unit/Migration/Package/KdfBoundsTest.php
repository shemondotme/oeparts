<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\InsufficientResourcesException;
use App\Services\Migration\Package\Exceptions\KdfParamsRejectedException;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The key-derivation settings come from the file, so a hostile file can ask for 100 GB of memory
 * or ten billion iterations and take the server down just by being opened. The bounds are checked
 * before any derivation runs.
 */
class KdfBoundsTest extends TestCase
{
    /** @return array<string, array{0: int, 1: int, 2: int}> */
    public static function rejected(): array
    {
        $kib = 1024;

        return [
            'argon2 passes too low' => [PackageFormat::KDF_ARGON2ID, 1, 64 * $kib],
            'argon2 passes zero' => [PackageFormat::KDF_ARGON2ID, 0, 64 * $kib],
            'argon2 passes too high' => [PackageFormat::KDF_ARGON2ID, 13, 64 * $kib],
            'argon2 memory too low' => [PackageFormat::KDF_ARGON2ID, 3, 1024],
            'argon2 memory at 100 GiB' => [PackageFormat::KDF_ARGON2ID, 3, 100 * 1024 * $kib],
            'argon2 memory just above the maximum' => [PackageFormat::KDF_ARGON2ID, 3, 1024 * $kib + 1],
            'pbkdf2 too few iterations' => [PackageFormat::KDF_PBKDF2_SHA512, 1000, 0],
            'pbkdf2 just below the minimum' => [PackageFormat::KDF_PBKDF2_SHA512, 299_999, 0],
            'pbkdf2 ten billion iterations' => [PackageFormat::KDF_PBKDF2_SHA512, 4_000_000_000, 0],
            'pbkdf2 just above the maximum' => [PackageFormat::KDF_PBKDF2_SHA512, 10_000_001, 0],
            'unknown kdf id' => [9, 3, 65536],
            'no kdf at all' => [0, 3, 65536],
        ];
    }

    #[Test]
    #[DataProvider('rejected')]
    public function parameters_outside_the_bounds_are_refused(int $kdfId, int $p1, int $p2): void
    {
        $this->expectException(KdfParamsRejectedException::class);

        (new Kdf)->assertAcceptable($kdfId, $p1, $p2);
    }

    #[Test]
    #[DataProvider('rejected')]
    public function derive_refuses_them_before_doing_any_work(int $kdfId, int $p1, int $p2): void
    {
        // If derivation ran with 100 GiB or 4 billion iterations this test would never finish.
        $this->expectException(KdfParamsRejectedException::class);

        (new Kdf)->derive('x', $kdfId, str_repeat("\0", 16), $p1, $p2);
    }

    #[Test]
    public function a_header_with_hostile_parameters_is_refused_by_parse_alone(): void
    {
        $hostile = Header::create(PackageFormat::KDF_ARGON2ID, 3, 100 * 1024 * 1024)->authenticatedBytes().str_repeat("\0", 32);

        $this->expectException(KdfParamsRejectedException::class);

        Header::parse($hostile);
    }

    #[Test]
    public function the_accepted_range_includes_the_documented_defaults_and_edges(): void
    {
        $kdf = new Kdf;

        $kdf->assertAcceptable(PackageFormat::KDF_ARGON2ID, 2, 32 * 1024);
        $kdf->assertAcceptable(PackageFormat::KDF_ARGON2ID, 12, 1024 * 1024);
        $kdf->assertAcceptable(PackageFormat::KDF_PBKDF2_SHA512, 300_000, 0);
        $kdf->assertAcceptable(PackageFormat::KDF_PBKDF2_SHA512, 10_000_000, 0);

        $this->addToAssertionCount(4);
    }

    #[Test]
    public function the_salt_must_be_exactly_sixteen_bytes(): void
    {
        $this->expectException(KdfParamsRejectedException::class);

        (new Kdf)->derive('x', PackageFormat::KDF_PBKDF2_SHA512, 'short', 600_000, 0);
    }

    #[Test]
    public function argon2_that_does_not_fit_the_memory_limit_is_refused_with_how_much_is_needed(): void
    {
        if (! Kdf::sodiumAvailable()) {
            $this->markTestSkipped('ext-sodium is not available.');
        }

        // A separate PHP with a 40 MB limit: 64 MiB of Argon2 plus the headroom cannot fit.
        $autoload = dirname(__DIR__, 4).'/vendor/autoload.php';
        $script = '
            require $argv[1];
            try {
                (new App\Services\Migration\Package\Kdf)->derive("x", 1, str_repeat("s", 16), 3, 64 * 1024);
                echo "DERIVED";
            } catch (App\Services\Migration\Package\Exceptions\InsufficientResourcesException $e) {
                echo "REFUSED:".$e->params()["mb"];
            }';

        $process = new Process([PHP_BINARY, '-d', 'memory_limit=40M', '-r', $script, $autoload]);
        $process->run();

        $this->assertSame('REFUSED:96', trim($process->getOutput()), $process->getErrorOutput());
        $this->assertTrue(class_exists(InsufficientResourcesException::class));
    }
}
