<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SettingType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the authorize-now/capture-later flow: the checkout-time auto_capture
 * flag, the payment_intent.requires_capture webhook (funds held, not yet
 * charged), ship-triggered auto-capture (OrderService::transitionStatus's
 * whole reason for existing here), and the guard that stops
 * processSuccessfulPayment() from throwing when a late "succeeded" webhook
 * lands on an order that has already shipped.
 */
class AirwallexManualCaptureTest extends TestCase
{
    use RefreshDatabase;

    private const SANDBOX_BASE = 'https://api-demo.airwallex.com/api/v1';

    private function setAirwallexSettings(bool $manualCapture): void
    {
        foreach ([
            'airwallex_environment' => 'sandbox',
            'airwallex_client_id' => 'test_client_id',
            'airwallex_api_key' => 'test_api_key',
            'airwallex_manual_capture_enabled' => $manualCapture ? '1' : '0',
        ] as $key => $value) {
            Setting::updateOrCreate(
                ['group' => 'payment', 'key' => $key],
                ['value' => $value, 'type' => SettingType::String],
            );
        }
    }

    private function fakeLoginAndIntent(): void
    {
        Http::fake([
            self::SANDBOX_BASE.'/authentication/login' => Http::response(['token' => 'fake_bearer_token'], 201),
            self::SANDBOX_BASE.'/pa/payment_intents/create' => Http::response(['id' => 'int_test', 'client_secret' => 'secret'], 200),
        ]);
    }

    #[Test]
    public function it_sends_auto_capture_false_when_manual_capture_is_enabled(): void
    {
        $this->setAirwallexSettings(manualCapture: true);
        $this->fakeLoginAndIntent();

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($order);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'payment_intents/create')) {
                return true;
            }

            return $request['payment_method_options']['card']['auto_capture'] === false;
        });
    }

    #[Test]
    public function it_omits_auto_capture_when_manual_capture_is_disabled(): void
    {
        $this->setAirwallexSettings(manualCapture: false);
        $this->fakeLoginAndIntent();

        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        app(PaymentService::class)->createAirwallexIntent($order);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'payment_intents/create')) {
                return true;
            }

            return ! array_key_exists('payment_method_options', $request->data());
        });
    }

    #[Test]
    public function requires_capture_webhook_marks_payment_authorized_without_marking_the_order_paid(): void
    {
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'user_id' => null]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'int_authorized_1',
            'status' => PaymentTransactionStatus::Pending,
            'amount' => $order->grand_total,
            'gateway_response' => [],
        ]);

        app(PaymentService::class)->processAirwallexAuthorization([
            'id' => 'evt_1',
            'type' => 'payment_intent.requires_capture',
            'data' => ['object' => ['id' => 'int_authorized_1']],
        ]);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentTransactionStatus::Authorized, $payment->status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        // Money hasn't actually moved yet — payment_status must not claim Paid.
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
    }

    #[Test]
    public function shipping_an_order_with_an_authorized_payment_triggers_a_capture_call(): void
    {
        $this->setAirwallexSettings(manualCapture: true);

        $order = Order::factory()->create([
            'guest_email' => 'buyer@example.com',
            'user_id' => null,
            'status' => OrderStatus::Processing,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'int_to_capture',
            'status' => PaymentTransactionStatus::Authorized,
            'amount' => $order->grand_total,
            'gateway_response' => [],
        ]);

        Http::fake([
            self::SANDBOX_BASE.'/authentication/login' => Http::response(['token' => 'fake_bearer_token'], 201),
            self::SANDBOX_BASE.'/pa/payment_intents/int_to_capture/capture' => Http::response(['id' => 'int_to_capture', 'status' => 'succeeded'], 200),
        ]);

        app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped, 'Shipped via test');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/pa/payment_intents/int_to_capture/capture'));

        $order->refresh();
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    #[Test]
    public function shipping_an_order_with_no_authorized_payment_never_calls_the_capture_endpoint(): void
    {
        $order = Order::factory()->create([
            'guest_email' => 'buyer@example.com',
            'user_id' => null,
            'status' => OrderStatus::Processing,
        ]);
        // Already captured (the common/default auto-capture case) — nothing to do.
        Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'int_already_captured',
            'status' => PaymentTransactionStatus::Captured,
            'amount' => $order->grand_total,
            'gateway_response' => [],
        ]);

        Http::fake();

        app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped, 'Shipped via test');

        Http::assertNothingSent();
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    #[Test]
    public function a_capture_failure_on_ship_does_not_block_the_shipment(): void
    {
        Log::spy();
        $this->setAirwallexSettings(manualCapture: true);

        $order = Order::factory()->create([
            'guest_email' => 'buyer@example.com',
            'user_id' => null,
            'status' => OrderStatus::Processing,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'int_capture_fails',
            'status' => PaymentTransactionStatus::Authorized,
            'amount' => $order->grand_total,
            'gateway_response' => [],
        ]);

        Http::fake([
            self::SANDBOX_BASE.'/authentication/login' => Http::response(['token' => 'fake_bearer_token'], 201),
            self::SANDBOX_BASE.'/pa/payment_intents/int_capture_fails/capture' => Http::response(['message' => 'authorization expired'], 400),
        ]);

        $result = app(OrderService::class)->transitionStatus($order, OrderStatus::Shipped, 'Shipped via test');

        $this->assertTrue($result);
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
        // The payment stays Authorized — a human needs to chase this, not
        // silently lose track of it as if nothing happened.
        $this->assertSame(PaymentTransactionStatus::Authorized, $payment->fresh()->status);
    }

    #[Test]
    public function a_succeeded_webhook_after_shipment_does_not_throw_an_invalid_transition(): void
    {
        $order = Order::factory()->create([
            'guest_email' => 'buyer@example.com',
            'user_id' => null,
            'status' => OrderStatus::Shipped,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'int_captured_after_ship',
            'status' => PaymentTransactionStatus::Authorized,
            'amount' => $order->grand_total,
            'gateway_response' => [],
        ]);

        // This must not throw InvalidArgumentException("...Shipped to Processing...").
        app(PaymentService::class)->processSuccessfulPayment([
            'id' => 'evt_captured',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'int_captured_after_ship']],
        ]);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(PaymentTransactionStatus::Captured, $payment->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        // Status untouched — still Shipped, not forced back to Processing.
        $this->assertSame(OrderStatus::Shipped, $order->status);
    }

    protected function tearDown(): void
    {
        foreach (['test_client_id'] as $clientId) {
            Cache::forget('airwallex_auth_token:'.md5(self::SANDBOX_BASE.$clientId));
        }
        parent::tearDown();
    }
}
