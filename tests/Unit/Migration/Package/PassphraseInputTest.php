<?php

namespace Tests\Unit\Migration\Package;

use App\Services\Migration\Package\PackageFormat;
use App\Services\Migration\Package\PassphraseInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PassphraseInputTest extends TestCase
{
    private const ARGON2 = PackageFormat::KDF_ARGON2ID;

    private const PBKDF2 = PackageFormat::KDF_PBKDF2_SHA512;

    #[Test]
    public function a_generated_passphrase_has_six_groups_of_four_and_is_never_repeated(): void
    {
        $seen = [];

        for ($i = 0; $i < 50; $i++) {
            $p = PassphraseInput::generate();

            $this->assertMatchesRegularExpression('/^(?:[0-9A-HJKMNP-TV-Z]{4}-){5}[0-9A-HJKMNP-TV-Z]{4}$/', $p);
            $this->assertTrue(PassphraseInput::isGeneratedShape($p));
            $seen[$p] = true;
        }

        $this->assertCount(50, $seen, '120 random bits do not collide');
    }

    #[Test]
    public function the_generated_passphrase_passes_under_both_key_derivations(): void
    {
        $p = PassphraseInput::generate();

        $this->assertNull(PassphraseInput::validate($p, self::ARGON2));
        $this->assertNull(PassphraseInput::validate($p, self::PBKDF2));
    }

    /** @return array<string, array{0: string, 1: int, 2: ?string}> */
    public static function rules(): array
    {
        return [
            'empty is refused' => ['', self::ARGON2, PassphraseInput::EMPTY],
            'empty under pbkdf2' => ['', self::PBKDF2, PassphraseInput::EMPTY],
            'argon2: 13 characters is too short' => ['abcdefghijklm', self::ARGON2, PassphraseInput::TOO_SHORT],
            'argon2: 14 characters is enough' => ['abcdefghijklmn', self::ARGON2, null],
            'argon2: a plain sentence is enough' => ['correct horse battery staple', self::ARGON2, null],
            'pbkdf2: 19 characters is too short' => ['k9#Vx2!mQ7@pL4wZ8%r', self::PBKDF2, PassphraseInput::TOO_SHORT],
            'pbkdf2: 20 strong characters pass' => ['k9#Vx2!mQ7@pL4wZ8%rT', self::PBKDF2, null],
            'pbkdf2: the same 14 characters that pass under argon2 do not' => ['abcdefghijklmn', self::PBKDF2, PassphraseInput::TOO_SHORT],
            'pbkdf2: long but one repeated letter' => ['aaaaaaaaaaaaaaaaaaaaaaaa', self::PBKDF2, PassphraseInput::WEAK_FOR_PBKDF2],
            'pbkdf2: the alphabet in order' => ['abcdefghijklmnopqrstuvwxyz', self::PBKDF2, PassphraseInput::WEAK_FOR_PBKDF2],
            'pbkdf2: counting digits' => ['12345678901234567890', self::PBKDF2, PassphraseInput::WEAK_FOR_PBKDF2],
            'pbkdf2: a common password with digits' => ['password123456789012', self::PBKDF2, PassphraseInput::WEAK_FOR_PBKDF2],
            'pbkdf2: the famous xkcd phrase' => ['correct horse battery staple', self::PBKDF2, PassphraseInput::WEAK_FOR_PBKDF2],
            'pbkdf2: a long mixed phrase passes' => ['Tr0ub4dor&3-Horse-Battery-Staple!', self::PBKDF2, null],
        ];
    }

    #[Test]
    #[DataProvider('rules')]
    public function the_rule_depends_on_how_expensive_a_guess_is(string $passphrase, int $kdfId, ?string $expected): void
    {
        $this->assertSame($expected, PassphraseInput::validate($passphrase, $kdfId));
    }

    #[Test]
    public function the_same_passphrase_can_pass_under_argon2id_and_be_refused_under_pbkdf2(): void
    {
        $p = 'abcdefghijklmn';

        $this->assertNull(PassphraseInput::validate($p, self::ARGON2));
        $this->assertNotNull(PassphraseInput::validate($p, self::PBKDF2));
    }

    #[Test]
    public function the_estimate_never_claims_more_than_the_passphrase_has(): void
    {
        $this->assertSame(0.0, PassphraseInput::estimateBits(''));
        $this->assertSame(0.0, PassphraseInput::estimateBits('password'), 'a common password is worth nothing');
        $this->assertLessThan(10, PassphraseInput::estimateBits('abcdefghijklmnopqrstuvwxyz'), 'a run of consecutive letters is one letter of information');
        $this->assertLessThan(60, PassphraseInput::estimateBits('aaaaaaaaaaaaaaaaaaaaaaaa'));
        $this->assertGreaterThan(100, PassphraseInput::estimateBits('k9#Vx2!mQ7@pL4wZ8%rT'));
        $this->assertGreaterThan(PassphraseInput::estimateBits('k9#Vx2!mQ7@pL4wZ8%'), PassphraseInput::estimateBits('k9#Vx2!mQ7@pL4wZ8%rT'), 'longer is stronger');
    }

    #[Test]
    public function a_typing_slip_in_a_generated_passphrase_is_forgiven_but_a_normal_passphrase_is_used_literally(): void
    {
        // Read from paper: lower case, O for 0, I or L for 1, stray spaces.
        $typed = 'mq4x-9t2b-7hdw-k5rn-c8ve-3yga';
        $this->assertSame(['mq4x-9t2b-7hdw-k5rn-c8ve-3yga', 'MQ4X-9T2B-7HDW-K5RN-C8VE-3YGA'], PassphraseInput::candidates($typed));

        $this->assertContains('0101-0101-0101-0101-0101-0101', PassphraseInput::candidates('OIOI-OLOI-OIOL-OIOI-OIOI-OIOI'));

        // A normal passphrase is never rewritten: bytes are used literally, so "Correct Horse" stays itself.
        $this->assertSame(['Correct Horse Battery'], PassphraseInput::candidates('Correct Horse Battery'));
        $this->assertSame(['hello-world-oil-lamp-1234'], PassphraseInput::candidates('hello-world-oil-lamp-1234'));
    }

    #[Test]
    public function a_generated_passphrase_is_recognised_by_shape_even_when_typed_in_lower_case(): void
    {
        $this->assertTrue(PassphraseInput::isGeneratedShape('mq4x-9t2b-7hdw-k5rn-c8ve-3yga'));
        $this->assertFalse(PassphraseInput::isGeneratedShape('mq4x-9t2b-7hdw-k5rn-c8ve'), 'five groups');
        $this->assertFalse(PassphraseInput::isGeneratedShape('MQ4X9T2B7HDWK5RNC8VE3YGA'), 'no hyphens');
        $this->assertFalse(PassphraseInput::isGeneratedShape('MQ4U-9T2B-7HDW-K5RN-C8VE-3YGA'), 'U is not in the alphabet');
    }
}
