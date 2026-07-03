<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Recovery Console security hardening (Module 21, Chunk 4.3): rate-limiting,
 * structured audit logging, POST-only confirm tokens (IP-bound + expiring), and
 * explicit disarm. Driven directly against the framework-free OeRecoveryConsole
 * with an injected clock for deterministic lockout/expiry timing.
 */
class RecoveryConsoleSecurityTest extends TestCase
{
    private string $base;

    private string $state;

    protected function setUp(): void
    {
        parent::setUp();

        require_once base_path('public/oe-recovery.php');

        $this->base  = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-recov-sec-'.getmypid();
        $this->state = $this->base.'/storage/app/updates';
        @mkdir($this->state, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function console(array $env = [], ?int &$now = null): \OeRecoveryConsole
    {
        $c = new \OeRecoveryConsole($this->base, array_merge(['OE_RECOVERY_KEY' => 'correct-key'], $env), $this->state);
        if ($now !== null) {
            $c->setClock(function () use (&$now) {
                return $now;
            });
        }

        return $c;
    }

    private function arm(): void
    {
        file_put_contents($this->state.'/arm.flag', json_encode(['to_version' => '1.1.0']));
    }

    #[Test]
    public function it_rate_limits_and_then_unblocks_after_the_lockout_window(): void
    {
        $this->arm();
        $now = 1000;
        $console = $this->console(['OE_RECOVERY_MAX_ATTEMPTS' => 3, 'OE_RECOVERY_LOCKOUT_SECONDS' => 60], $now);

        // Three wrong keys → still just rejected (403 login), not yet blocked.
        for ($i = 0; $i < 3; $i++) {
            [$status, $state] = $console->handle('wrong', '198.51.100.7');
            $this->assertSame(403, $status);
            $this->assertSame(\OeRecoveryConsole::STATE_LOGIN, $state);
        }

        // Now locked out — even the CORRECT key is refused.
        [$status, $state] = $console->handle('correct-key', '198.51.100.7');
        $this->assertSame(429, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_BLOCKED, $state);

        // After the lockout window elapses, the correct key works again.
        $now += 61;
        [$status, $state] = $console->handle('correct-key', '198.51.100.7');
        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_READY, $state);
    }

    #[Test]
    public function it_writes_a_structured_audit_log(): void
    {
        $this->arm();
        $console = $this->console();

        $console->handle('wrong', '203.0.113.5');       // auth_fail
        $console->handle('correct-key', '203.0.113.5'); // auth_success

        $log = file_get_contents($console->logFile());
        $this->assertStringContainsString('"event":"auth_fail"', $log);
        $this->assertStringContainsString('"event":"auth_success"', $log);
        $this->assertStringContainsString('"ip":"203.0.113.5"', $log);
    }

    #[Test]
    public function a_confirm_token_authorises_actions_without_resending_the_key(): void
    {
        $this->arm();
        $console = $this->console();

        $token = $console->mintToken('192.0.2.9');

        // Action with a valid token + NO key → runs.
        [$status, $state, $html] = $console->handle(null, '192.0.2.9', 'opcache_reset', $token);
        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_READY, $state);
        $this->assertStringContainsString('class="banner ok"', $html);

        // Action with a bogus token + no key → denied.
        [$status, $state] = $console->handle(null, '192.0.2.9', 'opcache_reset', 'not-a-real-token');
        $this->assertSame(403, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_LOGIN, $state);
    }

    #[Test]
    public function tokens_are_ip_bound_and_expire(): void
    {
        $now = 5000;
        $console = $this->console(['OE_RECOVERY_TOKEN_TTL' => 100], $now);

        $token = $console->mintToken('10.1.1.1');

        $this->assertTrue($console->validateToken($token, '10.1.1.1'));
        $this->assertFalse($console->validateToken($token, '10.1.1.2'), 'token is bound to the minting IP');

        $now += 101; // past the TTL
        $this->assertFalse($console->validateToken($token, '10.1.1.1'), 'token expires');
    }

    #[Test]
    public function the_disarm_action_closes_the_window_and_locks_the_console(): void
    {
        $this->arm();
        $console = $this->console();
        $token = $console->mintToken('127.0.0.1');

        [$status, $state] = $console->handle(null, '127.0.0.1', 'disarm', $token);

        $this->assertSame(200, $status);
        $this->assertSame(\OeRecoveryConsole::STATE_UNARMED, $state);
        $this->assertFalse($console->isArmed(), 'the arm flag is removed');
        $this->assertFileDoesNotExist($console->sessionFile(), 'the session token is cleared');
    }

    #[Test]
    public function the_dashboard_carries_a_token_not_the_raw_key(): void
    {
        $this->arm();
        $console = $this->console(['OE_RECOVERY_KEY' => 'super-secret-value']);

        [, , $html] = $console->handle('super-secret-value', '127.0.0.1');

        $this->assertStringContainsString('name="token"', $html);
        $this->assertStringNotContainsString('super-secret-value', $html, 'the raw key never appears in the DOM');
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') {
                continue;
            }
            $p = $dir.'/'.$e;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
