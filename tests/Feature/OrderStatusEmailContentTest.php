<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Mail\OrderStatusUpdate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The status email says what the announced status means, not one generic line for all of them. */
class OrderStatusEmailContentTest extends TestCase
{
    use RefreshDatabase;

    private function html(Order $order, OrderStatus $to): string
    {
        return (new OrderStatusUpdate($order, OrderStatus::Processing, $to))->render();
    }

    #[Test]
    public function each_status_has_its_own_sentence(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::Pending]);

        $this->assertStringContainsString('is being prepared', $this->html($order, OrderStatus::Processing));
        $this->assertStringContainsString('is on its way', $this->html($order, OrderStatus::Shipped));
        $this->assertStringContainsString('has been delivered', $this->html($order, OrderStatus::Delivered));
        $this->assertStringContainsString('has been cancelled', $this->html($order, OrderStatus::Cancelled));
        $this->assertStringContainsString('has been processed', $this->html($order, OrderStatus::Refunded));
        $this->assertStringNotContainsString('The status of your order has been updated', $this->html($order, OrderStatus::Shipped));
    }

    #[Test]
    public function cancelling_an_already_paid_order_promises_the_refund(): void
    {
        $order = Order::factory()->create(['payment_status' => PaymentStatus::Paid]);

        $html = $this->html($order, OrderStatus::Cancelled);

        $this->assertStringContainsString('we will refund the amount', $html);
    }

    #[Test]
    public function the_shipped_email_carries_the_tracking_number(): void
    {
        $order = Order::factory()->create(['tracking_number' => 'DHL-998877']);

        $this->assertStringContainsString('DHL-998877', $this->html($order, OrderStatus::Shipped));
        $this->assertStringNotContainsString('DHL-998877', $this->html($order, OrderStatus::Processing));
    }

    #[Test]
    public function the_timestamp_uses_the_real_timezone_not_a_hardcoded_cet(): void
    {
        config(['app.timezone' => 'UTC']);
        $order = Order::factory()->create();

        $html = $this->html($order, OrderStatus::Processing);

        $this->assertStringNotContainsString(' CET', $html);
        $this->assertStringContainsString('UTC', $html);
    }

    #[Test]
    public function the_email_announces_the_transition_even_if_the_order_has_moved_on(): void
    {
        // A queued mail can be sent after the order has changed again.
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $html = $this->html($order, OrderStatus::Shipped);

        $this->assertStringContainsString('SHIPPED', $html);
        $this->assertStringNotContainsString('>DELIVERED<', str_replace([' ', "\n"], '', $html));
    }
}
