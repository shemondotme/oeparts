<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\PaidOrderCancelledNotification;
use App\Services\CancelledOrderSettlement;
use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.5: cancelling an order that was copied from the real shop must not try to release its card hold, queue a
 * refund for money this site never took, or alert staff about it.
 */
class OrderSettlementSafeModeTest extends TestCase
{
    use RefreshDatabase;

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
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

    private function paidOrder(PaymentTransactionStatus $status): array
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Cancelled, 'payment_status' => $status === PaymentTransactionStatus::Captured ? PaymentStatus::Paid : PaymentStatus::Pending]);
        $payment = Payment::create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::Airwallex, 'transaction_id' => 'int_'.uniqid(),
            'status' => $status, 'amount' => $order->grand_total, 'gateway_response' => [],
        ]);

        return [$order, $payment];
    }

    #[Test]
    public function a_copied_captured_order_cancelled_on_a_copy_creates_no_refund_and_no_alert(): void
    {
        [$order, $payment] = $this->paidOrder(PaymentTransactionStatus::Captured);
        (new InstanceState)->setWatermarks(['payments' => (int) $payment->id]);
        config(['instance.mode' => 'staging']);
        Notification::fake();
        Http::fake();
        Log::spy();

        app(CancelledOrderSettlement::class)->settle($order);

        $this->assertSame(0, RefundRequest::count(), 'no real money was taken on this site, so none is owed');
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Log::shouldHaveReceived('notice')->withArgs(fn (string $m): bool => str_contains($m, 'copied from the real shop'))->once();
    }

    #[Test]
    public function a_copied_card_hold_is_not_released_from_a_copy(): void
    {
        [$order, $payment] = $this->paidOrder(PaymentTransactionStatus::Authorized);
        (new InstanceState)->setWatermarks(['payments' => (int) $payment->id]);
        config(['instance.mode' => 'quarantine']);
        Http::fake();

        app(CancelledOrderSettlement::class)->settle($order);

        Http::assertNothingSent();
        $this->assertSame(PaymentTransactionStatus::Authorized, $payment->fresh()->status);
    }

    #[Test]
    public function the_real_shop_still_queues_the_refund_and_alerts_staff(): void
    {
        [$order, $payment] = $this->paidOrder(PaymentTransactionStatus::Captured);
        (new InstanceState)->setWatermarks(['payments' => (int) $payment->id]); // stale on purpose
        config(['instance.mode' => 'production']);
        Admin::factory()->create(['is_active' => true]);
        Notification::fake();

        app(CancelledOrderSettlement::class)->settle($order);

        $this->assertSame(1, RefundRequest::count());
        Notification::assertSentTo(Admin::first(), PaidOrderCancelledNotification::class);
    }

    #[Test]
    public function an_order_paid_on_the_copy_itself_is_settled_normally_there(): void
    {
        [$old, $oldPayment] = $this->paidOrder(PaymentTransactionStatus::Captured);
        (new InstanceState)->setWatermarks(['payments' => (int) $oldPayment->id]);
        [$order] = $this->paidOrder(PaymentTransactionStatus::Captured);
        config(['instance.mode' => 'staging']);
        Notification::fake();

        app(CancelledOrderSettlement::class)->settle($order);

        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count(), 'a test order made on the copy follows the normal flow');
        $this->assertSame(0, RefundRequest::where('order_id', $old->id)->count());
    }
}
