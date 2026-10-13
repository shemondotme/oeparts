<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Export\Transform\Pseudonymizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PseudonymizerTest extends TestCase
{
    private function make(string $salt = 'a-salt-that-is-long-enough-for-the-rule'): Pseudonymizer
    {
        return new Pseudonymizer($salt);
    }

    #[Test]
    public function the_same_input_gives_the_same_stand_in_and_a_different_one_gives_another(): void
    {
        $p = $this->make();

        $this->assertSame($p->email('anna@example.com'), $p->email('anna@example.com'));
        $this->assertNotSame($p->email('anna@example.com'), $p->email('bob@example.com'));
        $this->assertSame($p->name('Anna Berg'), $p->name('Anna Berg'));
        $this->assertNotSame($p->phone('+370 600 11111'), $p->phone('+370 600 22222'));
    }

    #[Test]
    public function an_email_is_the_same_person_whatever_its_case_or_spacing(): void
    {
        $p = $this->make();

        $this->assertSame($p->email('Anna@Example.com'), $p->email('  anna@example.com '));
    }

    #[Test]
    public function the_stand_ins_can_never_reach_a_real_mailbox_phone_or_host(): void
    {
        $p = $this->make();

        $this->assertMatchesRegularExpression('/^u[0-9a-f]{16}@example\.invalid$/', $p->email('anna@example.com'));
        $this->assertMatchesRegularExpression('/^\+000\d{8}$/', $p->phone('+370 600 11111'));
        $this->assertMatchesRegularExpression('/^192\.0\.2\.(?:[1-9]\d?|1\d\d|2[0-4]\d|25[0-4])$/', $p->ip('203.0.113.77'));
        $this->assertMatchesRegularExpression('/^[0-9A-F]{17}$/', $p->vin('WVWZZZ1JZXW000001'));
    }

    #[Test]
    public function a_different_salt_gives_different_stand_ins_so_two_exports_cannot_be_lined_up(): void
    {
        $a = $this->make('first-salt-first-salt-first-salt-first');
        $b = $this->make('other-salt-other-salt-other-salt-other');

        $this->assertNotSame($a->email('anna@example.com'), $b->email('anna@example.com'));
        $this->assertNotSame($a->vin('WVWZZZ1JZXW000001'), $b->vin('WVWZZZ1JZXW000001'));
    }

    #[Test]
    public function the_stand_in_does_not_contain_the_original(): void
    {
        $p = $this->make();

        foreach ([$p->email('anna.berg@example.com'), $p->name('Anna Berg'), $p->street('Main Street 5'), $p->company('Berg Auto')] as $standIn) {
            $this->assertStringNotContainsStringIgnoringCase('berg', $standIn);
            $this->assertStringNotContainsStringIgnoringCase('anna', $standIn);
        }
    }

    #[Test]
    public function new_salts_are_random_and_long_enough_and_a_short_salt_is_refused(): void
    {
        $this->assertNotSame(Pseudonymizer::newSalt(), Pseudonymizer::newSalt());
        $this->assertGreaterThanOrEqual(32, strlen(Pseudonymizer::newSalt()));

        $this->expectException(\InvalidArgumentException::class);
        new Pseudonymizer('short');
    }
}
