<?php

namespace App\Services\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;

/**
 * Encrypts and decrypts the frames of one entry (AES-256-GCM).
 *
 * An entry is a 24-byte plaintext header followed by frames:
 *
 *   entry header  'OEPE' | entry_id u32 | key_class u8 | 3 reserved | frame_count u32 | plain_len u64
 *   frame         iv(12) | tag(16) | ct_len u32 | ciphertext
 *
 * Every frame except the last carries exactly 2^block_log2 plaintext bytes, so frame k starts at
 * k * (32 + block) from the first frame: any frame can be located without reading the ones before
 * it (resume, ranged reads), and the length a frame declares is checked against the length it must
 * have BEFORE its ciphertext is read.
 *
 * The additional authenticated data of a frame is 42 bytes:
 *
 *   "OEP1\0" | package_id(16) | entry_id u32 | key_class u8 | seq u32 | frame_count u32 | plain_len u64
 *
 * It binds the frame to its package, its entry, its position, the number of frames and the length
 * of the entry. Reordering, duplicating, dropping the last frame, or moving a frame into another
 * entry or another package all change the AAD and fail authentication; GCM alone only protects each
 * frame's own bytes.
 */
final class FrameCodec
{
    public const ENTRY_MAGIC = 'OEPE';

    public const ENTRY_HEADER_LEN = 24;

    /** iv 12 + tag 16 + ct_len 4 */
    public const FRAME_HEADER_LEN = 32;

    public const KEY_CLASS_DATA = 0;

    public const KEY_CLASS_SECRETS = 1;

    private const AAD_PREFIX = "OEP1\0";

    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private readonly string $dataKey,
        private readonly string $secretsKey,
        private readonly string $packageId,
        private readonly int $blockLog2 = PackageFormat::DEFAULT_BLOCK_LOG2,
    ) {
        if (strlen($packageId) !== PackageFormat::PACKAGE_ID_LEN) {
            throw new \InvalidArgumentException('The package id must be 16 bytes.');
        }
    }

    public function blockSize(): int
    {
        return 1 << $this->blockLog2;
    }

    /** Frames needed for an entry. An empty entry still has one (empty) frame so its tag authenticates it. */
    public function frameCountFor(int $plainLen): int
    {
        return max(1, intdiv($plainLen + $this->blockSize() - 1, $this->blockSize()));
    }

    /** Bytes the whole entry (header + frames) occupies in the file. */
    public function encryptedLengthFor(int $plainLen): int
    {
        return self::ENTRY_HEADER_LEN + $this->frameCountFor($plainLen) * self::FRAME_HEADER_LEN + $plainLen;
    }

    /** Offset of frame $seq from the first frame (constant stride). */
    public function frameOffset(int $seq): int
    {
        return $seq * (self::FRAME_HEADER_LEN + $this->blockSize());
    }

    /** The plaintext length frame $seq must have. */
    public function plainLengthOfFrame(int $seq, int $frameCount, int $plainLen): int
    {
        return $seq < $frameCount - 1
            ? $this->blockSize()
            : $plainLen - ($frameCount - 1) * $this->blockSize();
    }

    public function entryHeader(int $entryId, int $keyClass, int $frameCount, int $plainLen): string
    {
        return self::ENTRY_MAGIC.pack('N', $entryId).chr($keyClass)."\0\0\0".pack('N', $frameCount).pack('J', $plainLen);
    }

    /**
     * @return array{entryId: int, keyClass: int, frameCount: int, plainLen: int}
     *
     * @throws CorruptException
     */
    public static function parseEntryHeader(string $bytes): array
    {
        if (strlen($bytes) < self::ENTRY_HEADER_LEN || substr($bytes, 0, 4) !== self::ENTRY_MAGIC) {
            throw new CorruptException('An entry does not start with its header.', 'entry header');
        }

        return [
            'entryId' => unpack('N', substr($bytes, 4, 4))[1],
            'keyClass' => ord($bytes[8]),
            'frameCount' => unpack('N', substr($bytes, 12, 4))[1],
            'plainLen' => unpack('J', substr($bytes, 16, 8))[1],
        ];
    }

    /** The 42 bytes every frame is authenticated against. */
    public function aad(int $entryId, int $keyClass, int $seq, int $frameCount, int $plainLen): string
    {
        return self::AAD_PREFIX.$this->packageId.pack('N', $entryId).chr($keyClass).pack('NN', $seq, $frameCount).pack('J', $plainLen);
    }

    /** @return string iv | tag | ct_len | ciphertext */
    public function encryptFrame(string $plain, int $entryId, int $keyClass, int $seq, int $frameCount, int $plainLen): string
    {
        $expected = $this->plainLengthOfFrame($seq, $frameCount, $plainLen);
        if (strlen($plain) !== $expected) {
            throw new \InvalidArgumentException("Frame {$seq} must carry {$expected} bytes, got ".strlen($plain).'.');
        }

        $iv = random_bytes(12);
        $ciphertext = openssl_encrypt(
            $plain, self::CIPHER, $this->keyFor($keyClass), OPENSSL_RAW_DATA, $iv, $tag,
            $this->aad($entryId, $keyClass, $seq, $frameCount, $plainLen), 16
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new \RuntimeException('AES-256-GCM encryption failed.');
        }

        return $iv.$tag.pack('N', strlen($ciphertext)).$ciphertext;
    }

    /**
     * The plaintext length a frame header declares, checked against the length the frame must
     * have, before any ciphertext is read.
     *
     * @throws CorruptException
     */
    public function assertDeclaredLength(string $frameHeader, int $seq, int $frameCount, int $plainLen): int
    {
        if (strlen($frameHeader) < self::FRAME_HEADER_LEN) {
            throw new CorruptException("Frame {$seq} is cut short.", "frame {$seq}");
        }

        $declared = unpack('N', substr($frameHeader, 28, 4))[1];
        $expected = $this->plainLengthOfFrame($seq, $frameCount, $plainLen);

        if ($declared !== $expected) {
            throw new CorruptException("Frame {$seq} declares {$declared} bytes but must have {$expected}.", "frame {$seq}");
        }

        return $declared;
    }

    /**
     * @param  string  $frame  iv | tag | ct_len | ciphertext, exactly one frame
     *
     * @throws CorruptException when authentication fails or the length is implausible
     */
    public function decryptFrame(string $frame, int $entryId, int $keyClass, int $seq, int $frameCount, int $plainLen): string
    {
        $length = $this->assertDeclaredLength($frame, $seq, $frameCount, $plainLen);

        if (strlen($frame) !== self::FRAME_HEADER_LEN + $length) {
            throw new CorruptException("Frame {$seq} has the wrong size.", "frame {$seq}");
        }

        $plain = openssl_decrypt(
            substr($frame, self::FRAME_HEADER_LEN), self::CIPHER, $this->keyFor($keyClass), OPENSSL_RAW_DATA,
            substr($frame, 0, 12), substr($frame, 12, 16),
            $this->aad($entryId, $keyClass, $seq, $frameCount, $plainLen)
        );

        if ($plain === false) {
            throw new CorruptException("Frame {$seq} of entry {$entryId} failed authentication.", "entry {$entryId}, frame {$seq}");
        }

        return $plain;
    }

    private function keyFor(int $keyClass): string
    {
        return match ($keyClass) {
            self::KEY_CLASS_DATA => $this->dataKey,
            self::KEY_CLASS_SECRETS => $this->secretsKey,
            default => throw new CorruptException("Unknown key class {$keyClass}.", 'entry header'),
        };
    }
}
