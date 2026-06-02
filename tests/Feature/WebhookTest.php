<?php

namespace Tests\Feature;

use App\Jobs\ProcessAirwallexWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private Order $order;
    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the required Airwallex webhook secret setting
        \App\Models\Setting::create([
            'group' => 'payment',
            'key' => 'airwallex_webhook_secret',
            'value' => 'test_secret',
            'type' => 'string',
        ]);

        // Also set config for consistency (test uses config() as fallback)
        config(['services.airwallex.webhook_secret' => 'test_secret']);

        // Create a test user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        // Create a test order with correct schema
        $this->order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-123456',
            'grand_total' => 12500, // 125.00 EUR (subtotal + shipping + vat)
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'status' => 'pending',
            'guest_email' => null,
            'subtotal' => 10000,
            'shipping_cost' => 500,
            'vat_amount' => 2000,
            'discount_amount' => 0,
            'shipping_method_id' => null, // Foreign key constraint fix
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

        // Create a test payment
        $this->payment = Payment::create([
            'order_id' => $this->order->id,
            'gateway' => 'airwallex',
            'status' => 'pending',
            'amount' => 12500,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'gateway_payment_id' => 'pi_123456789',
            'gateway_response' => json_encode([]),
        ]);

        $this->paymentService = app(PaymentService::class);

        // CACHE_STORE=array persists across tests — clear webhook idempotency keys
        foreach (['evt_123456789', 'evt_duplicate_123', 'evt_success_123', 'evt_failed_123'] as $id) {
            Cache::forget("airwallex_webhook_{$id}");
        }
    }

    #[Test]
    public function it_accepts_webhook_with_valid_signature()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_123456789',
            'type' => 'payment_intent.succeeded',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $secret = config('services.airwallex.webhook_secret', 'test_secret');
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, $secret);

        $response = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200);
        $response->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessAirwallexWebhook::class, function ($job) use ($payload) {
            return $job->getWebhookData()['id'] === $payload['id'];
        });
    }

    #[Test]
    public function it_rejects_webhook_with_invalid_signature()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_123456789',
            'type' => 'payment_intent.succeeded',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $signature = 'invalid_signature_here';

        $response = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(401);
        $response->assertContent('Invalid signature');
        
        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function it_rejects_webhook_with_expired_timestamp()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_123456789',
            'type' => 'payment_intent.succeeded',
            'created' => time() - 400, // 400 seconds old (> 5 minutes)
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $secret = config('services.airwallex.webhook_secret', 'test_secret');
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, $secret);

        $response = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(401);
        $response->assertContent('Invalid signature');
        
        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function it_handles_duplicate_events_idempotently()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_duplicate_123',
            'type' => 'payment_intent.succeeded',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $secret = config('services.airwallex.webhook_secret', 'test_secret');
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, $secret);

        // First request should succeed
        $response1 = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);
        $response1->assertStatus(200);
        $response1->assertContent('Webhook accepted');

        // Mark the event as processed in cache (simulating what the controller does)
        Cache::put('airwallex_webhook_evt_duplicate_123', true, 86400);

        // Second request with same event ID should be rejected as duplicate
        $response2 = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);
        $response2->assertStatus(200);
        $response2->assertContent('Event already processed');

        // Job should only be dispatched once
        Queue::assertPushed(ProcessAirwallexWebhook::class, 1);
    }

    #[Test]
    public function it_processes_payment_intent_succeeded_event()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_success_123',
            'type' => 'payment_intent.succeeded',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'succeeded',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $secret = config('services.airwallex.webhook_secret', 'test_secret');
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, $secret);

        $response = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200);
        $response->assertContent('Webhook accepted');
        
        Queue::assertPushed(ProcessAirwallexWebhook::class, function ($job) use ($payload) {
            return $job->getWebhookData()['type'] === 'payment_intent.succeeded'
                && $job->queue === 'critical';
        });
    }

    #[Test]
    public function it_processes_payment_intent_failed_event()
    {
        Queue::fake();

        $payload = [
            'id' => 'evt_failed_123',
            'type' => 'payment_intent.failed',
            'created' => time(),
            'data' => [
                'object' => [
                    'id' => 'pi_123456789',
                    'amount' => 12500,
                    'currency' => 'EUR',
                    'status' => 'failed',
                    'metadata' => [
                        'order_number' => $this->order->order_number,
                    ],
                ],
            ],
        ];

        $payloadString = json_encode($payload);
        $timestamp = $payload['created'];
        $secret = config('services.airwallex.webhook_secret', 'test_secret');
        $signature = hash_hmac('sha256', $timestamp . '.' . $payloadString, $secret);

        $response = $this->postJson('/webhooks/airwallex', $payload, [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ]);

        $response->assertStatus(200);
        $response->assertContent('Webhook accepted');
        
        Queue::assertPushed(ProcessAirwallexWebhook::class, function ($job) use ($payload) {
            return $job->getWebhookData()['type'] === 'payment_intent.failed'
                && $job->queue === 'critical';
        });
    }
}