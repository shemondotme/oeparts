<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\StoreOperationsSettings;
use App\Jobs\SendAdminWebPush;
use App\Models\Admin;
use App\Models\AdminPushDelivery;
use App\Models\AdminPushSubscription;
use App\Models\Setting;
use App\Services\HealthCheckService;
use App\Services\Push\VapidKeys;
use App\Services\Push\WebPushClient;
use App\Services\SettingsService;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * A site restored or migrated onto a server with a DIFFERENT APP_KEY keeps its
 * encrypted settings rows (SMTP password, gateway keys, push keys...) but can no
 * longer read them. Before this fix SettingsService handed the raw ciphertext
 * back as if it were the credential, settings forms displayed it and re-encrypted
 * it on save, and the push keys would have been regenerated. These tests simulate
 * the situation by storing values encrypted under an unrelated key.
 */
class SettingsUndecryptableSecretsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class, SettingsSeeder::class]);
    }

    /** Ciphertext this server's APP_KEY cannot open. */
    private function foreignCiphertext(string $plain): string
    {
        return (new Encrypter(random_bytes(32), 'aes-256-cbc'))->encryptString($plain);
    }

    private function storeSecret(string $group, string $key, string $stored): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $stored, 'is_encrypted' => true],
        );

        app(SettingsService::class)->forget($group);
    }

    #[Test]
    public function an_unreadable_secret_is_empty_never_the_ciphertext_and_readable_ones_still_work(): void
    {
        $foreign = $this->foreignCiphertext('old-server-password');
        $this->storeSecret('email', 'smtp_password', $foreign);
        $this->storeSecret('payment', 'airwallex_api_key', Crypt::encryptString('readable-key'));

        $settings = app(SettingsService::class);

        $this->assertSame('', $settings->get('email.smtp_password'));
        $this->assertNotSame($foreign, $settings->get('email.smtp_password'));
        $this->assertSame('readable-key', $settings->get('payment.airwallex_api_key'));
    }

    #[Test]
    public function the_unreadable_secrets_are_listed_and_the_list_clears_when_the_secret_is_saved_again(): void
    {
        $this->storeSecret('email', 'smtp_password', $this->foreignCiphertext('x'));
        $this->storeSecret('payment', 'airwallex_api_key', Crypt::encryptString('fine'));

        $settings = app(SettingsService::class);

        $this->assertSame(['email.smtp_password'], $settings->undecryptableSecrets());

        $settings->set('email.smtp_password', 'typed-again');

        $this->assertSame([], $settings->undecryptableSecrets());
        $this->assertSame('typed-again', $settings->get('email.smtp_password'));
    }

    #[Test]
    public function an_empty_encrypted_row_is_not_reported_as_unreadable(): void
    {
        // The seeder creates every encrypted key with an empty value; that is "never configured", not "broken".
        $this->assertSame([], app(SettingsService::class)->undecryptableSecrets());
    }

    #[Test]
    public function the_settings_page_shows_the_field_empty_and_saving_does_not_double_encrypt(): void
    {
        $foreign = $this->foreignCiphertext('old-server-password');
        $this->storeSecret('payment', 'airwallex_api_key', $foreign);

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $component = Livewire::test(StoreOperationsSettings::class);

        $this->assertSame('', $component->get('data.airwallex_api_key'));

        // Save with an unrelated edit: the unreadable secret must come out blank, not as ciphertext-of-ciphertext.
        $component->set('data.bank_account_holder', 'Changed Holder UAB')->call('save');

        $row = Setting::where('group', 'payment')->where('key', 'airwallex_api_key')->first();
        $this->assertTrue((bool) $row->is_encrypted);
        $this->assertTrue($row->value === '' || $row->value === null, 'the unreadable secret is cleared, not re-encrypted');
        $this->assertNotSame($foreign, $row->value);
    }

    #[Test]
    public function a_readable_secret_still_round_trips_through_the_settings_page(): void
    {
        $this->storeSecret('payment', 'airwallex_api_key', Crypt::encryptString('keep-me'));

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $component = Livewire::test(StoreOperationsSettings::class);
        $this->assertSame('keep-me', $component->get('data.airwallex_api_key'));

        $component->set('data.bank_account_holder', 'Another Holder UAB')->call('save');

        $this->assertSame('keep-me', app(SettingsService::class)->get('payment.airwallex_api_key'));
    }

    #[Test]
    public function unreadable_push_keys_are_never_regenerated_and_push_stays_off(): void
    {
        $foreignPublic = $this->foreignCiphertext('PUBLIC');
        $foreignPrivate = $this->foreignCiphertext('PRIVATE');
        $this->storeSecret('push', 'vapid_public_key', $foreignPublic);
        $this->storeSecret('push', 'vapid_private_key', $foreignPrivate);

        $vapid = app(VapidKeys::class);

        $this->assertFalse($vapid->isUsable());
        $this->assertSame('', $vapid->publicKey());

        // The stored rows are untouched: regenerating would orphan every browser subscription.
        $this->assertSame($foreignPublic, Setting::where('group', 'push')->where('key', 'vapid_public_key')->value('value'));
        $this->assertSame($foreignPrivate, Setting::where('group', 'push')->where('key', 'vapid_private_key')->value('value'));
    }

    #[Test]
    public function the_push_job_leaves_subscriptions_alone_while_the_keys_are_unreadable(): void
    {
        $this->storeSecret('push', 'vapid_public_key', $this->foreignCiphertext('PUBLIC'));
        $this->storeSecret('push', 'vapid_private_key', $this->foreignCiphertext('PRIVATE'));

        $admin = Admin::factory()->create(['is_active' => true]);
        $endpoint = 'https://push.example.test/abc';
        $subscription = AdminPushSubscription::create([
            'admin_id' => $admin->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => AdminPushSubscription::hashEndpoint($endpoint),
            'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
            'auth_token' => 'tBHItJI5svbpez7KI4CCXg',
            'content_encoding' => 'aes128gcm',
            'failure_count' => 4,
        ]);

        // A client that would report a failure for everything: if the job still called it,
        // the failure counter would climb and the subscription would be deleted.
        $this->app->instance(WebPushClient::class, new class extends WebPushClient
        {
            public function __construct() {}

            public function send(Collection $subscriptions, array $payload, bool $urgent): array
            {
                return $subscriptions->mapWithKeys(fn ($s) => [$s->id => ['ok' => false, 'expired' => false, 'status' => 500, 'error' => 'boom']])->all();
            }
        });

        SendAdminWebPush::dispatchSync($admin->id, ['topic' => 'new_order', 'title' => 'T']);

        $subscription->refresh();
        $this->assertSame(4, $subscription->failure_count);
        $this->assertSame(0, AdminPushDelivery::count());
    }

    #[Test]
    public function the_secrets_health_check_names_what_needs_attention_and_is_not_an_outage(): void
    {
        $health = app(HealthCheckService::class);

        $this->assertSame('ok', $health->checkSecrets()['status']);

        $this->storeSecret('email', 'smtp_password', $this->foreignCiphertext('x'));
        Cache::forget('settings.undecryptable');

        $check = $health->checkSecrets();
        $this->assertSame('attention', $check['status']);
        $this->assertStringContainsString('email.smtp_password', $check['detail']);

        $all = $health->runAll();
        $this->assertArrayHasKey('secrets', $all['checks']);
        $this->assertNotSame('degraded', $all['status'], 'an unreadable secret is a notice, not a system fault');
    }

    #[Test]
    public function only_the_encrypted_value_helper_decrypts_settings(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            if ($file->getFilename() === 'EncryptedSettingValue.php') {
                continue;
            }

            if (preg_match('/Crypt::decrypt(String)?\(|\bdecrypt\(|->decryptString\(/', $file->getContents())) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'Setting values must be decrypted through App\\Support\\EncryptedSettingValue (an unreadable value must come back empty, never as ciphertext).');
    }
}
