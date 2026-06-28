<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\RefundRequestResource\Pages\ListRefundRequests;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommerceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\RolesSeeder::class]);
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    // ── Regression test for Bug 2: RefundRequestPolicy permission-key mismatch ──

    #[Test]
    public function refund_request_policy_update_and_delete_match_process_refunds_permission(): void
    {
        $manager = $this->adminWithRole('manager');
        $support = $this->adminWithRole('support');
        $catalogAdmin = $this->adminWithRole('catalog_admin');
        $refund = RefundRequest::factory()->create();

        $this->assertTrue($manager->can('update', $refund));
        $this->assertTrue($manager->can('delete', $refund));

        $this->assertFalse($support->can('update', $refund));
        $this->assertFalse($support->can('delete', $refund));

        $this->assertFalse($catalogAdmin->can('update', $refund));
        $this->assertFalse($catalogAdmin->can('delete', $refund));
    }

    // ── Regression tests for Bug 1: RefundRequestResource workflow actions ──

    #[Test]
    public function support_role_cannot_see_or_execute_refund_workflow_actions(): void
    {
        $support = $this->adminWithRole('support');
        $this->actingAs($support, 'admin');

        $pending = RefundRequest::factory()->create(['status' => RefundStatus::Pending]);
        $approved = RefundRequest::factory()->create(['status' => RefundStatus::Approved]);

        Livewire::test(ListRefundRequests::class)
            ->assertTableActionHidden('approve', $pending)
            ->assertTableActionHidden('reject', $pending)
            ->assertTableActionHidden('approveAndRefund', $pending)
            ->assertTableActionHidden('markProcessed', $approved);

        // Defense-in-depth: even if a request bypassed the UI hide, the
        // server-side gate (Action::isDisabled()) must also deny execution.
        Livewire::test(ListRefundRequests::class)
            ->assertTableActionDisabled('approve', $pending);

        $this->assertSame(RefundStatus::Pending, $pending->refresh()->status);
        $this->assertSame(RefundStatus::Approved, $approved->refresh()->status);
    }

    #[Test]
    public function manager_role_can_see_and_execute_refund_workflow_actions(): void
    {
        // markProcessed/approveAndRefund dispatch SendRefundProcessedEmail;
        // faking the queue avoids an unrelated pre-existing bug in that
        // mailable's Blade view (references a route that doesn't exist) —
        // logged as a separate finding, not this chunk's concern.
        Queue::fake();

        $manager = $this->adminWithRole('manager');
        $this->actingAs($manager, 'admin');

        $pending = RefundRequest::factory()->create(['status' => RefundStatus::Pending]);
        Livewire::test(ListRefundRequests::class)
            ->assertTableActionVisible('approve', $pending)
            ->callTableAction('approve', $pending);
        $this->assertSame(RefundStatus::Approved, $pending->refresh()->status);

        $approved = RefundRequest::factory()->create(['status' => RefundStatus::Approved]);
        Livewire::test(ListRefundRequests::class)->callTableAction('markProcessed', $approved);
        $this->assertSame(RefundStatus::Processed, $approved->refresh()->status);

        $pending2 = RefundRequest::factory()->create(['status' => RefundStatus::Pending]);
        Livewire::test(ListRefundRequests::class)->callTableAction('approveAndRefund', $pending2);
        $this->assertSame(RefundStatus::Processed, $pending2->refresh()->status);
    }

    // ── Regression tests for Bug 1: OrderResource custom actions ──

    #[Test]
    public function view_only_role_cannot_see_or_execute_order_custom_actions(): void
    {
        // Note: none of the 5 seeded roles currently has 'view orders'
        // without 'edit orders' (support has both) — every seeded role
        // happens to be edit-or-nothing for Orders today, so this test
        // verifies the *mechanism* (the gate itself) with a synthetic
        // view-only role, rather than asserting about today's specific
        // role matrix. This protects against any future role that adds
        // 'view orders' without 'edit orders'.
        Role::create(['name' => 'orders_view_only_test', 'guard_name' => 'admin'])
            ->givePermissionTo('view orders');
        $viewOnlyAdmin = $this->adminWithRole('orders_view_only_test');
        $this->actingAs($viewOnlyAdmin, 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        $trackingOrder = Order::factory()->create(['status' => OrderStatus::Processing, 'tracking_number' => null]);

        $bankOrder = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_method' => \App\Enums\PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
        ]);
        Payment::factory()->create([
            'order_id' => $bankOrder->id,
            'gateway' => PaymentGateway::BankTransfer,
        ]);

        Livewire::test(ListOrders::class)
            ->assertTableActionHidden('changeStatus', $order)
            ->assertTableActionHidden('sendTracking', $trackingOrder)
            ->assertTableActionHidden('confirmPayment', $bankOrder)
            ->assertTableActionHidden('printInvoice', $order);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
        $this->assertNull($trackingOrder->refresh()->tracking_number);
        $this->assertSame(OrderStatus::Pending, $bankOrder->refresh()->status);
    }

    #[Test]
    public function manager_role_can_see_and_execute_order_custom_actions(): void
    {
        $manager = $this->adminWithRole('manager');
        $this->actingAs($manager, 'admin');

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);
        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('changeStatus', $order)
            ->callTableAction('changeStatus', $order, data: ['new_status' => OrderStatus::Paid->value, 'note' => 'test']);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);

        $trackingOrder = Order::factory()->create(['status' => OrderStatus::Processing, 'tracking_number' => null]);
        Livewire::test(ListOrders::class)
            ->callTableAction('sendTracking', $trackingOrder, data: ['tracking_number' => 'DHL-1', 'carrier' => 'DHL']);
        $this->assertSame('DHL-1', $trackingOrder->refresh()->tracking_number);
    }

    // ── Regression tests: ViewOrder page duplicates 4 table-level custom
    // actions (toggleUrgent, addNote, generateInvoice, confirmPayment)
    // without their ->authorize('update') — found during the Phase 6
    // security audit (Option LL). ──

    #[Test]
    public function view_only_role_cannot_see_or_execute_view_order_page_actions(): void
    {
        Role::create(['name' => 'orders_view_page_test', 'guard_name' => 'admin'])
            ->givePermissionTo('view orders');
        $viewOnlyAdmin = $this->adminWithRole('orders_view_page_test');
        $this->actingAs($viewOnlyAdmin, 'admin');

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'urgent_processing' => false,
            'invoice_number' => null,
            'payment_method' => \App\Enums\PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::BankTransfer,
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('toggleUrgent')
            ->assertActionHidden('addNote')
            ->assertActionHidden('generateInvoice')
            ->assertActionHidden('confirmPayment');

        $order->refresh();
        $this->assertFalse($order->urgent_processing);
        $this->assertNull($order->invoice_number);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(0, $order->notes()->count());
    }

    #[Test]
    public function manager_role_can_see_and_execute_view_order_page_actions(): void
    {
        Queue::fake();

        $manager = $this->adminWithRole('manager');
        $this->actingAs($manager, 'admin');

        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'urgent_processing' => false,
            'invoice_number' => null,
            'payment_method' => \App\Enums\PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'gateway' => PaymentGateway::BankTransfer,
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('toggleUrgent')
            ->callAction('toggleUrgent');
        $this->assertTrue($order->refresh()->urgent_processing);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('addNote')
            ->callAction('addNote', data: ['note' => 'test note']);
        $this->assertSame(1, $order->notes()->count());

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('generateInvoice')
            ->callAction('generateInvoice');
        $this->assertNotNull($order->refresh()->invoice_number);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionVisible('confirmPayment')
            ->callAction('confirmPayment', data: ['transaction_id' => 'TXN-1']);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);
    }

    // ── Confirmatory test: PaymentResource is intentionally view-only today ──

    #[Test]
    public function manager_can_view_but_not_edit_or_delete_payments(): void
    {
        $manager = $this->adminWithRole('manager');
        $payment = Payment::factory()->create();

        $this->assertTrue($manager->can('view', $payment));
        $this->assertFalse($manager->can('update', $payment));
        $this->assertFalse($manager->can('delete', $payment));
    }
}
