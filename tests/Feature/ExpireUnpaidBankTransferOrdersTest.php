<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\SettingType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExpireUnpaidBankTransferOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function bankOrder(int $hoursOld, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
            'created_at' => now()->subHours($hoursOld),
        ], $overrides));
    }

    #[Test]
    public function an_unpaid_bank_transfer_order_past_the_limit_is_cancelled_and_its_stock_released(): void
    {
        Queue::fake();
        $order = $this->bankOrder(49);
        $payment = Payment::factory()->create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::BankTransfer,
            'status' => PaymentTransactionStatus::Pending,
        ]);
        $product = Product::factory()->create(['is_in_stock' => false]);
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Failed, $payment->fresh()->status);
        $this->assertTrue((bool) $product->fresh()->is_in_stock);
    }

    #[Test]
    public function orders_inside_the_limit_paid_or_on_another_method_are_left_alone(): void
    {
        Queue::fake();
        $recent = $this->bankOrder(10);
        $paid = $this->bankOrder(100, ['payment_status' => PaymentStatus::Paid]);
        $processing = $this->bankOrder(100, ['status' => OrderStatus::Processing]);
        $card = $this->bankOrder(100, ['payment_method' => PaymentMethod::Card]);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Pending, $recent->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $paid->fresh()->status);
        $this->assertSame(OrderStatus::Processing, $processing->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $card->fresh()->status);
    }

    #[Test]
    public function the_limit_comes_from_the_orders_policy_setting(): void
    {
        Queue::fake();
        Setting::updateOrCreate(
            ['group' => 'orders', 'key' => 'bank_transfer_expiry_hours'],
            ['value' => '12', 'type' => SettingType::String],
        );
        Cache::flush();
        $order = $this->bankOrder(13);

        $this->artisan('oeparts:orders:expire-unpaid')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
    }

    #[Test]
    public function the_sweep_is_scheduled_hourly(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'oeparts:orders:expire-unpaid'));

        $this->assertNotNull($event, 'oeparts:orders:expire-unpaid is not scheduled');
        $this->assertSame('0 * * * *', $event->expression);
    }
}
