<?php

namespace Tests\Feature;

use App\Jobs\ProcessPayseraWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mirrors WebhookTest.php's Airwallex coverage for the Paysera callback
 * endpoint: signature verification, idempotency, and dispatch to the
 * processing job. See PaymentService::verifyPayseraWebhookSignature()'s
 * docblock — the exact signed-string scheme is best-effort pending a real
 * Paysera callback delivery, so these tests exercise the implementation as
 * written, not a confirmed-against-Paysera contract.
 */
class PayseraWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        \App\Models\Setting::create([
            'group' => 'payment',
            'key' => 'paysera_webhook_secret',
            'value' => 'test_secret',
            'type' => 'string',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-654321',
            'grand_total' => 12500,
            'payment_method' => 'paysera',
            'payment_status' => 'pending',
            'status' => 'pending',
            'guest_email' => null,
            'subtotal' => 10000,
            'shipping_cost' => 500,
            'vat_amount' => 2000,
            'discount_amount' => 0,
            'shipping_method_id' => null,
            'shipping_name' => 'Test User',
            'shipping_address_line1' => 'Test Street',
            'shipping_city' => 'Test City',
            'shipping_postal_code' => '12345',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
            'is_b2b' => false,
            'vat_exempt' => false,
            'company_name' => null,
            'vat_number' => null,
            'customer_note' => null,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'tracking_number' => null,
            'carrier' => null,
            'urgent_processing' => false,
            'urgent_processing_fee' => 0,
            'invoice_number' => null,
        ]);

        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway' => 'paysera',
            'status' => 'pending',
            'amount' => 12500,
            'transaction_id' => 'order-uuid-999',
            'gateway_response' => [],
        ]);

        foreach (['order-uuid-999:paid', 'order-uuid-999:canceled', 'order-uuid-999:pending_payment'] as $key) {
            Cache::forget("paysera_webhook_{$key}");
        }
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, 'test_secret');
    }

    #[Test]
    public function it_accepts_webhook_with_valid_signature(): void
    {
        Queue::fake();

        $payload = ['order_id' => 'order-uuid-999', 'status' => 'paid'];
        $payloadString = json_encode($payload);

        $response = $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $this->sign($payloadString),
        ], $payloadString);

        $response->assertStatus(200);
        $response->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessPayseraWebhook::class, function ($job) {
            return $job->getWebhookData()['order_id'] === 'order-uuid-999';
        });
    }

    #[Test]
    public function it_rejects_webhook_with_invalid_signature(): void
    {
        Queue::fake();

        $payload = ['order_id' => 'order-uuid-999', 'status' => 'paid'];
        $payloadString = json_encode($payload);

        $response = $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => 'invalid_signature_here',
        ], $payloadString);

        $response->assertStatus(401);
        $response->assertContent('Invalid signature');

        Queue::assertNotPushed(ProcessPayseraWebhook::class);
    }

    #[Test]
    public function it_handles_duplicate_events_idempotently(): void
    {
        Queue::fake();

        $payload = ['order_id' => 'order-uuid-999', 'status' => 'paid'];
        $payloadString = json_encode($payload);
        $signature = $this->sign($payloadString);

        $response1 = $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $signature,
        ], $payloadString);
        $response1->assertStatus(200);
        $response1->assertContent('Webhook accepted');

        $response2 = $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $signature,
        ], $payloadString);
        $response2->assertStatus(200);
        $response2->assertContent('Event already processed');

        Queue::assertPushed(ProcessPayseraWebhook::class, 1);
    }

    #[Test]
    public function a_status_transition_for_the_same_order_is_not_treated_as_a_duplicate(): void
    {
        Queue::fake();

        $pending = json_encode(['order_id' => 'order-uuid-999', 'status' => 'pending_payment']);
        $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $this->sign($pending),
        ], $pending)->assertStatus(200);

        $paid = json_encode(['order_id' => 'order-uuid-999', 'status' => 'paid']);
        $response = $this->call('POST', '/webhooks/paysera', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSERA_SIGNATURE' => $this->sign($paid),
        ], $paid);

        $response->assertStatus(200);
        $response->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessPayseraWebhook::class, 2);
    }

    #[Test]
    public function the_paid_status_job_marks_the_order_and_payment_paid(): void
    {
        $job = new ProcessPayseraWebhook(['order_id' => 'order-uuid-999', 'status' => 'paid']);
        $job->handle(app(\App\Services\PaymentService::class));

        $this->payment->refresh();
        $this->order->refresh();

        $this->assertSame('captured', $this->payment->status->value);
        $this->assertSame('paid', $this->order->payment_status->value);
    }

    #[Test]
    public function the_canceled_status_job_marks_the_payment_failed(): void
    {
        $job = new ProcessPayseraWebhook(['order_id' => 'order-uuid-999', 'status' => 'canceled']);
        $job->handle(app(\App\Services\PaymentService::class));

        $this->payment->refresh();
        $this->order->refresh();

        $this->assertSame('failed', $this->payment->status->value);
        $this->assertSame('failed', $this->order->payment_status->value);
    }
}
