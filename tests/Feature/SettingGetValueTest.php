<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 14 (Monitoring/Logging Audit). Setting::getValue()'s decrypt catch
 * was completely empty — on a decrypt failure (corrupted ciphertext, a
 * rotated APP_KEY) it fell through to `return $value`, which at that point
 * still held the RAW, undecrypted ciphertext, not null/$default. Since this
 * helper exists specifically for encrypted settings (API keys/secrets), a
 * caller could silently receive garbled ciphertext instead of a clean
 * failure signal, with nothing logged anywhere to explain why. Confirmed
 * dead code today (zero callers anywhere in app/), but it's a public static
 * method on the core Setting model — this pins the correct contract before
 * anything starts calling it again.
 */
class SettingGetValueTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_decrypted_value_for_an_encrypted_setting(): void
    {
        Setting::create([
            'group' => 'integrations', 'key' => 'secret_test',
            'value' => Crypt::encryptString('super-secret-value'), 'is_encrypted' => true,
        ]);

        $this->assertSame('super-secret-value', Setting::getValue('integrations.secret_test'));
    }

    #[Test]
    public function it_returns_the_default_and_logs_a_warning_when_decryption_fails(): void
    {
        Log::spy();

        Setting::create([
            'group' => 'integrations', 'key' => 'corrupted_secret',
            'value' => 'not-a-real-ciphertext', 'is_encrypted' => true,
        ]);

        $result = Setting::getValue('integrations.corrupted_secret', 'fallback');

        $this->assertSame('fallback', $result);
        Log::shouldHaveReceived('warning')->once()
            ->withArgs(fn ($message) => str_contains($message, 'integrations.corrupted_secret'));
    }

    #[Test]
    public function it_returns_the_default_for_a_missing_key(): void
    {
        $this->assertSame('fallback', Setting::getValue('integrations.does_not_exist', 'fallback'));
    }

    #[Test]
    public function it_returns_the_default_for_a_malformed_dot_key(): void
    {
        $this->assertNull(Setting::getValue('no-dot-here'));
    }
}
