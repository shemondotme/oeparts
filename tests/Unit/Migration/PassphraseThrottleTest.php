<?php

namespace Tests\Unit\Migration;

use App\Security\PassphraseThrottle;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\Exceptions\CorruptException;
use App\Services\Migration\Package\Exceptions\WrongPassphraseException;
use App\Services\Migration\Package\PackageReader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Migration\Package\Concerns\BuildsPackages;

class PassphraseThrottleTest extends TestCase
{
    use BuildsPackages;

    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeWorkDir();
    }

    protected function tearDown(): void
    {
        $this->removeWorkDir();
        parent::tearDown();
    }

    private function throttle(): PassphraseThrottle
    {
        return new PassphraseThrottle($this->workDir.'/throttle', 5, 60, 900, 86400, fn (): int => $this->now);
    }

    private function wrong(PassphraseThrottle $throttle, string $package = 'pkg', string $client = '1.2.3.4'): void
    {
        try {
            $throttle->attempt($package, $client, function (): never {
                throw new WrongPassphraseException;
            });
        } catch (WrongPassphraseException) {
            // expected: the wrong passphrase is re-thrown after being counted
        }
    }

    #[Test]
    public function four_wrong_passphrases_do_not_lock_the_fifth_does(): void
    {
        $throttle = $this->throttle();

        for ($i = 0; $i < 4; $i++) {
            $this->wrong($throttle);
        }
        $this->assertSame(0, $throttle->secondsLocked('pkg', '1.2.3.4'));

        $this->wrong($throttle);
        $this->assertSame(60, $throttle->secondsLocked('pkg', '1.2.3.4'));
    }

    #[Test]
    public function while_locked_the_attempt_is_refused_before_the_passphrase_is_even_tried(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 5; $i++) {
            $this->wrong($throttle);
        }

        $tried = false;
        try {
            $throttle->attempt('pkg', '1.2.3.4', function () use (&$tried): string {
                $tried = true;

                return 'opened';
            });
            $this->fail('attempt ran while locked');
        } catch (MigrationException $e) {
            $this->assertSame(MigrationErrorCode::PassphraseLocked, $e->errorCode);
            $this->assertSame(60, $e->params['seconds']);
        }

        $this->assertFalse($tried, 'no key derivation may run during a lock');
    }

    #[Test]
    public function the_lock_counts_down_and_lifts(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 5; $i++) {
            $this->wrong($throttle);
        }

        $this->now += 45;
        $this->assertSame(15, $throttle->secondsLocked('pkg', '1.2.3.4'));

        $this->now += 15;
        $this->assertSame(0, $throttle->secondsLocked('pkg', '1.2.3.4'));
        $this->assertSame('opened', $throttle->attempt('pkg', '1.2.3.4', fn (): string => 'opened'));
    }

    #[Test]
    public function every_further_failure_doubles_the_lock_up_to_the_cap(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 5; $i++) {
            $this->wrong($throttle);
        }

        $seen = [60];
        foreach ([1, 2, 3, 4, 5] as $unused) {
            $this->now += $throttle->secondsLocked('pkg', '1.2.3.4');
            $this->wrong($throttle);
            $seen[] = $throttle->secondsLocked('pkg', '1.2.3.4');
        }

        $this->assertSame([60, 120, 240, 480, 900, 900], $seen);
    }

    #[Test]
    public function a_correct_passphrase_clears_the_count(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 4; $i++) {
            $this->wrong($throttle);
        }

        $this->assertSame('ok', $throttle->attempt('pkg', '1.2.3.4', fn (): string => 'ok'));
        $this->assertSame(0, $throttle->failures('pkg', '1.2.3.4'));

        for ($i = 0; $i < 4; $i++) {
            $this->wrong($throttle);
        }
        $this->assertSame(0, $throttle->secondsLocked('pkg', '1.2.3.4'), 'four more is still below the limit');
    }

    #[Test]
    public function only_a_wrong_passphrase_counts_not_a_damaged_file(): void
    {
        $throttle = $this->throttle();

        for ($i = 0; $i < 10; $i++) {
            try {
                $throttle->attempt('pkg', '1.2.3.4', function (): never {
                    throw new CorruptException('bad');
                });
            } catch (CorruptException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, $throttle->failures('pkg', '1.2.3.4'));
    }

    #[Test]
    public function counters_are_per_package_and_per_client(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 5; $i++) {
            $this->wrong($throttle, 'pkg-a', '1.1.1.1');
        }

        $this->assertSame(60, $throttle->secondsLocked('pkg-a', '1.1.1.1'));
        $this->assertSame(0, $throttle->secondsLocked('pkg-b', '1.1.1.1'));
        $this->assertSame(0, $throttle->secondsLocked('pkg-a', '2.2.2.2'));
    }

    #[Test]
    public function an_old_failure_is_forgotten_so_one_slip_a_week_never_adds_up(): void
    {
        $throttle = $this->throttle();
        for ($i = 0; $i < 4; $i++) {
            $this->wrong($throttle);
        }

        $this->now += 86401;
        $this->wrong($throttle);

        $this->assertSame(1, $throttle->failures('pkg', '1.2.3.4'));
        $this->assertSame(0, $throttle->secondsLocked('pkg', '1.2.3.4'));
    }

    #[Test]
    public function no_address_or_package_name_is_written_to_disk(): void
    {
        $throttle = $this->throttle();
        $this->wrong($throttle, 'secret-package-name', '203.0.113.77');

        foreach (glob($this->workDir.'/throttle/*') ?: [] as $file) {
            $this->assertStringNotContainsString('203.0.113.77', $file.file_get_contents($file));
            $this->assertStringNotContainsString('secret-package-name', $file.file_get_contents($file));
        }
    }

    #[Test]
    public function an_unreadable_counter_is_reset_instead_of_blocking_or_crashing(): void
    {
        $throttle = $this->throttle();
        $this->wrong($throttle);

        $file = glob($this->workDir.'/throttle/*.json')[0];
        file_put_contents($file, '{torn');
        @unlink($file.'.prev');

        $this->assertSame(0, $throttle->secondsLocked('pkg', '1.2.3.4'));
        $this->wrong($throttle);
        $this->assertSame(1, $throttle->failures('pkg', '1.2.3.4'));
    }

    #[Test]
    public function prune_removes_only_counters_that_are_old_and_unlocked(): void
    {
        $throttle = $this->throttle();
        $this->wrong($throttle, 'old', 'a');

        $this->now += 90000;
        for ($i = 0; $i < 5; $i++) {
            $this->wrong($throttle, 'locked', 'b');
        }

        $this->assertSame(1, $throttle->prune());
        $this->assertSame(0, $throttle->failures('old', 'a'));
        $this->assertSame(5, $throttle->failures('locked', 'b'));
    }

    #[Test]
    public function nonsense_settings_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PassphraseThrottle($this->workDir, 0);
    }

    #[Test]
    public function the_real_package_flow_locks_after_five_wrong_passphrases(): void
    {
        $writer = $this->newWriter();
        $writer->addEntryFromString('report/a.json', 'report', 'x');
        $writer->seal();

        $throttle = $this->throttle();
        $open = fn (string $passphrase) => fn () => PackageReader::open($this->packagePath(), $passphrase, $this->weakKdf());

        for ($i = 0; $i < 5; $i++) {
            try {
                $throttle->attempt('pkg', 'ip', $open('wrong passphrase '.$i));
            } catch (WrongPassphraseException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->expectException(MigrationException::class);
        $throttle->attempt('pkg', 'ip', $open($this->passphrase));
    }
}
