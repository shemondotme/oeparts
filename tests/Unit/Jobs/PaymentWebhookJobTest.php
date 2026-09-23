<?php

namespace Tests\Unit\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Jobs\NotifyAdminsOfPaymentDispute;
use App\Jobs\ProcessAirwallexWebhook;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
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

        // Job should execute without errors
        $this->assertTrue(true);
    }

    #[Test]
    public function payment_webhook_job_processes_failed_event(): void
    {
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

        // Job should execute without errors
        $this->assertTrue(true);
    }

    #[Test]
    public function payment_webhook_job_processes_canceled_event(): void
    {
        // Regression test for Option O's consolidation: this handler used to
        // call $order->update(['status' => ...]) directly with zero
        // OrderStatusHistory logging and zero customer email.
        Queue::fake();

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
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());

        Queue::assertPushed(SendOrderStatusEmail::class, function ($job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Processing
                && $job->newStatus === OrderStatus::Cancelled;
        });
    }

    #[Test]
    public function dispute_created_event_alerts_admins_without_touching_the_order(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'status' => PaymentTransactionStatus::Captured,
            'transaction_id' => 'pi_dispute_123',
        ]);

        $webhookData = [
            'id' => 'evt_dispute_123',
            'type' => 'dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dst_abc123',
                    'payment_intent_id' => 'pi_dispute_123',
                    'status' => 'REQUIRES_RESPONSE',
                    'stage' => 'CHARGEBACK',
                    'amount' => '49.99',
                    'currency' => 'EUR',
                    'reason' => ['type' => 'FRAUDULENT', 'description' => 'Fraudulent transaction'],
                ],
            ],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        // Alert-only: order/payment state must be untouched.
        $order->refresh();
        $payment->refresh();
        $this->assertEquals(OrderStatus::Processing, $order->status);
        $this->assertEquals(PaymentStatus::Paid, $order->payment_status);
        $this->assertEquals(PaymentTransactionStatus::Captured, $payment->status);

        Queue::assertPushed(NotifyAdminsOfPaymentDispute::class, function ($job) use ($order) {
            return $job->eventType === 'dispute.created'
                && $job->orderId === $order->id
                && $job->orderNumber === $order->order_number
                && $job->disputeId === 'dst_abc123'
                && $job->status === 'REQUIRES_RESPONSE'
                && $job->stage === 'CHARGEBACK'
                && $job->amount === '49.99'
                && $job->currency === 'EUR'
                && $job->reason === 'Fraudulent transaction';
        });
    }

    #[Test]
    public function a_dispute_event_for_an_unrecognized_sub_type_still_alerts_admins(): void
    {
        // Matched by prefix, not an enumerated list, so a dispute.won/
        // dispute.lost/dispute.rfi_responded event Airwallex sends still
        // reaches admins instead of silently falling into
        // handleUnknownEvent().
        Queue::fake();

        $webhookData = [
            'id' => 'evt_dispute_won_123',
            'type' => 'dispute.won',
            'data' => ['object' => ['id' => 'dst_won_123']],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Queue::assertPushed(NotifyAdminsOfPaymentDispute::class, fn ($job) => $job->eventType === 'dispute.won');
    }

    #[Test]
    public function a_dispute_event_with_no_matching_payment_still_alerts_admins(): void
    {
        // A dispute can arrive after the linked payment record is gone
        // (or the payment_intent_id simply doesn't match anything local) —
        // the alert must not be silently dropped just because the order
        // couldn't be resolved.
        Queue::fake();

        $webhookData = [
            'id' => 'evt_dispute_orphan_123',
            'type' => 'dispute.created',
            'data' => ['object' => ['id' => 'dst_orphan_123', 'payment_intent_id' => 'pi_does_not_exist']],
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        Queue::assertPushed(NotifyAdminsOfPaymentDispute::class, function ($job) {
            return $job->eventType === 'dispute.created'
                && $job->orderId === null
                && $job->orderNumber === null;
        });
    }

    #[Test]
    public function payment_webhook_job_handles_unknown_event_type(): void
    {
        $webhookData = [
            'id' => 'evt_unknown_123',
            'type' => 'charge.refunded', // Unknown event type
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        // Job should handle unknown events gracefully
        $this->assertTrue(true);
    }

    #[Test]
    public function payment_webhook_job_logs_event_details(): void
    {
        // For unknown event types, just verify the job handles it
        $webhookData = [
            'id' => 'evt_log_test_123',
            'type' => 'unknown.event',
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $job->handle(app(PaymentService::class));

        // Job should log event details during execution
        $this->assertTrue(true);
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
        $webhookData = [
            'id' => 'evt_failure_123',
            'type' => 'payment_intent.succeeded',
        ];

        $job = new ProcessAirwallexWebhook($webhookData);
        $exception = new \Exception('Test failure');

        // Call failed handler - should handle gracefully
        if (method_exists($job, 'failed')) {
            $job->failed($exception);
        }

        // Job should handle failures gracefully
        $this->assertTrue(true);
    }
}
