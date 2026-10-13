<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\TextModeTransferException;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * An FTP client in ASCII mode (or a copy that converts line endings) rewrites CR LF bytes and
 * ruins a binary file silently. The 4-byte guard right after the signature exists so that
 * damage is recognised as exactly that, with the instruction to upload again in binary mode,
 * rather than as a wrong passphrase or a corrupt file.
 */
class TextModeTransferTest extends TestCase
{
    private function header(): string
    {
        $kdf = new Kdf(['passesMin' => 1, 'passesMax' => 12, 'memoryKibMin' => 64, 'memoryKibMax' => 1 << 20, 'pbkdf2Min' => 1000, 'pbkdf2Max' => 10_000_000]);
        [$sealed] = Header::create(PackageFormat::KDF_PBKDF2_SHA512, 2000, 0)->seal('MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA', $kdf);

        return $sealed->toBytes();
    }

    #[Test]
    public function crlf_converted_to_lf_is_recognised(): void
    {
        $this->expectException(TextModeTransferException::class);
        $this->expectExceptionMessage('BINARY');

        Header::parse(str_replace("\r\n", "\n", $this->header()));
    }

    #[Test]
    public function lf_converted_to_crlf_is_recognised(): void
    {
        $this->expectException(TextModeTransferException::class);

        Header::parse(str_replace("\n", "\r\n", $this->header()));
    }

    #[Test]
    public function a_lone_cr_stripped_is_recognised(): void
    {
        $this->expectException(TextModeTransferException::class);

        Header::parse(str_replace("\r", '', $this->header()));
    }

    #[Test]
    public function an_untouched_header_is_not_mistaken_for_text_damage(): void
    {
        $this->assertSame(PackageFormat::KDF_PBKDF2_SHA512, Header::parse($this->header(), new Kdf(['passesMin' => 1, 'passesMax' => 12, 'memoryKibMin' => 64, 'memoryKibMax' => 1 << 20, 'pbkdf2Min' => 1000, 'pbkdf2Max' => 10_000_000]))->kdfId);
    }

    #[Test]
    public function the_error_maps_to_the_binary_mode_instruction(): void
    {
        $migration = (new TextModeTransferException)->toMigrationException();

        $this->assertSame('package_text_mode', $migration->errorCode->value);
    }
}
