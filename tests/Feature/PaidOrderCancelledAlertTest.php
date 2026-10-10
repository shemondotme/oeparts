<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Filament\Resources\RefundRequestResource\Pages\ListRefundRequests;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\PaidOrderCancelledNotification;
use App\Services\OrderService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cancelling an order that already holds the customer's money must not leave the
 * money behind: a card hold is released, taken money becomes a tracked refund,
 * and staff are told which of the two happened.
 */
class PaidOrderCancelledAlertTest extends TestCase
{
    use RefreshDatabase;

    private function heldCardOrder(?User $user = null): array
    {
        $order = Order::factory()->create([
            'user_id' => $user?->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::Airwallex,
            'transaction_id' => 'pi_held_1',
            'status' => PaymentTransactionStatus::Authorized,
        ]);

        return [$order, $payment];
    }

    private function fakeAirwallex(bool $cancelOk = true): void
    {
        Cache::flush();
        Http::fake([
            '*/authentication/login' => Http::response(['token' => 'tok', 'expires_at' => now()->addHour()->toIso8601String()]),
            '*/pa/payment_intents/pi_held_1/cancel' => Http::response($cancelOk ? ['status' => 'CANCELLED'] : ['message' => 'nope'], $cancelOk ? 200 : 400),
        ]);
    }

    #[Test]
    public function cancelling_an_order_with_taken_money_alerts_admins_and_queues_a_tracked_refund(): void
    {
        Queue::fake();
        Notification::fake();
        $active = Admin::factory()->create(['is_active' => true]);
        $inactive = Admin::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid, 'grand_total' => '191.50',
        ]);

        app(OrderService::class)->cancelOrder($order, 'Customer changed their mind');

        Notification::assertSentTo($active, PaidOrderCancelledNotification::class,
            fn ($n) => $n->order->is($order) && $n->outcome === PaidOrderCancelledNotification::REFUND_DUE);
        Notification::assertNotSentTo($inactive, PaidOrderCancelledNotification::class);

        $refund = RefundRequest::where('order_id', $order->id)->sole();
        $this->assertSame(RefundStatus::Approved, $refund->status);
        $this->assertSame('191.50', (string) $refund->amount_requested);
        $this->assertSame($user->id, $refund->user_id);
    }

    #[Test]
    public function a_cancellation_never_queues_the_same_refund_twice(): void
    {
        Queue::fake();
        Notification::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        ]);
        RefundRequest::factory()->create([
            'order_id' => $order->id, 'user_id' => $user->id, 'status' => RefundStatus::Pending,
        ]);

        app(OrderService::class)->cancelOrder($order);

        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
    }

    #[Test]
    public function an_order_without_a_customer_account_still_alerts_staff(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = Admin::factory()->create(['is_active' => true]);
        $order = Order::factory()->create([
            'user_id' => null, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        ]);

        app(OrderService::class)->cancelOrder($order);

        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class);
        $this->assertSame(0, RefundRequest::where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_held_card_payment_is_released_at_the_gateway_and_no_refund_is_needed(): void
    {
        Queue::fake();
        Notification::fake();
        $this->fakeAirwallex();
        $admin = Admin::factory()->create(['is_active' => true]);
        [$order, $payment] = $this->heldCardOrder(User::factory()->create());

        app(OrderService::class)->cancelOrder($order);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/pa/payment_intents/pi_held_1/cancel'));
        $this->assertSame(PaymentTransactionStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, RefundRequest::where('order_id', $order->id)->count());
        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class,
            fn ($n) => $n->outcome === PaidOrderCancelledNotification::HOLD_RELEASED);
    }

    #[Test]
    public function a_failed_hold_release_never_blocks_the_cancellation_and_tells_staff(): void
    {
        Queue::fake();
        Notification::fake();
        $this->fakeAirwallex(cancelOk: false);
        $admin = Admin::factory()->create(['is_active' => true]);
        [$order, $payment] = $this->heldCardOrder();

        app(OrderService::class)->cancelOrder($order);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Authorized, $payment->fresh()->status);
        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class,
            fn ($n) => $n->outcome === PaidOrderCancelledNotification::HOLD_RELEASE_FAILED);
    }

    #[Test]
    public function cancelling_an_unpaid_order_does_nothing(): void
    {
        Queue::fake();
        Notification::fake();
        Http::fake();
        Admin::factory()->create(['is_active' => true]);
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'payment_status' => PaymentStatus::Pending]);

        app(OrderService::class)->cancelOrder($order);

        Notification::assertNothingSent();
        Http::assertNothingSent();
        $this->assertSame(0, RefundRequest::count());
    }

    #[Test]
    public function a_customer_cancelling_their_paid_order_from_the_account_triggers_the_refund_flow(): void
    {
        Queue::fake();
        Notification::fake();
        $admin = Admin::factory()->create(['is_active' => true]);
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        ]);

        $this->actingAs($user, 'web')
            ->post(route('frontend.account.order.cancel', ['lang' => 'en', 'order' => $order]))
            ->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        Notification::assertSentTo($admin, PaidOrderCancelledNotification::class);
        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
    }

    #[Test]
    public function every_alert_variant_renders_with_the_order_and_its_next_step(): void
    {
        $admin = Admin::factory()->create();
        $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'payment_status' => PaymentStatus::Paid]);

        foreach ([
            PaidOrderCancelledNotification::REFUND_DUE => 'mark as processed',
            PaidOrderCancelledNotification::HOLD_RELEASED => 'released automatically',
            PaidOrderCancelledNotification::HOLD_RELEASE_FAILED => 'airwallex dashboard',
        ] as $outcome => $needle) {
            $html = strtolower((string) (new PaidOrderCancelledNotification($order, $outcome))->toMail($admin)->render());

            $this->assertStringContainsString(strtolower($order->order_number), $html, $outcome);
            $this->assertStringContainsString($needle, $html, $outcome);
        }
    }

    #[Test]
    public function marking_the_auto_queued_refund_processed_marks_the_cancelled_order_refunded(): void
    {
        Queue::fake();
        Notification::fake();
        $this->seed([RolesSeeder::class]);
        $manager = Admin::factory()->create(['is_active' => true]);
        $manager->assignRole('manager');
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing, 'payment_status' => PaymentStatus::Paid,
        ]);
        app(OrderService::class)->cancelOrder($order);
        $refund = RefundRequest::where('order_id', $order->id)->sole();

        $this->actingAs($manager, 'admin');
        Livewire::test(ListRefundRequests::class)
            ->callTableAction('markProcessed', $refund);

        $this->assertSame(RefundStatus::Processed, $refund->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
    }
}
