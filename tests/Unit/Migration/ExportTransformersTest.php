<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Export\Transform\CredentialScrubber;
use App\Services\Migration\Export\Transform\DanglingFkNullifier;
use App\Services\Migration\Export\Transform\MaintenanceNeutraliser;
use App\Services\Migration\Export\Transform\SequenceResetTransformer;
use App\Services\Migration\Export\Transform\SettingsSecretsTransformer;
use App\Services\Migration\Export\Transform\TransformPipeline;
use Database\Seeders\SettingsSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 3.8: the rules rows pass through on their way into a package.
 */
class ExportTransformersTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function setting(string $group, string $key, string $value, string $type = 'string', int $encrypted = 0): array
    {
        return ['id' => 1, 'group' => $group, 'key' => $key, 'value' => $value, 'type' => $type, 'is_encrypted' => $encrypted];
    }

    // ---- secrets: left out -------------------------------------------------------------------

    #[Test]
    public function a_secret_left_out_is_blanked_and_its_key_is_listed_for_re_entry(): void
    {
        $transformer = new SettingsSecretsTransformer(false);
        $row = $this->setting('email', 'smtp_password', Crypt::encryptString('hunter2'), 'encrypted', 1);

        $out = $transformer->transform('settings', $row);

        $this->assertSame('', $out['value']);
        $this->assertSame(1, $out['is_encrypted'], 'the row stays so the target knows the setting exists');
        $this->assertSame('encrypted', $out['type']);
        $this->assertSame(['email.smtp_password'], $transformer->report()['configured_keys']);
        $this->assertStringNotContainsString('hunter2', json_encode($out));
    }

    #[Test]
    public function leaving_secrets_out_never_decrypts_anything(): void
    {
        Crypt::shouldReceive('decryptString')->never();
        Log::spy();

        $transformer = new SettingsSecretsTransformer(false);
        $out = $transformer->transform('settings', $this->setting('payment', 'airwallex_api_key', 'this-is-not-even-ciphertext', 'encrypted', 1));

        $this->assertSame('', $out['value']);
        $this->assertSame(['payment.airwallex_api_key'], $transformer->report()['configured_keys'], 'an unreadable secret is still a configured one');
        Log::shouldNotHaveReceived('warning');
    }

    #[Test]
    public function a_secret_that_was_never_set_is_neither_hidden_nor_listed(): void
    {
        $transformer = new SettingsSecretsTransformer(false);
        $row = $this->setting('email', 'smtp_password', '', 'encrypted', 1);

        $this->assertSame($row, $transformer->transform('settings', $row));
        $this->assertSame([], $transformer->report()['configured_keys']);
    }

    #[Test]
    public function a_flag_or_a_type_either_one_makes_a_row_secret_and_ordinary_rows_pass_untouched(): void
    {
        $transformer = new SettingsSecretsTransformer(false);
        $cipher = Crypt::encryptString('x');

        $flagOnly = $transformer->transform('settings', $this->setting('push', 'vapid_private_key', $cipher, 'string', 1));
        $typeOnly = $transformer->transform('settings', $this->setting('x', 'y', $cipher, 'encrypted', 0));
        $plain = $this->setting('ui', 'account_new_password', '{"en":"New password"}', 'json', 0);

        $this->assertSame('', $flagOnly['value']);
        $this->assertSame('', $typeOnly['value']);
        $this->assertSame($plain, $transformer->transform('settings', $plain), 'a UI label that mentions a password is not a secret');
    }

    // ---- secrets: included ----------------------------------------------------------------------

    #[Test]
    public function an_included_secret_goes_only_to_the_secrets_sink_and_the_row_stays_blank(): void
    {
        $sunk = [];
        $transformer = new SettingsSecretsTransformer(true, function (array $secret) use (&$sunk): void {
            $sunk[] = $secret;
        });

        $out = $transformer->transform('settings', $this->setting('email', 'smtp_password', Crypt::encryptString('hunter2'), 'encrypted', 1));

        $this->assertSame('', $out['value']);
        $this->assertSame([['group' => 'email', 'key' => 'smtp_password', 'value' => 'hunter2']], $sunk);
        $this->assertSame(['email.smtp_password'], $transformer->report()['included_keys']);
        $this->assertSame([], $transformer->report()['configured_keys']);
    }

    #[Test]
    public function an_included_secret_that_cannot_be_read_is_blanked_and_reported_and_its_ciphertext_never_leaves(): void
    {
        $other = new Encrypter(random_bytes(32), 'aes-256-cbc');
        $foreignCipher = $other->encryptString('belongs to another site');
        $sunk = [];
        $transformer = new SettingsSecretsTransformer(true, function (array $secret) use (&$sunk): void {
            $sunk[] = $secret;
        });

        $out = $transformer->transform('settings', $this->setting('payment', 'paysera_client_secret', $foreignCipher, 'encrypted', 1));

        $this->assertSame('', $out['value']);
        $this->assertSame([], $sunk);
        $this->assertSame(['payment.paysera_client_secret'], $transformer->report()['undecryptable_keys']);
        $this->assertStringNotContainsString($foreignCipher, json_encode($out));
    }

    #[Test]
    public function a_secret_written_under_a_previous_app_key_is_still_read_when_that_key_is_listed(): void
    {
        $oldRaw = random_bytes(32);
        $newRaw = random_bytes(32);
        $cipher = (new Encrypter($oldRaw, config('app.cipher')))->encryptString('old-key-secret');

        config(['app.key' => 'base64:'.base64_encode($newRaw), 'app.previous_keys' => ['base64:'.base64_encode($oldRaw)]]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');

        $sunk = [];
        $transformer = new SettingsSecretsTransformer(true, function (array $secret) use (&$sunk): void {
            $sunk[] = $secret['value'];
        });
        $transformer->transform('settings', $this->setting('email', 'smtp_password', $cipher, 'encrypted', 1));

        $this->assertSame(['old-key-secret'], $sunk);
    }

    #[Test]
    public function including_secrets_without_a_place_to_put_them_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SettingsSecretsTransformer(true);
    }

    // ---- the small rules -----------------------------------------------------------------------

    #[Test]
    public function maintenance_is_always_exported_switched_off(): void
    {
        $rule = new MaintenanceNeutraliser;

        $this->assertSame('0', $rule->transform('settings', $this->setting('maintenance', 'enabled', '1', 'boolean'))['value']);
        $message = $this->setting('maintenance', 'message', '{"en":"Back soon"}', 'json');
        $this->assertSame($message, $rule->transform('settings', $message));
        $other = $this->setting('general', 'enabled', '1');
        $this->assertSame($other, $rule->transform('settings', $other), 'only maintenance.enabled');
    }

    #[Test]
    public function the_nullifier_cuts_only_the_columns_it_was_given_for_that_table(): void
    {
        $rule = new DanglingFkNullifier(['orders' => ['user_id', 'carrier_id'], 'coupons' => ['user_id']]);

        $this->assertSame(['orders', 'coupons'], $rule->tables());

        $order = ['id' => 5, 'user_id' => 9, 'carrier_id' => 2, 'shipping_method_id' => 3];
        $this->assertSame(['id' => 5, 'user_id' => null, 'carrier_id' => null, 'shipping_method_id' => 3], $rule->transform('orders', $order));
        $this->assertSame(['id' => 1, 'user_id' => null], $rule->transform('coupons', ['id' => 1, 'user_id' => 4]));
        $this->assertSame(['id' => 8], $rule->transform('orders', ['id' => 8]), 'a column the row does not have is not invented');
    }

    #[Test]
    public function remember_me_tokens_are_dropped_but_password_hashes_stay(): void
    {
        $rule = new CredentialScrubber;
        $row = ['id' => 1, 'email' => 'a@example.test', 'password' => '$2y$hash', 'remember_token' => 'abc'];

        $this->assertSame(['id' => 1, 'email' => 'a@example.test', 'password' => '$2y$hash', 'remember_token' => null], $rule->transform('users', $row));
        $this->assertSame(['admins', 'users'], $rule->tables());
    }

    #[Test]
    public function sequences_start_again_from_zero_for_a_new_store(): void
    {
        $row = ['id' => 1, 'type' => 'order', 'current_value' => 16, 'resets_monthly' => 1, 'last_reset_month' => '2026-10'];

        $out = (new SequenceResetTransformer)->transform('sequences', $row);

        $this->assertSame(0, $out['current_value']);
        $this->assertNull($out['last_reset_month']);
        $this->assertSame('order', $out['type']);
        $this->assertSame(1, $out['resets_monthly']);
    }

    // ---- the pipeline --------------------------------------------------------------------------

    #[Test]
    public function a_table_with_no_rule_costs_nothing_and_a_table_with_rules_runs_them_in_order(): void
    {
        $pipeline = new TransformPipeline([
            new SettingsSecretsTransformer(false),
            new MaintenanceNeutraliser,
            new CredentialScrubber,
        ]);

        $this->assertNull($pipeline->forTable('products'), 'no rule: the caller skips the call');
        $this->assertSame(['admins', 'settings', 'users'], $pipeline->tablesWithRules());

        $run = $pipeline->forTable('settings');
        $row = $this->setting('maintenance', 'enabled', '1', 'boolean');
        $this->assertSame('0', $run($row)['value']);

        $secret = $this->setting('email', 'smtp_password', Crypt::encryptString('x'), 'encrypted', 1);
        $this->assertSame('', $run($secret)['value']);
    }

    // ---- the guard: every secret-looking seeded setting is encrypted -----------------------------

    #[Test]
    public function every_seeded_setting_that_looks_like_a_secret_is_flagged_encrypted(): void
    {
        $this->seed(SettingsSeeder::class);

        $unflagged = DB::table('settings')
            ->where('group', '<>', 'ui')
            ->whereNotIn('type', ['integer', 'boolean'])
            ->where('is_encrypted', 0)
            ->where('type', '<>', 'encrypted')
            ->pluck('key', 'id')
            ->filter(fn (string $key): bool => preg_match('/(secret|password|token|_key|api_)/i', $key) === 1)
            ->values()
            ->all();

        $this->assertSame([], $unflagged, 'these settings look like secrets but would travel in the clear: flag them encrypted in SettingsSeeder');
    }
}
