<?php

namespace Tests\Unit\Migration\Package\Concerns;

use App\Services\Migration\Package\FrameCodec;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageLimits;

/**
 * Builds packages a correct writer would never produce: a table of contents that is authentic
 * (encrypted with the real keys) but lies, or a file whose trailer hash was recomputed after a
 * tamper. Authenticity is not enough for a reader to trust a record, and these prove it.
 * Depends on BuildsPackages::walk() and is written without the PackageReader.
 */
trait ForgesPackages
{
    /**
     * Replace the table of contents of a finished package with $mutate(toc) re-encrypted under the
     * package's own keys, and fix the trailer so the file is internally consistent.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $mutate
     */
    protected function forgeToc(string $path, callable $mutate): void
    {
        $walk = $this->walk($path);
        $toc = $mutate($walk['toc']);
        $json = json_encode($toc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $this->writeFileWithToc($path, $walk, $json);
    }

    /** Same as forgeToc() but with a raw (possibly invalid JSON) plaintext. */
    protected function forgeRawToc(string $path, string $plaintext): void
    {
        $this->writeFileWithToc($path, $this->walk($path), $plaintext);
    }

    /** @param  array<string, mixed>  $walk */
    private function writeFileWithToc(string $path, array $walk, string $plaintext): void
    {
        $codec = $walk['codec'];
        $prefix = substr((string) file_get_contents($path), 0, $walk['entries'][PackageLimits::ENTRY_ID_TOC]['offset']);

        $id = PackageLimits::ENTRY_ID_TOC;
        $length = strlen($plaintext);
        $frames = $codec->frameCountFor($length);
        $entry = $codec->entryHeader($id, FrameCodec::KEY_CLASS_DATA, $frames, $length);

        for ($k = 0; $k < $frames; $k++) {
            $chunk = substr($plaintext, $k * $codec->blockSize(), $codec->plainLengthOfFrame($k, $frames, $length));
            $entry .= $codec->encryptFrame($chunk, $id, FrameCodec::KEY_CLASS_DATA, $k, $frames, $length);
        }

        $body = $prefix.$entry;
        $trailer = PackageLimits::TRAILER_MAGIC.pack('J', strlen($prefix)).pack('J', strlen($entry)).hash('sha256', $body, true).PackageFormat::MAGIC;

        file_put_contents($path, $body.$trailer);
    }

    /** After a tamper: make the whole-file hash in the trailer true again so only the AEAD can catch it. */
    protected function refreshTrailerHash(string $path): void
    {
        $bytes = (string) file_get_contents($path);
        $body = substr($bytes, 0, -PackageLimits::TRAILER_LEN);
        $trailer = substr($bytes, -PackageLimits::TRAILER_LEN);

        file_put_contents($path, $body.substr($trailer, 0, 24).hash('sha256', $body, true).substr($trailer, 56));
    }

    /** A copy of a package under another name. */
    protected function copyPackage(string $from, string $name): string
    {
        $to = $this->packagePath($name);
        copy($from, $to);

        return $to;
    }
}
