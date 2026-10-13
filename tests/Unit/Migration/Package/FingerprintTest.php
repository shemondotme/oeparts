<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\Fingerprint;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FingerprintTest extends TestCase
{
    private function key(): string
    {
        return implode('', array_map('chr', range(0, 31)));
    }

    private function packageId(): string
    {
        return implode('', array_map('chr', range(100, 115)));
    }

    #[Test]
    public function crockford_base32_encodes_known_byte_strings(): void
    {
        $this->assertSame('00000000', Fingerprint::crockford32(str_repeat("\x00", 5)));
        $this->assertSame('ZZZZZZZZ', Fingerprint::crockford32(str_repeat("\xFF", 5)));
        // 0x00 0x44 0x32 0x14 0xC7 -> "0123456 7" (the encoding of the digits 0..7), a textbook example.
        $this->assertSame('01234567', Fingerprint::crockford32("\x00\x44\x32\x14\xC7"));
        $this->assertSame(16, strlen(Fingerprint::crockford32(str_repeat("\xAB", 10))), 'ten bytes are exactly sixteen characters');
    }

    #[Test]
    public function the_fingerprint_matches_an_independent_implementation(): void
    {
        // Python: Crockford32(HMAC-SHA256(key, SHA-256(toc) + package id)[:10]) grouped by four.
        $code = Fingerprint::compute($this->key(), '{"entries":[]}', $this->packageId());

        $this->assertSame('9XJ6-M37D-MM5Z-P94K', $code);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){3}$/', $code);
    }

    #[Test]
    public function any_change_to_the_table_of_contents_the_package_or_the_key_changes_it(): void
    {
        $base = Fingerprint::compute($this->key(), '{"entries":[]}', $this->packageId());

        $this->assertNotSame($base, Fingerprint::compute($this->key(), '{"entries":[1]}', $this->packageId()));
        $this->assertNotSame($base, Fingerprint::compute($this->key(), '{"entries":[]}', strrev($this->packageId())));
        $this->assertNotSame($base, Fingerprint::compute(strrev($this->key()), '{"entries":[]}', $this->packageId()));
        $this->assertSame($base, Fingerprint::compute($this->key(), '{"entries":[]}', $this->packageId()), 'deterministic');
    }

    #[Test]
    public function a_code_read_aloud_or_typed_loosely_still_matches(): void
    {
        $code = '9XJ6-M37D-MM5Z-P94K';

        $this->assertTrue(Fingerprint::matches('9xj6m37dmm5zp94k', $code), 'lower case, no hyphens');
        $this->assertTrue(Fingerprint::matches(' 9XJ6 M37D MM5Z P94K ', $code), 'spaces');
        $this->assertTrue(Fingerprint::matches('9XJ6-M37D-MM5Z-P94K', $code));
        $this->assertTrue(Fingerprint::matches('9XJ6-M37D-MM5Z-P94K', '9XJ6-M37D-MM5Z-P94K'));
        $this->assertFalse(Fingerprint::matches('9XJ6-M37D-MM5Z-P94J', $code), 'one character wrong');
        $this->assertFalse(Fingerprint::matches('', $code));
    }

    #[Test]
    public function the_look_alike_letters_crockford_defines_are_forgiven(): void
    {
        // O is read as 0, I and L as 1, so a code with a 0 and a 1 matches when typed with O, I or L.
        $this->assertTrue(Fingerprint::matches('O1O1-IIII-LLLL-OOOO', '0101-1111-1111-0000'));
    }
}
