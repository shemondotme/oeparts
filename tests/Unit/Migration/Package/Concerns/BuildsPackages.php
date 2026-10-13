<?php

namespace Tests\Unit\Migration\Package\Concerns;

use App\Services\Migration\Package\FrameCodec;
use App\Services\Migration\Package\Header;
use App\Services\Migration\Package\Kdf;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageLimits;
use App\Services\Migration\Package\PackageWriter;
use App\Services\Migration\Package\PassphraseInput;

/**
 * Helpers for tests that build real packages with the real algorithms and cheap key-derivation
 * settings, and a layout checker that parses the file BY HAND (no PackageReader), so a writer
 * bug cannot hide behind a reader that shares it.
 */
trait BuildsPackages
{
    protected string $passphrase = 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA';

    protected string $workDir = '';

    protected function makeWorkDir(): void
    {
        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-pkg-'.getmypid().'-'.uniqid();
        mkdir($this->workDir, 0775, true);
    }

    protected function removeWorkDir(): void
    {
        $this->rrmdir($this->workDir);
    }

    private function rrmdir(string $dir): void
    {
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    protected function weakKdf(): Kdf
    {
        return new Kdf([
            'passesMin' => 1, 'passesMax' => 12, 'memoryKibMin' => 64, 'memoryKibMax' => 1 << 20,
            'pbkdf2Min' => 1000, 'pbkdf2Max' => 10_000_000,
        ]);
    }

    protected function packagePath(string $name = 'site.oeparts'): string
    {
        return $this->workDir.DIRECTORY_SEPARATOR.$name;
    }

    /** @param  array<string, mixed>  $meta */
    protected function newWriter(string $name = 'site.oeparts', array $meta = ['source' => ['domain' => 'old.example']]): PackageWriter
    {
        return PackageWriter::create(
            $this->packagePath($name), $this->passphrase, $meta, $this->weakKdf(),
            kdfId: PackageFormat::KDF_PBKDF2_SHA512, kdfParams: ['p1' => 2000, 'p2' => 0],
            enforcePassphraseRule: true
        );
    }

    protected function resumeWriter(string $name = 'site.oeparts'): PackageWriter
    {
        return PackageWriter::resume($this->packagePath($name), $this->passphrase, $this->weakKdf());
    }

    /**
     * Walk a finished package by hand. Asserts the no-slack layout and returns what it found.
     *
     * @return array{header: Header, codec: FrameCodec, entries: array<int, array<string, mixed>>, toc: array<string, mixed>, tocEnd: int, size: int, fileSha256: string}
     */
    protected function walk(string $path): array
    {
        $bytes = (string) file_get_contents($path);
        $size = strlen($bytes);

        $opened = Header::open(substr($bytes, 0, PackageFormat::HEADER_LEN), $this->passphrase, $this->weakKdf());
        $codec = new FrameCodec($opened->keys->data(), $opened->keys->secrets(), $opened->header->packageId, $opened->header->blockLog2);

        $trailer = substr($bytes, -PackageLimits::TRAILER_LEN);
        $this->assertSame(PackageLimits::TRAILER_MAGIC, substr($trailer, 0, 8), 'trailer magic');
        $this->assertSame(PackageFormat::MAGIC, substr($trailer, 56, 8), 'trailer end magic');
        $tocOffset = unpack('J', substr($trailer, 8, 8))[1];
        $tocEncLen = unpack('J', substr($trailer, 16, 8))[1];
        $this->assertSame($size, $tocOffset + $tocEncLen + PackageLimits::TRAILER_LEN, 'the file ends right after the trailer: no slack');

        $offset = PackageFormat::HEADER_LEN;
        $entries = [];
        $guard = 0;

        while (true) {
            $this->assertLessThan(10000, ++$guard);
            $head = FrameCodec::parseEntryHeader(substr($bytes, $offset, FrameCodec::ENTRY_HEADER_LEN));
            $encLen = $codec->encryptedLengthFor($head['plainLen']);
            $this->assertSame($codec->frameCountFor($head['plainLen']), $head['frameCount'], 'frame count follows from the length');

            $plain = '';
            $cursor = $offset + FrameCodec::ENTRY_HEADER_LEN;
            for ($k = 0; $k < $head['frameCount']; $k++) {
                $len = $codec->plainLengthOfFrame($k, $head['frameCount'], $head['plainLen']);
                $frame = substr($bytes, $cursor, FrameCodec::FRAME_HEADER_LEN + $len);
                $this->assertSame($offset + FrameCodec::ENTRY_HEADER_LEN + $codec->frameOffset($k), $cursor, 'constant stride');
                $plain .= $codec->decryptFrame($frame, $head['entryId'], $head['keyClass'], $k, $head['frameCount'], $head['plainLen']);
                $cursor += FrameCodec::FRAME_HEADER_LEN + $len;
            }
            $this->assertSame($offset + $encLen, $cursor, 'entry length');

            $entries[$head['entryId']] = ['offset' => $offset, 'enc_len' => $encLen, 'head' => $head, 'plain' => $plain, 'enc_sha256' => hash('sha256', substr($bytes, $offset, $encLen))];
            $offset += $encLen;

            if ($head['entryId'] === PackageLimits::ENTRY_ID_TOC) {
                break;
            }
        }

        $this->assertSame($tocOffset, $entries[PackageLimits::ENTRY_ID_TOC]['offset']);
        $this->assertSame($tocEncLen, $entries[PackageLimits::ENTRY_ID_TOC]['enc_len']);
        $this->assertSame($offset, $size - PackageLimits::TRAILER_LEN, 'the trailer starts exactly where the table of contents ends');

        $fileSha256 = bin2hex(substr($trailer, 24, 32));
        $this->assertSame(hash('sha256', substr($bytes, 0, $offset)), $fileSha256, 'the trailer carries the SHA-256 of every byte before it');

        return [
            'header' => $opened->header,
            'codec' => $codec,
            'entries' => $entries,
            'toc' => json_decode($entries[PackageLimits::ENTRY_ID_TOC]['plain'], true),
            'tocEnd' => $offset,
            'size' => $size,
            'fileSha256' => $fileSha256,
        ];
    }

    protected function generatedPassphrase(): string
    {
        return PassphraseInput::generate();
    }
}
