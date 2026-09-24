<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SettingType;
use App\Jobs\NotifyAdminsOfPaymentDispute;
use App\Jobs\ProcessPayseraWebhook;
use App\Jobs\SendOrderConfirmationEmail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Support\Payments\PayseraWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Paysera Checkout Modern callbacks, exercised against the contract in
 * Paysera's "Webhooks" guide — not against what the code assumed. The
 * previous version of this file (and the code) used a flat
 * {"order_id","status"} body signed with a separate "webhook secret"; the
 * documented callback is nested ({"event":{...},"order":{"paysera_order_id",
 * "status",...}}), signed with the OAuth CLIENT secret, so no genuine
 * delivery could ever have been accepted while these tests stayed green.
 */
class PayseraWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_SECRET = 'test_client_secret';

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(
            ['group' => 'payment', 'key' => 'paysera_client_secret'],
            ['value' => self::CLIENT_SECRET, 'type' => SettingType::String],
        );

        $this->order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'buyer@example.com',
            'order_number' => 'ORD-654321',
            'grand_total' => 125.00,
            'payment_method' => 'paysera',
            'payment_status' => PaymentStatus::Pending,
            'status' => OrderStatus::Pending,
        ]);

        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway' => PaymentGateway::Paysera,
            'transaction_id' => 'order-uuid-999',
            'status' => PaymentTransactionStatus::Pending,
            'amount' => 125.00,
            'gateway_response' => [],
        ]);
    }

    /** Order events carry the full order snapshot (amounts are integer minor units). */
    private function orderEvent(string $status, string $name = 'status_updated', array $orderOverrides = []): array
    {
        return [
            'event' => ['name' => $name, 'type' => 'order'],
            'order' => array_merge([
                'paysera_order_id' => 'order-uuid-999',
                'merchant_order_id' => 'ORD-654321',
                'amount' => 12500,
                'amount_paid' => $status === 'paid' ? 12500 : 0,
                'currency' => 'EUR',
                'status' => $status,
                'created_at' => 1736433270,
                'updated_at' => 1736433570,
                'merchant_data' => [],
            ], $orderOverrides),
        ];
    }

    /** Payment / refund events use a thin envelope. */
    private function paymentEvent(string $status, array $paymentOverrides = []): array
    {
        return [
            'version' => 1,
            'event' => ['type' => 'payment', 'name' => 'status_updated'],
            'order' => ['paysera_order_id' => 'order-uuid-999', 'merchant_order_id' => 'ORD-654321'],
            'payment' => array_merge(['id' => 'p-1', 'status' => $status, 'amount' => 12500, 'currency' => 'EUR'], $paymentOverrides),
            'timestamp' => 1736433570,
        ];
    }

    private function sign(string $body, string $secret = self::CLIENT_SECRET): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    private function deliver(array|string $payload, ?string $signature = null, array $headers = [])
    {
        $body = is_string($payload) ? $payload : json_encode($payload);

        return $this->call('POST', '/webhooks/paysera', [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $signature ?? $this->sign($body),
            'HTTP_X_PAYSERA_SIGNATURE_ALG' => 'HMAC-SHA256',
            'HTTP_X_PAYSERA_CALLBACK_ID' => 'cb-'.md5($body),
        ], $headers), $body);
    }

    private function runJob(array $payload): void
    {
        (new ProcessPayseraWebhook($payload))->handle(app(PaymentService::class));
    }

    // ── endpoint: signature / parsing ─────────────────────────────────────

    #[Test]
    public function it_accepts_a_documented_order_callback_signed_with_the_client_secret(): void
    {
        Queue::fake();

        $this->deliver($this->orderEvent('paid', 'amount_paid_updated'))
            ->assertStatus(200)
            ->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessPayseraWebhook::class, fn ($job) => $job->getWebhookData()['order']['paysera_order_id'] === 'order-uuid-999'
            && $job->queue === 'critical');
    }

    #[Test]
    public function it_rejects_an_invalid_signature_with_403_so_paysera_keeps_retrying(): void
    {
        Queue::fake();

        // 403, not 401: Paysera treats 401 as "stop retrying for good", and a
        // genuine callback failing verification means OUR secret is
        // misconfigured — exactly when the retries (over ~31h) let it be fixed.
        $this->deliver($this->orderEvent('paid'), 'invalid_signature_here')
            ->assertStatus(403)
            ->assertContent('Invalid signature');

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function a_signature_made_with_any_other_key_is_rejected(): void
    {
        Queue::fake();

        $body = json_encode($this->orderEvent('paid'));

        // The old, separate "webhook secret" scheme: not what Paysera signs with.
        $this->deliver($body, $this->sign($body, 'some-legacy-webhook-secret'))->assertStatus(403);

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function a_body_altered_after_signing_is_rejected(): void
    {
        Queue::fake();

        $original = json_encode($this->orderEvent('pending_payment'));
        $tampered = json_encode($this->orderEvent('paid'));

        $this->deliver($tampered, $this->sign($original))->assertStatus(403);

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function an_unsupported_signature_algorithm_header_is_rejected(): void
    {
        Queue::fake();

        $this->deliver($this->orderEvent('paid'), null, ['HTTP_X_PAYSERA_SIGNATURE_ALG' => 'HMAC-SHA1'])->assertStatus(403);

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function without_a_client_secret_nothing_can_be_verified(): void
    {
        Queue::fake();
        Setting::where('key', 'paysera_client_secret')->delete();
        app(SettingsService::class)->forget('payment');

        // Even a body signed with the (now removed) secret must not verify.
        $this->deliver($this->orderEvent('paid'))->assertStatus(403);

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function invalid_json_with_a_valid_signature_is_a_400(): void
    {
        Queue::fake();

        $this->deliver('{not json')->assertStatus(400)->assertContent('Invalid JSON');

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function events_without_an_order_id_are_acknowledged_and_ignored_not_retried(): void
    {
        Queue::fake();

        // e.g. paysera.fund-distributor.* split-payment events carry no paysera_order_id.
        $this->deliver(['event' => ['type' => 'paysera.fund-distributor.distribution', 'name' => 'completed']])
            ->assertStatus(200)
            ->assertContent('Webhook ignored');

        // And the flat shape this integration used to assume is not a real Paysera payload.
        $this->deliver(['order_id' => 'order-uuid-999', 'status' => 'paid'])
            ->assertStatus(200)
            ->assertContent('Webhook ignored');

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    // ── endpoint: idempotency / queueing ──────────────────────────────────

    #[Test]
    public function it_handles_duplicate_deliveries_idempotently(): void
    {
        Queue::fake();

        $payload = $this->orderEvent('paid');

        $this->deliver($payload)->assertStatus(200)->assertContent('Webhook accepted');
        $this->deliver($payload)->assertStatus(200)->assertContent('Event already processed');

        Queue::assertPushed(ProcessPayseraWebhook::class, 1);
    }

    #[Test]
    public function a_status_transition_for_the_same_order_is_not_treated_as_a_duplicate(): void
    {
        Queue::fake();

        $this->deliver($this->orderEvent('pending_payment', 'amount_paid_updated', ['amount_paid' => 5000]))->assertStatus(200);
        $this->deliver($this->orderEvent('paid', 'amount_paid_updated'))->assertStatus(200)->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessPayseraWebhook::class, 2);
    }

    #[Test]
    public function the_duplicate_window_outlasts_paysera_retries_of_about_31_hours(): void
    {
        Queue::fake();

        $payload = $this->orderEvent('paid');
        $this->deliver($payload)->assertStatus(200);

        Carbon::setTestNow(now()->addHours(32));
        try {
            $this->deliver($payload)->assertStatus(200)->assertContent('Event already processed');
        } finally {
            Carbon::setTestNow();
        }

        Queue::assertPushed(ProcessPayseraWebhook::class, 1);
    }

    #[Test]
    public function a_callback_that_could_not_be_queued_is_released_so_the_retry_is_processed(): void
    {
        $payload = $this->orderEvent('paid');

        Queue::shouldReceive('connection')->once()->andThrow(new \RuntimeException('queue down'));
        $this->deliver($payload)->assertStatus(503);

        Queue::fake();
        $this->deliver($payload)->assertStatus(200)->assertContent('Webhook accepted');
        Queue::assertPushed(ProcessPayseraWebhook::class, 1);
    }

    // ── job: order status ─────────────────────────────────────────────────

    #[Test]
    public function the_paid_status_job_marks_the_order_and_payment_paid_and_starts_processing(): void
    {
        Queue::fake();

        $this->runJob($this->orderEvent('paid', 'amount_paid_updated'));

        $this->assertSame(PaymentTransactionStatus::Captured, $this->payment->refresh()->status);
        $this->order->refresh();
        $this->assertSame(PaymentStatus::Paid, $this->order->payment_status);
        $this->assertSame('order-uuid-999', $this->order->payment_reference);
        $this->assertSame(OrderStatus::Processing, $this->order->status);
        Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
    }

    #[Test]
    public function a_redelivered_paid_callback_is_harmless_and_sends_no_second_email(): void
    {
        Queue::fake();

        $this->runJob($this->orderEvent('paid'));
        $this->runJob($this->orderEvent('paid'));

        $this->assertSame(OrderStatus::Processing, $this->order->refresh()->status);
        Queue::assertPushed(SendOrderConfirmationEmail::class, 1);
    }

    #[Test]
    public function paid_with_an_amount_paid_below_the_payment_amount_is_not_fulfilled(): void
    {
        Queue::fake();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('amount_paid');

        try {
            $this->runJob($this->orderEvent('paid', 'amount_paid_updated', ['amount_paid' => 9999]));
        } finally {
            $this->assertSame(PaymentTransactionStatus::Pending, $this->payment->refresh()->status);
            $this->assertSame(OrderStatus::Pending, $this->order->refresh()->status);
        }
    }

    #[Test]
    public function paid_in_an_unexpected_currency_is_not_fulfilled(): void
    {
        Queue::fake();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('currency');

        try {
            $this->runJob($this->orderEvent('paid', 'amount_paid_updated', ['currency' => 'USD']));
        } finally {
            $this->assertSame(PaymentTransactionStatus::Pending, $this->payment->refresh()->status);
        }
    }

    #[Test]
    public function a_paid_callback_for_an_unknown_paysera_order_fails_loudly_for_retry(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment not found');

        $this->runJob($this->orderEvent('paid', 'amount_paid_updated', ['paysera_order_id' => 'no-such-order']));
    }

    #[Test]
    public function the_canceled_status_job_marks_the_payment_failed(): void
    {
        $this->runJob($this->orderEvent('canceled'));

        $this->assertSame(PaymentTransactionStatus::Failed, $this->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Failed, $this->order->refresh()->payment_status);
    }

    #[Test]
    public function a_late_canceled_callback_never_overrides_a_captured_payment(): void
    {
        Queue::fake();

        $this->runJob($this->orderEvent('paid'));
        $this->runJob($this->orderEvent('canceled'));

        $this->assertSame(PaymentTransactionStatus::Captured, $this->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $this->order->refresh()->payment_status);
    }

    #[Test]
    public function a_partial_payment_update_changes_nothing_yet(): void
    {
        $this->runJob($this->orderEvent('pending_payment', 'amount_paid_updated', ['amount_paid' => 5000]));

        $this->assertSame(PaymentTransactionStatus::Pending, $this->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $this->order->refresh()->payment_status);
    }

    // ── job: payment-level events ─────────────────────────────────────────

    #[Test]
    public function a_failed_payment_attempt_does_not_fail_the_order_because_the_customer_can_retry(): void
    {
        foreach (['failed', 'rejected', 'expired', 'canceled'] as $attemptStatus) {
            $this->runJob($this->paymentEvent($attemptStatus));
        }

        $this->assertSame(PaymentTransactionStatus::Pending, $this->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $this->order->refresh()->payment_status);
    }

    #[Test]
    public function a_chargeback_alerts_admins_without_touching_order_or_payment(): void
    {
        Queue::fake();
        $this->runJob($this->orderEvent('paid'));
        Queue::fake();

        $this->runJob($this->paymentEvent('chargeback', ['amount' => 12500]));

        $this->assertSame(PaymentTransactionStatus::Captured, $this->payment->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $this->order->refresh()->payment_status);

        Queue::assertPushed(NotifyAdminsOfPaymentDispute::class, fn ($job) => $job->eventType === 'paysera.payment.chargeback'
            && $job->orderId === $this->order->id
            && $job->orderNumber === 'ORD-654321'
            && $job->disputeId === 'p-1'
            && $job->status === 'chargeback'
            && $job->amount === '125.00'
            && $job->currency === 'EUR');
    }

    #[Test]
    public function a_chargeback_for_an_unknown_order_still_alerts_admins(): void
    {
        Queue::fake();

        $event = $this->paymentEvent('chargeback');
        $event['order']['paysera_order_id'] = 'no-such-order';
        $this->runJob($event);

        Queue::assertPushed(NotifyAdminsOfPaymentDispute::class, fn ($job) => $job->orderId === null && $job->status === 'chargeback');
    }

    // ── the parsed value object ───────────────────────────────────────────

    #[Test]
    public function the_event_parser_reads_both_documented_shapes(): void
    {
        $order = PayseraWebhookEvent::fromArray($this->orderEvent('paid', 'amount_paid_updated'));
        $this->assertTrue($order->isOrderEvent());
        $this->assertFalse($order->isPaymentEvent());
        $this->assertSame('amount_paid_updated', $order->name);
        $this->assertSame('order-uuid-999', $order->payseraOrderId);
        $this->assertSame('ORD-654321', $order->merchantOrderId);
        $this->assertSame('paid', $order->orderStatus);
        $this->assertSame(12500, $order->amount);
        $this->assertSame(12500, $order->amountPaid);
        $this->assertSame('EUR', $order->currency);

        $payment = PayseraWebhookEvent::fromArray($this->paymentEvent('settled'));
        $this->assertTrue($payment->isPaymentEvent());
        $this->assertSame('p-1', $payment->paymentId);
        $this->assertSame('settled', $payment->paymentStatus);
        $this->assertSame('EUR', $payment->currency);
    }

    #[Test]
    public function the_event_parser_is_defensive_about_junk(): void
    {
        $event = PayseraWebhookEvent::fromArray(['event' => 'nope', 'order' => ['paysera_order_id' => 123, 'amount' => 'x']]);

        $this->assertNull($event->type);
        $this->assertNull($event->payseraOrderId, 'a non-string id is not usable');
        $this->assertNull($event->amount);
        $this->assertSame(2500, PayseraWebhookEvent::fromArray(['order' => ['amount' => '2500']])->amount, 'numeric strings tolerated');
    }

    protected function tearDown(): void
    {
        Cache::forget('paysera_auth_token:'.md5('test_client_idtest_client_secret'));
        parent::tearDown();
    }
}
