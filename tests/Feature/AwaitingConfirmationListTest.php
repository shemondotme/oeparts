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
use Spatie\Permission\Models\Role;
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

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function start_processing_moves_a_paid_order_into_processing_with_logging_and_email(): void
    {
        Queue::fake();

        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        Livewire::test(AwaitingConfirmationList::class)
            ->callTableAction('start_processing', $order);

        $this->assertSame(OrderStatus::Processing, $order->refresh()->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());

        Queue::assertPushed(SendOrderStatusEmail::class, function (SendOrderStatusEmail $job) use ($order) {
            return $job->order->is($order)
                && $job->oldStatus === OrderStatus::Paid
                && $job->newStatus === OrderStatus::Processing;
        });
    }

    #[Test]
    public function start_processing_is_hidden_for_orders_already_processing(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Processing]);

        Livewire::test(AwaitingConfirmationList::class)
            ->assertTableActionHidden('start_processing', $order);
    }

    #[Test]
    public function view_only_role_cannot_see_or_execute_start_processing(): void
    {
        // Same mechanism-not-matrix approach as CommerceAuthorizationTest:
        // every seeded role is edit-or-nothing for Orders today, so prove the
        // ->authorize('update') gate with a synthetic view-only role. The
        // widget shipped with NO gate at all (§5q #3).
        Role::create(['name' => 'orders_view_only_test', 'guard_name' => 'admin'])
            ->givePermissionTo('view orders');

        $this->actingAs($this->adminWithRole('orders_view_only_test'), 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Paid]);

        Livewire::test(AwaitingConfirmationList::class)
            ->assertTableActionHidden('start_processing', $order);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    #[Test]
    public function the_one_click_ship_and_cancel_actions_are_gone(): void
    {
        // "Approve" used to jump Paid straight to Shipped (no tracking number)
        // and "Reject" cancelled a paid order — both replaced deliberately.
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        Order::factory()->create(['status' => OrderStatus::Paid]);

        Livewire::test(AwaitingConfirmationList::class)
            ->assertTableActionDoesNotExist('approve')
            ->assertTableActionDoesNotExist('reject');
    }
}
