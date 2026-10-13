<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\FrameCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GCM authenticates each frame's own bytes. What stops an attacker rearranging a package out of
 * valid frames (reorder, duplicate, drop the last, move a frame into another entry or package) is
 * the 42-byte AAD that binds every frame to its package, entry, position, frame count and length.
 * Block size 2^16 keeps the test entries small; the logic is the same at 2^20.
 */
class FrameAadTest extends TestCase
{
    private const LOG2 = 16;

    private const BLOCK = 65536;

    private function codec(string $packageId = 'PPPPPPPPPPPPPPPP', ?string $data = null, ?string $secrets = null): FrameCodec
    {
        return new FrameCodec($data ?? str_repeat('D', 32), $secrets ?? str_repeat('S', 32), $packageId, self::LOG2);
    }

    /** Encrypt a whole entry; returns the frames (each iv|tag|len|ct) and the entry parameters. */
    private function entry(FrameCodec $codec, string $plain, int $entryId = 7, int $keyClass = FrameCodec::KEY_CLASS_DATA): array
    {
        $plainLen = strlen($plain);
        $count = $codec->frameCountFor($plainLen);
        $frames = [];

        for ($k = 0; $k < $count; $k++) {
            $chunk = substr($plain, $k * self::BLOCK, self::BLOCK);
            $frames[] = $codec->encryptFrame($chunk, $entryId, $keyClass, $k, $count, $plainLen);
        }

        return [$frames, $count, $plainLen];
    }

    private function decryptAll(FrameCodec $codec, array $frames, int $count, int $plainLen, int $entryId = 7, int $keyClass = FrameCodec::KEY_CLASS_DATA): string
    {
        $out = '';
        foreach ($frames as $k => $frame) {
            $out .= $codec->decryptFrame($frame, $entryId, $keyClass, $k, $count, $plainLen);
        }

        return $out;
    }

    private function assertRefused(callable $attempt, string $because): void
    {
        try {
            $attempt();
            $this->fail("Must be refused: {$because}");
        } catch (CorruptException) {
            $this->addToAssertionCount(1);
        }
    }

    // ---- published AES-256-GCM known-answer vectors (McGrew & Viega, the GCM spec test cases) ----

    #[Test]
    public function aes_256_gcm_matches_the_published_test_cases(): void
    {
        $key = hex2bin('feffe9928665731c6d6a8f9467308308feffe9928665731c6d6a8f9467308308');
        $iv = hex2bin('cafebabefacedbaddecaf888');
        $p64 = hex2bin('d9313225f88406e5a55909c5aff5269a86a7a9531534f7da2e4c303d8a318a721c3c0c95956809532fcf0e2449a6b525b16aedf5aa0de657ba637b391aafd255');

        // Test case 14: all-zero key, empty plaintext.
        openssl_encrypt('', 'aes-256-gcm', str_repeat("\0", 32), OPENSSL_RAW_DATA, str_repeat("\0", 12), $tag14, '', 16);
        $this->assertSame('530f8afbc74536b9a963b4f1c4cb738b', bin2hex($tag14));

        // Test case 15: 64 bytes, no additional data.
        $ct15 = openssl_encrypt($p64, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag15, '', 16);
        $this->assertSame('522dc1f099567d07f47f37a32a84427d643a8cdcbfe5c0c97598a2bd2555d1aa8cb08e48590dbb3da7b08b1056828838c5f61e6393ba7a0abcc9f662898015ad', bin2hex($ct15));
        $this->assertSame('b094dac5d93471bdec1a502270e3cc6c', bin2hex($tag15));

        // Test case 16: 60 bytes with 20 bytes of additional data.
        $ct16 = openssl_encrypt(substr($p64, 0, 60), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag16, hex2bin('feedfacedeadbeeffeedfacedeadbeefabaddad2'), 16);
        $this->assertSame('522dc1f099567d07f47f37a32a84427d643a8cdcbfe5c0c97598a2bd2555d1aa8cb08e48590dbb3da7b08b1056828838c5f61e6393ba7a0abcc9f662', bin2hex($ct16));
        $this->assertSame('76fc6ece0f4e1768cddf8853bb2d551b', bin2hex($tag16));
    }

    // ---- layout ---------------------------------------------------------------------------------

    #[Test]
    public function the_aad_is_exactly_42_bytes_and_binds_every_field(): void
    {
        $codec = $this->codec();
        $aad = $codec->aad(7, 0, 3, 9, 123456);

        $this->assertSame(42, strlen($aad));
        $this->assertSame("OEP1\0", substr($aad, 0, 5));
        $this->assertSame('PPPPPPPPPPPPPPPP', substr($aad, 5, 16));
        $this->assertSame(7, unpack('N', substr($aad, 21, 4))[1]);
        $this->assertSame(0, ord($aad[25]));
        $this->assertSame([3, 9], array_values(unpack('N2', substr($aad, 26, 8))));
        $this->assertSame(123456, unpack('J', substr($aad, 34, 8))[1]);
    }

    #[Test]
    public function frames_have_a_constant_stride_so_any_frame_can_be_located_directly(): void
    {
        $codec = $this->codec();

        $this->assertSame(0, $codec->frameOffset(0));
        $this->assertSame(32 + self::BLOCK, $codec->frameOffset(1));
        $this->assertSame(5 * (32 + self::BLOCK), $codec->frameOffset(5));

        [$frames, $count] = $this->entry($codec, random_bytes(self::BLOCK * 2 + 100));
        $this->assertSame(3, $count);
        $this->assertSame(32 + self::BLOCK, strlen($frames[0]));
        $this->assertSame(32 + self::BLOCK, strlen($frames[1]));
        $this->assertSame(32 + 100, strlen($frames[2]), 'only the last frame is short');
    }

    #[Test]
    public function the_frame_count_and_total_length_follow_from_the_plain_length(): void
    {
        $codec = $this->codec();

        $this->assertSame(1, $codec->frameCountFor(0), 'an empty entry still has one frame so its tag authenticates it');
        $this->assertSame(1, $codec->frameCountFor(1));
        $this->assertSame(1, $codec->frameCountFor(self::BLOCK));
        $this->assertSame(2, $codec->frameCountFor(self::BLOCK + 1));
        $this->assertSame(24 + 32 + 0, $codec->encryptedLengthFor(0));
        $this->assertSame(24 + 2 * 32 + self::BLOCK + 1, $codec->encryptedLengthFor(self::BLOCK + 1));
    }

    #[Test]
    public function the_entry_header_round_trips(): void
    {
        $codec = $this->codec();
        $bytes = $codec->entryHeader(0xFFFFFFFF, FrameCodec::KEY_CLASS_SECRETS, 12, 8_000_000_000);

        $this->assertSame(24, strlen($bytes));
        $this->assertSame(['entryId' => 0xFFFFFFFF, 'keyClass' => 1, 'frameCount' => 12, 'plainLen' => 8_000_000_000], FrameCodec::parseEntryHeader($bytes));

        $this->assertRefused(fn () => FrameCodec::parseEntryHeader('XXXX'.substr($bytes, 4)), 'wrong entry magic');
        $this->assertRefused(fn () => FrameCodec::parseEntryHeader(substr($bytes, 0, 10)), 'short entry header');
    }

    // ---- round trip and the tamper matrix -----------------------------------------------------

    #[Test]
    public function an_entry_round_trips_across_several_frames(): void
    {
        $codec = $this->codec();
        $plain = random_bytes(self::BLOCK * 3 + 777);

        [$frames, $count, $plainLen] = $this->entry($codec, $plain);

        $this->assertSame($plain, $this->decryptAll($codec, $frames, $count, $plainLen));
    }

    #[Test]
    public function an_empty_entry_round_trips(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, '');

        $this->assertSame('', $this->decryptAll($codec, $frames, $count, $plainLen));
    }

    #[Test]
    public function each_frame_uses_a_fresh_iv(): void
    {
        $codec = $this->codec();
        [$frames] = $this->entry($codec, str_repeat('A', self::BLOCK * 4));

        $ivs = array_map(fn ($f) => substr($f, 0, 12), $frames);
        $this->assertCount(4, array_unique($ivs));
    }

    #[Test]
    public function swapping_two_frames_inside_an_entry_is_detected(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(self::BLOCK * 3));

        [$frames[0], $frames[1]] = [$frames[1], $frames[0]];

        $this->assertRefused(fn () => $this->decryptAll($codec, $frames, $count, $plainLen), 'reordered frames');
    }

    #[Test]
    public function duplicating_a_frame_in_place_of_another_is_detected(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(self::BLOCK * 3));

        $frames[2] = $frames[1];

        $this->assertRefused(fn () => $this->decryptAll($codec, $frames, $count, $plainLen), 'duplicated frame');
    }

    #[Test]
    public function dropping_the_last_frame_is_detected_because_the_frame_count_is_bound(): void
    {
        $codec = $this->codec();
        $plain = random_bytes(self::BLOCK * 2 + 10);
        [$frames, $count, $plainLen] = $this->entry($codec, $plain);

        array_pop($frames);

        // The attacker also shortens the header to match: frame_count 2, plain_len two blocks.
        $this->assertRefused(
            fn () => $this->decryptAll($codec, $frames, 2, self::BLOCK * 2),
            'a truncated entry whose header was rewritten to match'
        );
        // And an honest reader that still believes in the original header notices the missing frame.
        $this->assertRefused(
            fn () => $codec->decryptFrame($frames[1], 7, 0, 2, $count, $plainLen),
            'frame 1 presented as the last one'
        );
    }

    #[Test]
    public function a_frame_moved_into_another_entry_is_detected(): void
    {
        $codec = $this->codec();
        [$a, $countA, $lenA] = $this->entry($codec, random_bytes(self::BLOCK), entryId: 1);

        $this->assertRefused(
            fn () => $codec->decryptFrame($a[0], 2, 0, 0, $countA, $lenA),
            'entry 1 frame presented as entry 2'
        );
    }

    #[Test]
    public function a_frame_moved_into_another_package_is_detected(): void
    {
        $one = $this->codec('AAAAAAAAAAAAAAAA');
        $two = $this->codec('BBBBBBBBBBBBBBBB');
        [$frames, $count, $plainLen] = $this->entry($one, random_bytes(self::BLOCK));

        // Same keys on purpose: only the package id in the AAD differs.
        $this->assertRefused(fn () => $two->decryptFrame($frames[0], 7, 0, 0, $count, $plainLen), 'same keys, different package');
    }

    #[Test]
    public function altering_the_declared_entry_length_or_class_is_detected(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(self::BLOCK - 5));

        $this->assertRefused(fn () => $codec->decryptFrame($frames[0], 7, 0, 0, $count, $plainLen + 1), 'plain_len altered');
        $this->assertRefused(fn () => $codec->decryptFrame($frames[0], 7, 1, 0, $count, $plainLen), 'key class altered to secrets');
    }

    #[Test]
    public function a_flipped_ciphertext_tag_or_iv_bit_is_detected(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(1000));

        foreach ([0, 5, 12, 20, 32, 500, strlen($frames[0]) - 1] as $position) {
            $tampered = $frames[0];
            $tampered[$position] = $tampered[$position] ^ "\x01";

            $this->assertRefused(fn () => $codec->decryptFrame($tampered, 7, 0, 0, $count, $plainLen), "byte {$position} flipped");
        }
    }

    #[Test]
    public function the_secrets_key_class_cannot_be_opened_with_the_data_key_and_vice_versa(): void
    {
        $codec = $this->codec();
        [$secrets, $count, $plainLen] = $this->entry($codec, 'payment key', keyClass: FrameCodec::KEY_CLASS_SECRETS);

        $this->assertSame('payment key', $codec->decryptFrame($secrets[0], 7, FrameCodec::KEY_CLASS_SECRETS, 0, $count, $plainLen));
        $this->assertRefused(fn () => $codec->decryptFrame($secrets[0], 7, FrameCodec::KEY_CLASS_DATA, 0, $count, $plainLen), 'secrets frame opened as data');
        $this->assertRefused(fn () => $codec->decryptFrame($secrets[0], 7, 5, 0, $count, $plainLen), 'unknown key class');
    }

    #[Test]
    public function a_wrong_key_never_opens_a_frame(): void
    {
        [$frames, $count, $plainLen] = $this->entry($this->codec(), 'secret');

        $this->assertRefused(fn () => $this->codec(data: str_repeat('X', 32))->decryptFrame($frames[0], 7, 0, 0, $count, $plainLen), 'wrong data key');
    }

    // ---- plausibility guard (like BackupCipher: nothing is read on a declared length that cannot be true) ----

    #[Test]
    public function an_implausible_declared_length_is_refused_before_any_ciphertext_is_read(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(self::BLOCK * 2));

        $header = substr($frames[0], 0, 28).pack('N', 0x7FFFFFFF); // 2 GiB declared, only the 32 header bytes given

        $this->assertRefused(fn () => $codec->assertDeclaredLength($header, 0, $count, $plainLen), 'a frame declaring 2 GiB');
        $this->assertSame(self::BLOCK, $codec->assertDeclaredLength(substr($frames[0], 0, 32), 0, $count, $plainLen));
    }

    #[Test]
    public function the_last_frame_must_declare_exactly_the_remainder(): void
    {
        $codec = $this->codec();
        [$frames, $count, $plainLen] = $this->entry($codec, random_bytes(self::BLOCK + 123));

        $this->assertSame(123, $codec->assertDeclaredLength(substr($frames[1], 0, 32), 1, $count, $plainLen));

        $this->assertRefused(
            fn () => $codec->assertDeclaredLength(substr($frames[1], 0, 28).pack('N', 124), 1, $count, $plainLen),
            'the last frame claims one byte too many'
        );
        $this->assertRefused(
            fn () => $codec->assertDeclaredLength(substr($frames[0], 0, 28).pack('N', 123), 0, $count, $plainLen),
            'a middle frame that is not a full block'
        );
    }

    #[Test]
    public function encrypting_a_frame_of_the_wrong_size_is_a_programming_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->codec()->encryptFrame('short', 7, 0, 0, 2, self::BLOCK + 5);
    }

    #[Test]
    public function a_flipped_bit_in_the_unauthenticated_reserved_bytes_of_an_entry_header_is_refused(): void
    {
        $header = $this->codec()->entryHeader(7, FrameCodec::KEY_CLASS_DATA, 3, 150000);

        $this->assertSame(7, FrameCodec::parseEntryHeader($header)['entryId'], 'the untouched header parses');

        foreach ([9, 10, 11] as $offset) {
            $tampered = $header;
            $tampered[$offset] = '';

            try {
                FrameCodec::parseEntryHeader($tampered);
                $this->fail("a change at byte {$offset} went unnoticed");
            } catch (CorruptException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
