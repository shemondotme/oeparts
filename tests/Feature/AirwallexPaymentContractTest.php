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
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Airwallex behavior checked against Airwallex's own documentation rather than
 * the code's assumptions (Phase 22 docs review, 2026-09-24):
 *
 *  - PaymentIntent `amount` is in MAJOR units ("$9.99 is represented as
 *    9.99"); the code used to send cents, i.e. 100x the order total.
 *  - The client_secret is valid for 60 minutes, so a still-valid intent is
 *    reused rather than minting a new intent + Payment per page load.
 *  - Capture requires a `request_id`.
 *  - Event delivery is unordered ("Airwallex does not guarantee that events
 *    are delivered in the order they were generated"), so a late
 *    requires_capture / payment_failed / cancelled must never downgrade a
 *    payment that already succeeded.
 */
class AirwallexPaymentContractTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api-demo.airwallex.com/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'airwallex_environment' => 'sandbox',
            'airwallex_client_id' => 'test_client_id',
            'airwallex_api_key' => 'test_api_key',
        ] as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }
    }

    private function fakeIntentApi(string $intentId = 'int_test', string $secret = 'secret_abc'): void
    {
        Http::fake([
            self::BASE.'/authentication/login' => Http::response(['token' => 'fake_bearer_token'], 201),
            self::BASE.'/pa/payment_intents/create' => Http::response(['id' => $intentId, 'client_secret' => $secret], 200),
            self::BASE.'/pa/payment_intents/*/capture' => Http::response(['id' => $intentId, 'status' => 'SUCCEEDED'], 200),
        ]);
    }

    private function order(float|int|string $total = 108.87, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'guest_email' => 'buyer@example.com',
            'user_id' => null,
            'grand_total' => $total,
        ], $overrides));
    }

    private function payment(Order $order, PaymentTransactionStatus $status, string $intentId = 'int_x', array $overrides = []): Payment
    {
        // created_at is not mass-assignable — set it with a direct query update.
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $payment = Payment::create(array_merge([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => $intentId,
            'status' => $status,
            'amount' => $order->grand_total,
            'gateway_response' => ['client_secret' => 'secret_abc'],
        ], $overrides));

        if ($createdAt !== null) {
            Payment::whereKey($payment->id)->update(['created_at' => $createdAt]);
        }

        return $payment->refresh();
    }

    private function createRequestBody(): array
    {
        $recorded = Http::recorded(fn ($request) => str_contains($request->url(), 'payment_intents/create'));
        $this->assertNotEmpty($recorded, 'no payment_intents/create request was sent');

        return $recorded->first()[0]->data();
    }

    // ── amount ────────────────────────────────────────────────────────────

    #[Test]
    public function the_intent_amount_is_sent_in_major_units_not_cents(): void
    {
        $this->fakeIntentApi();

        app(PaymentService::class)->createAirwallexIntent($this->order(108.87));

        $body = $this->createRequestBody();
        $this->assertSame(108.87, $body['amount'], 'EUR 108.87 must be sent as 108.87 — sending 10887 charges 100x');
        $this->assertSame('EUR', $body['currency']);
    }

    #[Test]
    public function a_whole_amount_is_still_a_number_and_not_scaled(): void
    {
        $this->fakeIntentApi();

        app(PaymentService::class)->createAirwallexIntent($this->order(250));

        $this->assertEquals(250, $this->createRequestBody()['amount']);
        $this->assertNotEquals(25000, $this->createRequestBody()['amount']);
    }

    #[Test]
    public function the_amount_helper_rounds_with_bcmath_not_float_arithmetic(): void
    {
        $service = app(PaymentService::class);

        $this->assertSame(0.3, $service->airwallexAmount('0.30'));
        // bcmath truncates rather than rounds — the project-wide money convention
        // (order totals are already 2dp, so this only pins determinism).
        $this->assertSame(1234.56, $service->airwallexAmount('1234.567'));
        $this->assertSame(19.99, $service->airwallexAmount(19.99));
    }

    #[Test]
    public function the_customer_payment_json_reports_the_amount_in_major_units_too(): void
    {
        $this->fakeIntentApi();
        $order = $this->order(108.87, ['payment_method' => 'card']);

        $this->withSession(['owned_order_ids' => [$order->id]])
            ->getJson(route('frontend.checkout.payment.intent', ['lang' => 'en', 'order' => $order->order_number]))
            ->assertOk()
            ->assertJsonPath('amount', 108.87)
            ->assertJsonPath('currency', 'EUR');
    }

    // ── return_url ────────────────────────────────────────────────────────

    #[Test]
    public function the_return_url_is_the_waiting_page_not_the_thank_you_page(): void
    {
        $this->fakeIntentApi();
        $order = $this->order();

        app(PaymentService::class)->createAirwallexIntent($order);

        $returnUrl = $this->createRequestBody()['return_url'];
        $this->assertStringContainsString("/checkout/payment/{$order->order_number}/return", $returnUrl);
        $this->assertStringNotContainsString('thank-you', $returnUrl);
    }

    // ── intent reuse ──────────────────────────────────────────────────────

    #[Test]
    public function reloading_the_payment_page_reuses_the_pending_intent(): void
    {
        $this->fakeIntentApi('int_first', 'secret_first');
        $order = $this->order();
        $service = app(PaymentService::class);

        $first = $service->createAirwallexIntent($order);
        $second = $service->createAirwallexIntent($order);

        $this->assertSame($first['payment_intent_id'], $second['payment_intent_id']);
        $this->assertSame('secret_first', $second['client_secret']);
        $this->assertSame(1, $order->payments()->count(), 'no orphan Payment row per page load');
        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), 'payment_intents/create')));
    }

    #[Test]
    public function an_intent_past_the_client_secret_validity_window_is_not_reused(): void
    {
        $this->fakeIntentApi('int_second', 'secret_second');
        $order = $this->order();
        // Airwallex: the secret is valid for 60 minutes and cannot be refreshed.
        $this->payment($order, PaymentTransactionStatus::Pending, 'int_old', ['created_at' => now()->subMinutes(55)]);

        $result = app(PaymentService::class)->createAirwallexIntent($order);

        $this->assertSame('int_second', $result['payment_intent_id']);
        $this->assertSame(2, $order->payments()->count());
    }

    #[Test]
    public function an_intent_created_for_a_different_amount_is_not_reused(): void
    {
        $this->fakeIntentApi('int_new');
        $order = $this->order(200);
        $this->payment($order, PaymentTransactionStatus::Pending, 'int_stale_amount', ['amount' => 150]);

        $result = app(PaymentService::class)->createAirwallexIntent($order);

        $this->assertSame('int_new', $result['payment_intent_id']);
    }

    #[Test]
    public function a_failed_or_captured_intent_is_never_reused(): void
    {
        $this->fakeIntentApi('int_fresh');
        $order = $this->order();
        $this->payment($order, PaymentTransactionStatus::Failed, 'int_failed');
        $this->payment($order, PaymentTransactionStatus::Captured, 'int_done');

        $result = app(PaymentService::class)->createAirwallexIntent($order);

        $this->assertSame('int_fresh', $result['payment_intent_id']);
    }

    #[Test]
    public function the_orders_payment_relation_points_at_the_latest_attempt(): void
    {
        $order = $this->order();
        $this->payment($order, PaymentTransactionStatus::Failed, 'int_a');
        $latest = $this->payment($order, PaymentTransactionStatus::Captured, 'int_b');

        // The waiting page reads $order->payment; a bare hasOne() returned the
        // oldest (failed) attempt and never noticed the one that was paid.
        $this->assertSame($latest->id, $order->fresh()->payment->id);
    }

    // ── capture ───────────────────────────────────────────────────────────

    #[Test]
    public function capture_sends_the_required_request_id_and_no_amount(): void
    {
        $this->fakeIntentApi();
        $order = $this->order();
        $payment = $this->payment($order, PaymentTransactionStatus::Authorized, 'int_held');

        app(PaymentService::class)->captureAirwallexPayment($payment);

        $recorded = Http::recorded(fn ($r) => str_contains($r->url(), '/capture'));
        $this->assertCount(1, $recorded);
        $body = $recorded->first()[0]->data();
        $this->assertArrayHasKey('request_id', $body, 'Airwallex requires request_id on capture');
        $this->assertNotEmpty($body['request_id']);
        $this->assertArrayNotHasKey('amount', $body, 'omitted amount captures the full authorized amount');
    }

    // ── unordered delivery ────────────────────────────────────────────────

    #[Test]
    public function a_late_requires_capture_does_not_downgrade_a_captured_payment(): void
    {
        $order = $this->order(108.87, ['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid]);
        $payment = $this->payment($order, PaymentTransactionStatus::Captured, 'int_seq');

        app(PaymentService::class)->processAirwallexAuthorization([
            'id' => 'evt_late', 'name' => 'payment_intent.requires_capture',
            'data' => ['object' => ['id' => 'int_seq']],
        ]);

        $this->assertSame(PaymentTransactionStatus::Captured, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    #[Test]
    public function a_late_payment_failed_does_not_flip_a_paid_or_authorized_payment(): void
    {
        foreach ([PaymentTransactionStatus::Captured, PaymentTransactionStatus::Authorized] as $status) {
            $order = $this->order(50, ['payment_status' => PaymentStatus::Paid]);
            $payment = $this->payment($order, $status, 'int_'.$status->value);

            app(PaymentService::class)->processFailedPayment([
                'id' => 'evt_f', 'name' => 'payment_intent.payment_failed',
                'data' => ['object' => ['id' => 'int_'.$status->value]],
            ]);

            $this->assertSame($status, $payment->refresh()->status, "a {$status->value} payment must survive a late failure notice");
            $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
        }
    }

    #[Test]
    public function a_payment_failed_on_a_pending_payment_records_the_failure(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, PaymentTransactionStatus::Pending, 'int_pending');

        app(PaymentService::class)->processFailedPayment([
            'id' => 'evt_f', 'name' => 'payment_intent.payment_failed',
            'data' => ['object' => ['id' => 'int_pending']],
        ]);

        $this->assertSame(PaymentTransactionStatus::Failed, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->refresh()->payment_status);
    }

    #[Test]
    public function a_cancelled_intent_cancels_a_processing_order(): void
    {
        $order = $this->order(108.87, ['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Pending]);
        $payment = $this->payment($order, PaymentTransactionStatus::Pending, 'int_cancel');

        app(PaymentService::class)->processCancelledPayment([
            'id' => 'evt_c', 'name' => 'payment_intent.cancelled',
            'data' => ['object' => ['id' => 'int_cancel']],
        ]);

        $this->assertSame(PaymentTransactionStatus::Failed, $payment->refresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    #[Test]
    public function a_cancelled_notice_never_cancels_a_paid_order(): void
    {
        $order = $this->order(108.87, ['status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid]);
        $payment = $this->payment($order, PaymentTransactionStatus::Captured, 'int_paid');

        app(PaymentService::class)->processCancelledPayment([
            'id' => 'evt_c', 'name' => 'payment_intent.cancelled',
            'data' => ['object' => ['id' => 'int_paid']],
        ]);

        $this->assertSame(PaymentTransactionStatus::Captured, $payment->refresh()->status);
        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
    }

    #[Test]
    public function a_cancelled_notice_for_an_already_shipped_order_does_not_throw_or_cancel_it(): void
    {
        $order = $this->order(108.87, ['status' => OrderStatus::Shipped, 'payment_status' => PaymentStatus::Pending]);
        $this->payment($order, PaymentTransactionStatus::Pending, 'int_shipped');

        // Shipped -> Cancelled is not an allowed transition; the old inline
        // handler called transitionStatus() unconditionally and threw.
        app(PaymentService::class)->processCancelledPayment([
            'id' => 'evt_c', 'name' => 'payment_intent.cancelled',
            'data' => ['object' => ['id' => 'int_shipped']],
        ]);

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
    }
}
