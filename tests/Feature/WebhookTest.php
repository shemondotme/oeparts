<?php

namespace Tests\Feature;

use App\Jobs\ProcessAirwallexWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Airwallex webhook endpoint, exercised against the contract in Airwallex's
 * "Listen for webhook events" guide — NOT against whatever the code happened
 * to assume. The previous version of this file signed `timestamp.body` with a
 * dot using epoch SECONDS and an event envelope keyed `type`; every one of
 * those was wrong (docs: `x-timestamp` is epoch milliseconds, value_to_digest
 * is the timestamp concatenated directly with the body, the event type is in
 * `name`), so the suite stayed green while no genuine Airwallex delivery could
 * ever have verified.
 */
class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test_secret';

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create([
            'group' => 'payment',
            'key' => 'airwallex_webhook_secret',
            'value' => self::SECRET,
            'type' => 'string',
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-123456',
            'grand_total' => 125.00,
            'payment_method' => 'card',
            'payment_status' => 'pending',
            'status' => 'pending',
            'guest_email' => null,
            'subtotal' => 100.00,
            'shipping_cost' => 5.00,
            'vat_amount' => 20.00,
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
            'gateway' => 'airwallex',
            'transaction_id' => 'int_123456789',
            'status' => 'pending',
            'amount' => 125.00,
            'gateway_response' => [],
        ]);

        // CACHE_STORE=array persists across tests — clear webhook idempotency keys
        foreach (['evt_123456789', 'evt_duplicate_123', 'evt_success_123', 'evt_failed_123', 'evt_cancel_123', 'evt_retry_123'] as $id) {
            Cache::forget("airwallex_webhook_{$id}");
        }
    }

    /** The event envelope exactly as Airwallex documents it: {id, name, account_id, data:{object}, created_at, version}. */
    private function event(string $id, string $name, array $object = []): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'account_id' => 'acct_123',
            'data' => ['object' => array_merge([
                'id' => 'int_123456789',
                'amount' => 125.00,
                'currency' => 'EUR',
                'merchant_order_id' => $this->order->order_number,
            ], $object)],
            'created_at' => '2026-09-24T10:00:00+0000',
            'version' => '2024-02-22',
        ];
    }

    private function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    /**
     * POST the raw body with headers signed per the docs:
     * x-signature = hex HMAC-SHA256(secret, x-timestamp . body), timestamp in ms.
     */
    private function deliver(array $envelope, ?string $timestampMs = null, ?string $signature = null, bool $withHeaders = true)
    {
        $body = json_encode($envelope);
        $timestampMs ??= (string) $this->nowMs();

        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($withHeaders) {
            $server['HTTP_X_TIMESTAMP'] = $timestampMs;
            $server['HTTP_X_SIGNATURE'] = $signature ?? hash_hmac('sha256', $timestampMs.$body, self::SECRET);
        }

        return $this->call('POST', '/webhooks/airwallex', [], [], [], $server, $body);
    }

    #[Test]
    public function it_accepts_a_webhook_signed_the_way_the_docs_specify(): void
    {
        Queue::fake();

        $envelope = $this->event('evt_123456789', 'payment_intent.succeeded');

        $this->deliver($envelope)->assertStatus(200)->assertContent('Webhook accepted');

        Queue::assertPushed(ProcessAirwallexWebhook::class, fn ($job) => $job->getWebhookData()['id'] === 'evt_123456789'
            && ProcessAirwallexWebhook::eventName($job->getWebhookData()) === 'payment_intent.succeeded'
            && $job->queue === 'critical');
    }

    #[Test]
    public function the_old_dot_separated_signature_scheme_is_rejected(): void
    {
        Queue::fake();

        $envelope = $this->event('evt_123456789', 'payment_intent.succeeded');
        $body = json_encode($envelope);
        $ts = (string) $this->nowMs();

        // What the code used to verify (Stripe-style "timestamp.body"). Airwallex never signs it that way.
        $legacy = hash_hmac('sha256', $ts.'.'.$body, self::SECRET);

        $this->deliver($envelope, $ts, $legacy)->assertStatus(401);

        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function it_rejects_an_invalid_signature(): void
    {
        Queue::fake();

        $this->deliver($this->event('evt_123456789', 'payment_intent.succeeded'), null, 'invalid_signature_here')
            ->assertStatus(401)
            ->assertContent('Invalid signature');

        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function a_correctly_signed_but_stale_timestamp_is_rejected(): void
    {
        Queue::fake();

        // 10 minutes old, in MILLISECONDS, with a signature that is otherwise valid —
        // so this can only be rejected by the freshness check.
        $stale = (string) ($this->nowMs() - 600_000);

        $this->deliver($this->event('evt_123456789', 'payment_intent.succeeded'), $stale)
            ->assertStatus(401);

        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function a_millisecond_timestamp_is_not_mistaken_for_stale_seconds(): void
    {
        Queue::fake();

        // Regression: the code compared time() (seconds) with the header, so a
        // perfectly fresh millisecond value always looked ~55,000 years old.
        $this->deliver($this->event('evt_123456789', 'payment_intent.succeeded'), (string) $this->nowMs())
            ->assertStatus(200);
    }

    #[Test]
    public function missing_signature_headers_are_a_clean_401_not_a_server_error(): void
    {
        Queue::fake();

        // A null header used to hit a non-nullable `string $signature` parameter (TypeError -> 500).
        $this->deliver($this->event('evt_123456789', 'payment_intent.succeeded'), null, null, withHeaders: false)
            ->assertStatus(401);

        Queue::assertNotPushed(ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function the_event_type_is_read_from_name_not_type(): void
    {
        Queue::fake();

        // An envelope with neither `name` nor `type` has no usable event type.
        $envelope = $this->event('evt_123456789', 'payment_intent.succeeded');
        unset($envelope['name']);

        $this->deliver($envelope)->assertStatus(400)->assertContent('Missing required fields');
    }

    #[Test]
    public function it_handles_duplicate_events_idempotently(): void
    {
        Queue::fake();

        $envelope = $this->event('evt_duplicate_123', 'payment_intent.succeeded');

        $this->deliver($envelope)->assertStatus(200)->assertContent('Webhook accepted');
        $this->deliver($envelope)->assertStatus(200)->assertContent('Event already processed');

        Queue::assertPushed(ProcessAirwallexWebhook::class, 1);
    }

    #[Test]
    public function the_duplicate_window_outlasts_airwallexs_three_day_retry_schedule(): void
    {
        Queue::fake();

        $envelope = $this->event('evt_duplicate_123', 'payment_intent.succeeded');
        $this->deliver($envelope)->assertStatus(200);

        // Airwallex retries with exponential back-off "over about three days".
        // The claim used to be stored with a TTL of 7*24*60 SECONDS (~2.8 hours).
        Carbon::setTestNow(now()->addDays(3));
        try {
            $this->assertTrue(app(PaymentService::class)->isDuplicateEvent('evt_duplicate_123'), 'a retry on day 3 must still be recognised as a duplicate');
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function an_event_that_could_not_be_queued_is_released_so_the_retry_is_processed(): void
    {
        $envelope = $this->event('evt_retry_123', 'payment_intent.succeeded');

        // Queue outage after the event was claimed.
        Queue::shouldReceive('connection')->once()->andThrow(new \RuntimeException('queue down'));

        $this->deliver($envelope)->assertStatus(503);

        // Airwallex retries once the queue is back. Without the release this
        // would be answered "already processed" and the payment
        // confirmation lost for good.
        Queue::fake();

        $this->deliver($envelope)->assertStatus(200)->assertContent('Webhook accepted');
        Queue::assertPushed(ProcessAirwallexWebhook::class, 1);
    }

    #[Test]
    public function it_queues_payment_failed_and_cancelled_events_under_their_real_names(): void
    {
        Queue::fake();

        $this->deliver($this->event('evt_failed_123', 'payment_intent.payment_failed'))->assertStatus(200);
        $this->deliver($this->event('evt_cancel_123', 'payment_intent.cancelled'))->assertStatus(200);

        Queue::assertPushed(ProcessAirwallexWebhook::class, 2);
    }
}
