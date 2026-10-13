<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SettingType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Instance\InstanceModeBlockedException;
use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Support\CheckoutPaymentMethods;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.5: a copy of the shop cannot move real money. Every blocked path is proven with Http::assertNothingSent
 * (a refusal that still made the call would be no refusal), and every allowed path is proven to reach the SANDBOX
 * host and never the live one.
 */
class PaymentSafeModeTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX = 'https://api-demo.airwallex.com/api/v1';

    private const LIVE = 'https://api.airwallex.com/api/v1';

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
        Cache::flush();
        $path = InstanceState::file()->path();
        $this->ledgerBackup = is_file($path) ? (string) file_get_contents($path) : '';
        @unlink($path);
        @unlink($path.'.prev');
    }

    protected function tearDown(): void
    {
        $path = InstanceState::file()->path();
        @unlink($path);
        @unlink($path.'.prev');
        if ($this->ledgerBackup !== '') {
            file_put_contents($path, $this->ledgerBackup);
        }

        parent::tearDown();
    }

    private function mode(string $mode): void
    {
        config(['instance.mode' => $mode]);
    }

    private function store(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }
        app(SettingsService::class)->forget('payment');
    }

    private function airwallex(string $environment = 'sandbox'): void
    {
        $this->store(['airwallex_environment' => $environment, 'airwallex_client_id' => 'cid', 'airwallex_api_key' => 'key']);
    }

    private function fakeAirwallex(): void
    {
        Http::fake([
            '*/authentication/login' => Http::response(['token' => 'tok'], 201),
            '*/pa/payment_intents/create' => Http::response(['id' => 'int_new', 'client_secret' => 'sec'], 200),
            '*/capture' => Http::response([], 200),
            '*/cancel' => Http::response([], 200),
        ]);
    }

    private function order(): Order
    {
        return Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null, 'status' => OrderStatus::Processing]);
    }

    private function heldPayment(Order $order, string $id = 'int_held'): Payment
    {
        return Payment::create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::Airwallex, 'transaction_id' => $id,
            'status' => PaymentTransactionStatus::Authorized, 'amount' => $order->grand_total, 'gateway_response' => [],
        ]);
    }

    // ---- the card ---------------------------------------------------------------------------------------------

    #[Test]
    public function the_real_shop_in_live_mode_still_talks_to_the_live_api(): void
    {
        $this->airwallex('live');
        $this->mode('production');
        $this->fakeAirwallex();

        app(PaymentService::class)->createAirwallexIntent($this->order());

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::LIVE));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), self::SANDBOX));
    }

    #[Test]
    public function a_staging_copy_whose_stored_environment_says_live_still_talks_to_the_sandbox_only(): void
    {
        $this->airwallex('live');
        $this->mode('staging');
        $this->fakeAirwallex();

        app(PaymentService::class)->createAirwallexIntent($this->order());

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::SANDBOX));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), self::LIVE));
    }

    #[Test]
    public function a_quarantined_copy_cannot_even_start_a_card_payment(): void
    {
        $this->airwallex();
        $this->mode('quarantine');
        Http::fake();

        $this->expectException(InstanceModeBlockedException::class);
        try {
            app(PaymentService::class)->createAirwallexIntent($this->order());
        } finally {
            Http::assertNothingSent();
        }
    }

    // ---- capture and release of a copied payment ---------------------------------------------------------------

    #[Test]
    public function a_payment_copied_from_the_real_shop_cannot_be_captured_or_released_from_a_copy(): void
    {
        $this->airwallex();
        $order = $this->order();
        $copied = $this->heldPayment($order);
        (new InstanceState)->setWatermarks(['payments' => (int) $copied->id]);
        $this->mode('staging');
        Http::fake();

        foreach (['captureAirwallexPayment', 'releaseAirwallexAuthorization'] as $method) {
            try {
                app(PaymentService::class)->{$method}($copied);
                $this->fail("{$method} must be refused for a copied payment");
            } catch (InstanceModeBlockedException $e) {
                $this->assertStringContainsString('copied', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    #[Test]
    public function a_payment_made_on_the_copy_itself_can_be_captured_against_the_sandbox(): void
    {
        $this->airwallex();
        $order = $this->order();
        $old = $this->heldPayment($order, 'int_copied');
        (new InstanceState)->setWatermarks(['payments' => (int) $old->id]);
        $new = $this->heldPayment($order, 'int_made_here');
        $this->mode('staging');
        $this->fakeAirwallex();

        app(PaymentService::class)->captureAirwallexPayment($new);

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::SANDBOX) && str_contains($request->url(), '/int_made_here/capture'));
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), self::LIVE));
    }

    #[Test]
    public function shipping_a_copied_order_on_a_copy_skips_the_capture_with_a_notice_and_still_ships(): void
    {
        $this->airwallex();
        $order = $this->order();
        $payment = $this->heldPayment($order);
        (new InstanceState)->setWatermarks(['payments' => (int) $payment->id]);
        $this->mode('staging');
        Http::fake();
        Log::spy();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped, 'Shipped via test');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Authorized, $payment->fresh()->status, 'the copied hold was left alone');
        Log::shouldHaveReceived('notice')->withArgs(fn (string $message): bool => str_contains($message, 'copied from the real shop'))->once();
    }

    #[Test]
    public function the_real_shop_still_captures_on_ship(): void
    {
        $this->airwallex();
        $order = $this->order();
        $payment = $this->heldPayment($order);
        (new InstanceState)->setWatermarks(['payments' => (int) $payment->id]); // a stale watermark must mean nothing in production
        $this->mode('production');
        $this->fakeAirwallex();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped, 'Shipped via test');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/int_held/capture'));
    }

    // ---- Paysera --------------------------------------------------------------------------------------------------

    #[Test]
    public function paysera_is_refused_on_a_copy_without_a_single_outbound_call(): void
    {
        $this->store(['paysera_client_id' => 'pid', 'paysera_client_secret' => 'psecret']);
        Http::fake();

        foreach (['staging', 'quarantine'] as $mode) {
            $this->mode($mode);

            try {
                app(PaymentService::class)->createPayseraPaymentLink($this->order());
                $this->fail("Paysera must be refused on {$mode}");
            } catch (InstanceModeBlockedException) {
                $this->addToAssertionCount(1);
            }

            $this->assertNull(app(PaymentService::class)->payseraMethodKeys(), 'not even the list of methods is fetched');
        }

        Http::assertNothingSent();
    }

    // ---- what the checkout offers ----------------------------------------------------------------------------------

    private function allowed(array $methods): void
    {
        Setting::updateOrCreate(['group' => 'checkout', 'key' => 'allowed_payment_methods'], ['value' => json_encode($methods), 'type' => SettingType::Json]);
        app(SettingsService::class)->forget('checkout');
    }

    #[Test]
    public function the_real_shop_offers_whatever_the_admin_enabled(): void
    {
        $this->allowed(['card', 'paysera', 'bank_transfer']);
        $this->mode('production');

        $this->assertSame(['card', 'paysera', 'bank_transfer'], CheckoutPaymentMethods::enabled());
    }

    #[Test]
    public function a_staging_copy_never_offers_paysera_and_offers_the_card_only_with_sandbox_credentials(): void
    {
        $this->allowed(['card', 'paysera', 'bank_transfer']);
        $this->mode('staging');

        $this->assertSame(['bank_transfer'], CheckoutPaymentMethods::enabled(), 'no card credentials at all');

        $this->airwallex('live');
        $this->assertSame(['bank_transfer'], CheckoutPaymentMethods::enabled(), 'the stored credentials are live ones: they must not be used');

        $this->airwallex('sandbox');
        $this->assertSame(['card', 'bank_transfer'], CheckoutPaymentMethods::enabled());
    }

    #[Test]
    public function a_quarantined_copy_offers_bank_transfer_only_and_a_checkout_never_ends_up_with_nothing(): void
    {
        $this->airwallex('sandbox');
        $this->allowed(['card', 'paysera']);
        $this->mode('quarantine');

        $this->assertSame(['bank_transfer'], CheckoutPaymentMethods::enabled());
        $this->assertSame('bank_transfer', CheckoutPaymentMethods::default());
        $this->assertSame('in:bank_transfer', CheckoutPaymentMethods::validationRule());
    }

    #[Test]
    public function the_paysera_wallets_are_empty_on_a_copy_and_ask_nobody(): void
    {
        $this->allowed(['paysera', 'bank_transfer']);
        $this->store(['paysera_client_id' => 'pid', 'paysera_client_secret' => 'psecret']);
        Http::fake();
        $this->mode('staging');

        $this->assertSame([], CheckoutPaymentMethods::payseraWallets());
        Http::assertNothingSent();
    }
}
