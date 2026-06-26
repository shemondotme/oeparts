<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Filament\Widgets\AwaitingConfirmationList;
use App\Jobs\SendOrderStatusEmail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AwaitingConfirmationListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\RolesSeeder::class,
        ]);
    }

    #[Test]
    public function approve_action_transitions_order_to_shipped_with_logging_and_email(): void
    {
        // Regression test: this widget's "Approve" action used to call
        // $record->update(['status' => ...]) directly, with zero
        // OrderStatusHistory logging and zero customer email.
        Queue::fake();

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        Livewire::test(AwaitingConfirmationList::class)
            ->callTableAction('approve', $order);

        $this->assertSame(OrderStatus::Shipped, $order->refresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Paid
                && $job->newStatus === OrderStatus::Shipped;
        });
    }

    #[Test]
    public function reject_action_transitions_order_to_cancelled_with_logging_and_email(): void
    {
        Queue::fake();

        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Processing]);

        Livewire::test(AwaitingConfirmationList::class)
            ->callTableAction('reject', $order);

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Processing
                && $job->newStatus === OrderStatus::Cancelled;
        });
    }
}
