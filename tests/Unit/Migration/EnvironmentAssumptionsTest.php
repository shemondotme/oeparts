<?php

namespace Tests\Unit\Migration;

use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Phase 1 spike for the .oeparts site-migration feature: every PHP/OS capability the package
 * format and the importer will rely on is proven here once, so a host that lacks one fails a
 * named test (or skips with a visible reason) instead of corrupting a migration later.
 */
class EnvironmentAssumptionsTest extends TestCase
{
    private function requireSodium(): void
    {
        if (! function_exists('sodium_crypto_pwhash')) {
            $this->markTestSkipped('ext-sodium (Argon2id) is not available on this PHP build: the .oeparts key derivation would fall back to PBKDF2 (see the plan, section 3.2).');
        }
    }

    #[Test]
    public function argon2id_is_available_and_deterministic_for_the_same_salt_only(): void
    {
        $this->requireSodium();

        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $derive = fn (string $pass, string $s): string => sodium_crypto_pwhash(
            32, $pass, $s, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, 64 * 1024 * 1024, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        $key = $derive('correct horse', $salt);

        $this->assertSame(32, strlen($key));
        $this->assertSame($key, $derive('correct horse', $salt));
        $this->assertNotSame($key, $derive('correct horse!', $salt), 'a different passphrase gives a different key');
        $this->assertNotSame($key, $derive('correct horse', random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES)), 'a different salt gives a different key');
    }

    #[Test]
    public function argon2id_with_64_mib_fits_a_256m_memory_limit(): void
    {
        $this->requireSodium();

        // The shared-hosting profile (docker/hostingsim/php.ini-overrides) caps memory_limit at 256M.
        $process = new Process([
            PHP_BINARY, '-d', 'memory_limit=256M', '-r',
            'sodium_crypto_pwhash(32, "pw", str_repeat("s", SODIUM_CRYPTO_PWHASH_SALTBYTES), SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, 64*1024*1024, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13); echo "ok";',
        ]);
        $process->run();

        $this->assertSame('ok', trim($process->getOutput()), 'Argon2id (64 MiB) must run under memory_limit=256M: '.$process->getErrorOutput());
    }

    #[Test]
    public function a_sha256_hash_context_survives_serialisation_and_continues_to_the_same_digest(): void
    {
        // Lets a resumable writer/reader persist "the hash so far" between requests instead of re-hashing.
        $context = hash_init('sha256');
        hash_update($context, 'part one, ');

        $restored = unserialize(serialize($context));
        hash_update($restored, 'part two');

        $this->assertSame(hash('sha256', 'part one, part two'), hash_final($restored));
    }

    #[Test]
    public function aes_256_gcm_binds_a_42_byte_aad_and_rejects_any_change_to_it_or_to_the_data(): void
    {
        $key = random_bytes(32);
        $iv = random_bytes(12);
        $aad = random_bytes(42); // package id + part name + sequence + total, as the format will bind them

        $ciphertext = openssl_encrypt('customer data', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad);

        $this->assertSame('customer data', openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad));

        $changedAad = $aad;
        $changedAad[41] = $changedAad[41] ^ "\x01";
        $this->assertFalse(openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $changedAad), 'one flipped AAD bit must fail');

        $changedData = $ciphertext;
        $changedData[0] = $changedData[0] ^ "\x01";
        $this->assertFalse(openssl_decrypt($changedData, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad), 'one flipped ciphertext bit must fail');
    }

    /**
     * THE TRAP this spike exists to pin. The file backup appends one gzip MEMBER per 1 MiB block to a
     * volume, so a stored segment is several members back to back. gzdecode() and a single
     * inflate_add() both stop at the end of the FIRST member and silently drop the rest.
     */
    #[Test]
    public function gzdecode_and_a_single_inflate_silently_drop_every_member_after_the_first(): void
    {
        $joined = gzencode('first member|').gzencode('second member');

        $this->assertSame('first member|', gzdecode($joined), 'gzdecode returns only the first member: this is why the reader must loop');

        $context = inflate_init(ZLIB_ENCODING_GZIP);
        $this->assertSame('first member|', inflate_add($context, $joined));
        $this->assertSame(ZLIB_STREAM_END, inflate_get_status($context));
        $this->assertLessThan(strlen($joined), inflate_get_read_len($context), 'part of the input was never read');
    }

    #[Test]
    public function every_member_of_a_concatenated_gzip_stream_is_recovered_by_restarting_at_the_read_length(): void
    {
        $members = ['first member|', str_repeat('x', 100000), '', 'last'];
        $joined = implode('', array_map('gzencode', $members));

        $decoded = '';
        $offset = 0;
        $guard = 0;
        while ($offset < strlen($joined)) {
            $this->assertLessThan(100, ++$guard, 'decoder made no progress');

            $context = inflate_init(ZLIB_ENCODING_GZIP);
            $decoded .= inflate_add($context, substr($joined, $offset));
            $this->assertSame(ZLIB_STREAM_END, inflate_get_status($context), 'a member ended mid-input without a stream end');

            $offset += inflate_get_read_len($context);
        }

        $this->assertSame(implode('', $members), $decoded);
    }

    #[Test]
    public function the_scheduler_is_fully_populated_once_the_console_routes_are_loaded(): void
    {
        // Phase 3 appends a gate loop at the END of routes/console.php; it only works if every event
        // registered above it is already visible there. The first and the last statements of the file:
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) $event->command)
            ->implode("\n");

        $this->assertStringContainsString('sitemap:generate', $commands);
        $this->assertStringContainsString('oeparts:admin-push:flush', $commands, 'the last event of routes/console.php');
        $this->assertGreaterThanOrEqual(20, count(app(Schedule::class)->events()));
    }
}
