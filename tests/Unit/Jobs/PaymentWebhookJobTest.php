<?php

namespace Tests\Unit\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\ProcessAirwallexWebhook;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payment_webhook_job_is_queued_on_critical(): void
    {
        Queue::fake();
        $webhookData = [
            'id' => 'evt_123',
            'type' => 'payment_intent.succeeded',
        ];

        dispatch(new ProcessAirwallexWebhook($webhookData));

        Queue::assertPushedOn('critical', ProcessAirwallexWebhook::class);
    }

    #[Test]
    public function payment_webhook_job_has_three_retries(): void
    {
        $webhookData = ['id' => 'evt_123', 'type' => 'payment_intent.succeeded'];
        $job = new ProcessAirwallexWebhook($webhookData);

        $this->assertEquals(3, $job->tries);
    }

    #[Test]
    public function payment_webhook_job_has_backoff_delays(): void
    {
        $webhookData = ['id' => 'evt_123', 'type' => 'payment_intent.succeeded'];
        $job = new ProcessAirwallexWebhook($webhookData);

        $this->assertEquals([60, 120, 300], $job->backoff());
    }

    #[Test]
    public function payment_webhook_job_processes_succeeded_event(): void
    {
        Log::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        $webhookData = [
            'id' => 'evt_success_123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => $payment->transaction_id,
                    'status' => 'SUCCEEDED',
                ],
            ],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Log::assertLogged('info', fn($log) => str_contains($log, 'Processing Airwallex webhook job'));
    }

    #[Test]
    public function payment_webhook_job_processes_failed_event(): void
    {
        Log::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        $webhookData = [
            'id' => 'evt_failed_123',
            'type' => 'payment_intent.failed',
            'data' => [
                'object' => [
                    'id' => $payment->transaction_id,
                    'status' => 'FAILED',
                ],
            ],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Log::assertLogged('info', fn($log) => str_contains($log, 'Processing Airwallex webhook job'));
    }

    #[Test]
    public function payment_webhook_job_processes_canceled_event(): void
    {
        Log::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'status' => PaymentTransactionStatus::Pending,
            'transaction_id' => 'pi_cancel_123',
        ]);

        $webhookData = [
            'id' => 'evt_cancel_123',
            'type' => 'payment_intent.canceled',
            'data' => [
                'object' => [
                    'id' => 'pi_cancel_123',
                    'status' => 'CANCELED',
                ],
            ],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        $order->refresh();
        $this->assertEquals(OrderStatus::Cancelled, $order->status);
        $this->assertEquals(PaymentStatus::Failed, $order->payment_status);
    }

    #[Test]
    public function payment_webhook_job_handles_unknown_event_type(): void
    {
        Log::fake();

        $webhookData = [
            'id' => 'evt_unknown_123',
            'type' => 'charge.refunded', // Unknown event type
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Log::assertLogged('info', fn($log) => str_contains($log, 'unknown event type ignored'));
    }

    #[Test]
    public function payment_webhook_job_logs_event_details(): void
    {
        Log::fake();

        $webhookData = [
            'id' => 'evt_log_test_123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => []],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Log::assertLogged('info', fn($log) => str_contains($log, 'evt_log_test_123'));
    }

    #[Test]
    public function payment_webhook_job_can_be_retrieved(): void
    {
        $webhookData = [
            'id' => 'evt_retrieve_123',
            'type' => 'payment_intent.succeeded',
            'custom' => 'data',
        ];

        $job = new ProcessAirwallexWebhook($webhookData);

        $this->assertEquals($webhookData, $job->getWebhookData());
    }

    #[Test]
    public function payment_webhook_job_retry_until_10_minutes(): void
    {
        $webhookData = ['id' => 'evt_123', 'type' => 'payment_intent.succeeded'];
        $job = new ProcessAirwallexWebhook($webhookData);

        $retryUntil = $job->retryUntil();

        // Should retry until 10 minutes from now
        $this->assertTrue($retryUntil->greaterThan(now()->addMinutes(9)));
        $this->assertTrue($retryUntil->lessThan(now()->addMinutes(11)));
    }

    #[Test]
    public function payment_webhook_job_logs_critical_failure(): void
    {
        Log::fake();

        $webhookData = [
            'id' => 'evt_failure_123',
            'type' => 'payment_intent.succeeded',
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $exception = new \Exception('Test failure');

        $job->failed($exception);

        Log::assertLogged('critical', fn($log) => str_contains($log, 'failed after all retries'));
    }
}
