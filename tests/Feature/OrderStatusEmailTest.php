<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendOrderStatusEmail;
use App\Jobs\SendRefundStatusEmail;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderStatusEmailTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transition_status_dispatches_status_email(): void
    {
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        app(OrderService::class)->transitionStatus($order, OrderStatus::Paid, 'Payment verified');

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Pending
                && $job->newStatus === OrderStatus::Paid;
        });
    }

    #[Test]
    public function customer_cancel_order_dispatches_status_email_without_crashing(): void
    {
        // Regression test for a live bug found during the Commerce gap-analysis
        // chunk: this route used to call `new SendOrderStatusEmail($order,
        // OrderStatus::Cancelled)` with only 2 of 3 required constructor args,
        // throwing ArgumentCountError on every customer-initiated cancellation.
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Pending,
        ]);

        $response = $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $order]));

        $response->assertRedirect(route('frontend.account.orders', ['lang' => 'en']));
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Pending
                && $job->newStatus === OrderStatus::Cancelled;
        });
        Queue::assertNotPushed(SendOrderConfirmationEmail::class);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    #[Test]
    public function confirm_bank_transfer_payment_sends_confirmation_email_not_status_email(): void
    {
        // Regression test for Option M's consolidation: confirmBankTransferPayment()
        // now routes its status change through OrderService::transitionStatus()
        // with notifyCustomer: false, since it already sends its own more specific
        // SendOrderConfirmationEmail. Without that flag, a bank-transfer
        // confirmation would fire two emails for one event.
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::BankTransfer,
        ]);

        app(PaymentService::class)->confirmBankTransferPayment($payment, 'REF-123', null);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);

        Queue::assertPushed(SendOrderConfirmationEmail::class, function (SendOrderConfirmationEmail $job) use ($order) {
            return $job->order->is($order);
        });
        Queue::assertNotPushed(SendOrderStatusEmail::class);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    #[Test]
    public function airwallex_webhook_success_sends_confirmation_email_not_status_email(): void
    {
        // Regression test for Option O's consolidation: processSuccessfulPayment()
        // now routes its status change through transitionStatus() with
        // notifyCustomer: false, since it already sends its own SendOrderConfirmationEmail.
        Queue::fake();

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'pi_success_999',
        ]);

        app(PaymentService::class)->processSuccessfulPayment([
            'id' => 'evt_success_999',
            'data' => ['object' => ['id' => 'pi_success_999']],
        ]);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);

        Queue::assertPushed(SendOrderConfirmationEmail::class, function (SendOrderConfirmationEmail $job) use ($order) {
            return $job->order->is($order);
        });
        Queue::assertNotPushed(SendOrderStatusEmail::class);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    #[Test]
    public function customer_refund_request_sends_refund_email_not_status_email(): void
    {
        // Regression test for Option O's consolidation: requestRefund() now
        // routes its status change through transitionStatus() with
        // notifyCustomer: false, since it already sends its own SendRefundStatusEmail.
        Queue::fake();

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => OrderStatus::Delivered,
        ]);

        $response = $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]), [
                'reason' => 'The part arrived damaged and does not fit my vehicle as described.',
            ]);

        $response->assertRedirect(route('frontend.account.order.detail', ['lang' => 'en', 'order' => $order]));
        $this->assertSame(OrderStatus::RefundRequested, $order->refresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());

        Queue::assertPushed(SendRefundStatusEmail::class);
        Queue::assertNotPushed(SendOrderStatusEmail::class);
    }
}
