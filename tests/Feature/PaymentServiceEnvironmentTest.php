<?php

namespace Tests\Feature;

use App\Enums\SettingType;
use App\Models\Order;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression tests for two real bugs found while preparing to live-test
 * Airwallex checkout with real sandbox credentials:
 *
 * 1. The Payment Settings admin form stored 'production' for live mode, but
 *    PaymentService/CheckoutController only ever checked for 'live' — so
 *    selecting "Production (Live Mode)" in Settings silently never switched
 *    the actual checkout off the sandbox API.
 * 2. createAirwallexIntent() sent the raw API key as
 *    "Authorization: Bearer {$apiKey}" — Airwallex requires exchanging the
 *    Client ID / API Key for a short-lived token via POST
 *    /authentication/login first (confirmed against the real sandbox API: a
 *    valid key/id pair returns 201 + {token, expires_at} from /login, and
 *    the raw key is never itself a valid bearer token). Every payment
 *    intent creation would have failed with the old code. Zero test
 *    coverage existed for either bug before.
 */
class PaymentServiceEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private function setAirwallexSettings(string $environment): void
    {
        foreach ([
            'airwallex_environment' => $environment,
            'airwallex_client_id'   => 'test_client_id',
            'airwallex_api_key'     => 'test_api_key',
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'payment', 'key' => $key],
                ['value' => $value, 'type' => SettingType::String],
            );
        }
    }

    private function fakeAirwallexLoginAndIntent(string $host): void
    {
        Http::fake([
            "{$host}/api/v1/authentication/login" => Http::response(['token' => 'fake_bearer_token', 'expires_at' => now()->addMinutes(30)->toIso8601String()], 201),
            "{$host}/api/v1/pa/payment_intents/create" => Http::response(['id' => 'int_test', 'client_secret' => 'secret'], 200),
        ]);
    }

    #[Test]
    public function the_live_mode_setting_value_actually_hits_the_live_airwallex_api(): void
    {
        $this->setAirwallexSettings('live');
        $this->fakeAirwallexLoginAndIntent('api.airwallex.com');

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($order);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.airwallex.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'api-demo.airwallex.com'));
    }

    #[Test]
    public function the_sandbox_mode_setting_value_hits_the_sandbox_airwallex_api(): void
    {
        $this->setAirwallexSettings('sandbox');
        $this->fakeAirwallexLoginAndIntent('api-demo.airwallex.com');

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($order);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api-demo.airwallex.com'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'https://api.airwallex.com'));
    }

    #[Test]
    public function it_exchanges_the_api_key_for_a_bearer_token_before_creating_the_payment_intent(): void
    {
        $this->setAirwallexSettings('sandbox');
        $this->fakeAirwallexLoginAndIntent('api-demo.airwallex.com');

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($order);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'authentication/login')) {
                return true; // not the call we're checking here
            }

            return $request->method() === 'POST'
                && $request->header('x-client-id')[0] === 'test_client_id'
                && $request->header('x-api-key')[0] === 'test_api_key';
        });

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'payment_intents/create')) {
                return true;
            }

            // Must use the TOKEN returned by /login, never the raw API key.
            return $request->header('Authorization')[0] === 'Bearer fake_bearer_token';
        });
    }

    #[Test]
    public function the_auth_token_is_cached_and_not_refetched_on_a_second_intent(): void
    {
        $this->setAirwallexSettings('sandbox');
        $this->fakeAirwallexLoginAndIntent('api-demo.airwallex.com');

        $orderOne = Order::factory()->create(['guest_email' => 'buyer1@example.com', 'user_id' => null]);
        $orderTwo = Order::factory()->create(['guest_email' => 'buyer2@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($orderOne);
        app(PaymentService::class)->createAirwallexIntent($orderTwo);

        Http::assertSentCount(3); // one login + two payment_intents/create, not two logins
    }

    protected function tearDown(): void
    {
        // Never Cache::flush() (rule #5) — forget only the specific keys
        // these tests populate, so the auth-token cache can't leak stale
        // fake tokens into a later, unrelated test.
        foreach (['api.airwallex.com', 'api-demo.airwallex.com'] as $host) {
            Cache::forget('airwallex_auth_token:' . md5("https://{$host}/api/v1" . 'test_client_id'));
        }
        parent::tearDown();
    }
}
