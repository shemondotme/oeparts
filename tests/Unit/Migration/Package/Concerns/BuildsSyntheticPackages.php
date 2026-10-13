<?php

namespace Tests\Unit\Migration\Package\Concerns;

use App\Services\Migration\Package\DerivedKeys;
use App\Services\Migration\Package\FrameCodec;
use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PackageLimits;

/**
 * Hand-assembles a minimal package with header fields a real writer would never emit (a newer
 * minor version, ignorable flags, a longer header, unknown extra fields), so the compatibility
 * policy of the reader can be tested without waiting for a version 1.1 to exist. The cryptography
 * is the real one; only the header layout choices are free.
 */
trait BuildsSyntheticPackages
{
    /**
     * @param  array<string, mixed>  $o  major, minor, headerLen, critical, ignorable, cipher, kdfId, p1, p2, tocExtra, recordExtra
     */
    protected function synthesize(string $path, array $o = []): void
    {
        $major = $o['major'] ?? PackageFormat::FORMAT_MAJOR;
        $minor = $o['minor'] ?? PackageFormat::FORMAT_MINOR;
        $headerLen = $o['headerLen'] ?? PackageFormat::HEADER_LEN;
        $critical = $o['critical'] ?? 0;
        $ignorable = $o['ignorable'] ?? 0;
        $cipher = $o['cipher'] ?? PackageFormat::CIPHER_AES_256_GCM;
        $kdfId = $o['kdfId'] ?? PackageFormat::KDF_PBKDF2_SHA512;
        $p1 = $o['p1'] ?? 2000;
        $p2 = $o['p2'] ?? 0;
        $log2 = PackageFormat::MIN_BLOCK_LOG2;
        $salt = str_repeat('S', 16);
        $packageId = str_repeat('P', 16);

        $auth = PackageFormat::MAGIC.PackageFormat::TEXT_GUARD
            .pack('nnn', $major, $minor, $headerLen)."\0\0"
            .pack('NN', $critical, $ignorable)
            .pack('CCC', $cipher, $kdfId, $log2)."\0"
            .pack('NN', $p1, $p2)
            .$salt.$packageId
            .str_repeat("\0", $headerLen - 32 - 72);

        $keys = new DerivedKeys(hash_pbkdf2('sha512', $this->passphrase, $salt, 2000, 32, true), $auth);
        $header = $auth.hash_hmac('sha256', PackageFormat::KEYCHECK_CONTEXT.$auth, $keys->check(), true);

        $codec = new FrameCodec($keys->data(), $keys->secrets(), $packageId, $log2);

        $meta = json_encode(['format' => ['major' => $major, 'minor' => $minor], 'package_id' => bin2hex($packageId), 'source' => ['domain' => 'future.example']]);
        $records = [];
        $body = $header;

        foreach ([[0, 'meta', $meta], [1, 'report/a.json', '{"future":true}']] as [$id, $name, $plain]) {
            $entry = $this->encryptEntry($codec, $id, $plain);
            $records[] = array_merge([
                'id' => $id, 'name' => $name, 'kind' => $id === 0 ? 'meta' : 'report', 'group' => null, 'critical' => $id === 0,
                'key_class' => 0, 'offset' => strlen($body), 'frames' => $codec->frameCountFor(strlen($plain)),
                'plain_len' => strlen($plain), 'enc_len' => strlen($entry), 'enc_sha256' => hash('sha256', $entry),
                'plain_sha256' => hash('sha256', $plain), 'codec' => 'none', 'raw_len' => strlen($plain), 'rows' => null, 'attrs' => new \stdClass,
            ], $o['recordExtra'] ?? []);
            $body .= $entry;
        }

        $plainTotal = array_sum(array_column($records, 'plain_len'));
        $toc = json_encode(array_merge([
            'format' => ['major' => $major, 'minor' => $minor],
            'package_id' => bin2hex($packageId),
            'meta_sha256' => $records[0]['plain_sha256'],
            'contains_secrets' => false,
            'totals' => ['entries' => count($records), 'plain_bytes' => $plainTotal, 'raw_bytes' => $plainTotal],
            'entries' => $records,
        ], $o['tocExtra'] ?? []));

        $tocOffset = strlen($body);
        $tocEntry = $this->encryptEntry($codec, PackageLimits::ENTRY_ID_TOC, $toc);
        $body .= $tocEntry;

        file_put_contents($path, $body.PackageLimits::TRAILER_MAGIC.pack('JJ', $tocOffset, strlen($tocEntry)).hash('sha256', $body, true).PackageFormat::MAGIC);
    }

    private function encryptEntry(FrameCodec $codec, int $id, string $plain): string
    {
        $length = strlen($plain);
        $frames = $codec->frameCountFor($length);
        $entry = $codec->entryHeader($id, FrameCodec::KEY_CLASS_DATA, $frames, $length);

        for ($k = 0; $k < $frames; $k++) {
            $chunk = substr($plain, $k * $codec->blockSize(), $codec->plainLengthOfFrame($k, $frames, $length));
            $entry .= $codec->encryptFrame($chunk, $id, FrameCodec::KEY_CLASS_DATA, $k, $frames, $length);
        }

        return $entry;
    }
}
