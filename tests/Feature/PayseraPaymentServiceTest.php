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
 * Mirrors PaymentServiceEnvironmentTest's coverage for Airwallex, but for
 * Paysera's Checkout Modern integration: OAuth2 client-credentials token
 * exchange, the two-step order -> payment-link flow, and token caching.
 */
class PayseraPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://api.paysera.com/auth/realms/Paysera/protocol/openid-connect/token';

    private const ORDERS_URL = 'https://api.paysera.com/merchant-order/integration/v1/orders';

    private const LINKS_URL = 'https://api.paysera.com/checkout-payment-link/integration/v1/payment-links';

    private function setPayseraSettings(): void
    {
        foreach ([
            'paysera_environment' => 'sandbox',
            'paysera_client_id' => 'test_client_id',
            'paysera_client_secret' => 'test_client_secret',
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'payment', 'key' => $key],
                ['value' => $value, 'type' => SettingType::String],
            );
        }
    }

    private function fakePayseraFlow(): void
    {
        Http::fake([
            self::TOKEN_URL => Http::response([
                'access_token' => 'fake_bearer_token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            self::ORDERS_URL => Http::response([
                'order_id' => 'order-uuid-123',
                'project_id' => 'project-uuid-456',
                'purchase' => ['reference' => 'ORD-1', 'amount' => 2500, 'currency' => 'EUR'],
            ], 201),
            self::LINKS_URL => Http::response([
                'link_id' => 'link-uuid-789',
                'order_id' => 'order-uuid-123',
                'payment_URL' => 'https://api.paysera.com/checkout-payment-link/payment-collection/v1/payment-links/abc123',
            ], 201),
        ]);
    }

    #[Test]
    public function it_exchanges_credentials_for_a_bearer_token_before_creating_the_order(): void
    {
        $this->setPayseraSettings();
        $this->fakePayseraFlow();

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createPayseraPaymentLink($order);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::TOKEN_URL) {
                return true;
            }

            return $request->method() === 'POST'
                && $request['client_id'] === 'test_client_id'
                && $request['client_secret'] === 'test_client_secret'
                && $request['grant_type'] === 'client_credentials';
        });

        Http::assertSent(function ($request) {
            if ($request->url() !== self::ORDERS_URL) {
                return true;
            }

            // Must use the TOKEN returned by the auth endpoint, never the raw secret.
            return $request->header('Authorization')[0] === 'Bearer fake_bearer_token';
        });
    }

    #[Test]
    public function it_creates_an_order_then_a_payment_link_and_returns_the_payment_url(): void
    {
        $this->setPayseraSettings();
        $this->fakePayseraFlow();

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        $result = app(PaymentService::class)->createPayseraPaymentLink($order);

        $this->assertSame('https://api.paysera.com/checkout-payment-link/payment-collection/v1/payment-links/abc123', $result['payment_url']);
        $this->assertSame('order-uuid-123', $result['order_id']);
        $this->assertNotNull($result['payment_id']);

        $this->assertDatabaseHas('payments', [
            'id' => $result['payment_id'],
            'order_id' => $order->id,
            'gateway' => 'paysera',
            'transaction_id' => 'order-uuid-123',
            'status' => 'pending',
        ]);

        Http::assertSent(function ($request) {
            if ($request->url() !== self::LINKS_URL) {
                return true;
            }

            return $request['order_id'] === 'order-uuid-123';
        });
    }

    #[Test]
    public function the_auth_token_is_cached_and_not_refetched_on_a_second_payment_link(): void
    {
        $this->setPayseraSettings();
        $this->fakePayseraFlow();

        $orderOne = Order::factory()->create(['guest_email' => 'buyer1@example.com', 'user_id' => null]);
        $orderTwo = Order::factory()->create(['guest_email' => 'buyer2@example.com', 'user_id' => null]);

        app(PaymentService::class)->createPayseraPaymentLink($orderOne);
        app(PaymentService::class)->createPayseraPaymentLink($orderTwo);

        // One token exchange + (order create + link create) x2, not two token exchanges.
        Http::assertSentCount(5);
    }

    #[Test]
    public function the_order_identifier_is_accepted_under_id_as_well_as_order_id(): void
    {
        // Paysera's Payment Orders guide lists the response identifier as `id`
        // (UUID) while its PHP sample reads $order['order_id'] — accept both.
        $this->setPayseraSettings();
        Http::fake([
            self::TOKEN_URL => Http::response(['access_token' => 'fake_bearer_token', 'expires_in' => 3600], 200),
            self::ORDERS_URL => Http::response(['id' => 'order-uuid-from-id', 'amount' => 2500, 'currency' => 'EUR', 'status' => 'pending_payment'], 201),
            self::LINKS_URL => Http::response([
                'link_id' => 'link-1', 'order_id' => 'order-uuid-from-id',
                'payment_URL' => 'https://api.paysera.com/checkout-payment-link/payment-collection/v1/payment-links/xyz',
            ], 201),
        ]);

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        $result = app(PaymentService::class)->createPayseraPaymentLink($order);

        $this->assertSame('order-uuid-from-id', $result['order_id']);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'gateway' => 'paysera', 'transaction_id' => 'order-uuid-from-id']);
        Http::assertSent(fn ($request) => $request->url() !== self::LINKS_URL || $request['order_id'] === 'order-uuid-from-id');
    }

    #[Test]
    public function the_order_request_matches_the_documented_shape_with_integer_minor_units(): void
    {
        $this->setPayseraSettings();
        $this->fakePayseraFlow();

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null, 'grand_total' => 108.87]);

        app(PaymentService::class)->createPayseraPaymentLink($order);

        $recorded = Http::recorded(fn ($request) => $request->url() === self::ORDERS_URL);
        $body = $recorded->first()[0]->data();

        // Payment Orders guide: purchase.amount is an INTEGER in minor units (2500 = EUR 25.00).
        $this->assertSame(10887, $body['purchase']['amount']);
        $this->assertSame($order->order_number, $body['purchase']['reference']);
        $this->assertSame('EUR', $body['purchase']['currency']);
        $this->assertSame(route('webhooks.paysera'), $body['redirect_urls']['callback_url']);

        $link = Http::recorded(fn ($request) => $request->url() === self::LINKS_URL)->first()[0]->data();
        $this->assertSame(10887, $link['purchase']['amount']);
    }

    #[Test]
    public function an_unsupported_site_locale_falls_back_to_english_for_the_required_language_field(): void
    {
        // experience.language is REQUIRED and Paysera only documents "en"/"lt".
        // The site also serves de/fr/es — an unrecognised code risks a 422 that
        // blocks the whole payment, English merely shows the hosted page in English.
        $this->setPayseraSettings();

        foreach (['de' => 'en', 'fr' => 'en', 'es' => 'en', 'lt' => 'lt', 'en' => 'en'] as $locale => $expected) {
            Http::fake([
                self::TOKEN_URL => Http::response(['access_token' => 'fake_bearer_token', 'expires_in' => 3600], 200),
                self::ORDERS_URL => Http::response(['order_id' => 'order-uuid-'.$locale], 201),
                self::LINKS_URL => Http::response(['link_id' => 'l', 'order_id' => 'order-uuid-'.$locale, 'payment_URL' => 'https://api.paysera.com/x'], 201),
            ]);
            app()->setLocale($locale);

            $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);
            app(PaymentService::class)->createPayseraPaymentLink($order);

            $link = Http::recorded(fn ($request) => $request->url() === self::LINKS_URL)->last()[0]->data();
            $this->assertSame($expected, $link['experience']['language'], "locale {$locale}");
        }
    }

    #[Test]
    public function missing_credentials_throw_before_any_http_call(): void
    {
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        Http::fake();

        $this->expectException(\RuntimeException::class);

        app(PaymentService::class)->createPayseraPaymentLink($order);

        Http::assertNothingSent();
    }

    protected function tearDown(): void
    {
        // Never Cache::flush() (rule #5) — forget only the specific keys
        // these tests populate, so the auth-token cache can't leak stale
        // fake tokens into a later, unrelated test.
        Cache::forget('paysera_auth_token:'.md5('test_client_idtest_client_secret'));
        parent::tearDown();
    }
}
