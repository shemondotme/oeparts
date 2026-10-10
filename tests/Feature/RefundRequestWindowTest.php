<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\RefundRequest;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The customer's refund window starts when the order was delivered, and a submission is all-or-nothing. */
class RefundRequestWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingsSeeder::class]);
        Queue::fake();
    }

    private function deliveredOrder(User $user, int $deliveredDaysAgo, ?int $lastTouchedDaysAgo = null): Order
    {
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::Delivered]);

        $history = OrderStatusHistory::create([
            'order_id' => $order->id, 'old_status' => OrderStatus::Shipped, 'new_status' => OrderStatus::Delivered, 'note' => 'test',
        ]);
        $history->forceFill(['created_at' => now()->subDays($deliveredDaysAgo)])->save();

        // Any later edit of the order (a note, a tracking fix) bumps updated_at.
        Order::whereKey($order->id)->update(['updated_at' => now()->subDays($lastTouchedDaysAgo ?? $deliveredDaysAgo)]);

        return $order->fresh();
    }

    private function submit(User $user, Order $order)
    {
        return $this->actingAs($user, 'web')->post(
            route('frontend.account.order.refund.submit', ['lang' => 'en', 'order' => $order]),
            ['reason' => str_repeat('The part does not fit. ', 3)],
        );
    }

    #[Test]
    public function an_order_delivered_inside_the_window_can_be_refunded(): void
    {
        $user = User::factory()->create();
        $order = $this->deliveredOrder($user, 3);

        $this->submit($user, $order)->assertRedirect();

        $this->assertSame(OrderStatus::RefundRequested, $order->fresh()->status);
        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
    }

    #[Test]
    public function a_later_edit_of_the_order_does_not_extend_the_window(): void
    {
        $user = User::factory()->create();
        // Delivered 40 days ago; an admin touched the order yesterday.
        $order = $this->deliveredOrder($user, 40, 1);

        $this->submit($user, $order)->assertForbidden();

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(0, RefundRequest::where('order_id', $order->id)->count());
    }

    #[Test]
    public function submitting_twice_is_refused_cleanly_not_a_server_error(): void
    {
        $user = User::factory()->create();
        $order = $this->deliveredOrder($user, 2);

        $this->submit($user, $order)->assertRedirect();
        $this->submit($user, $order->fresh())->assertForbidden();

        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
    }
}
