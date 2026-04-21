<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Order $order;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin manually since factory may not exist
        $this->admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Create customer
        $this->customer = User::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Create order with all required fields
        $this->order = Order::create([
            'order_number' => 'ORD-' . time(),
            'user_id' => $this->customer->id,
            'status' => OrderStatus::Paid,
            'payment_method' => \App\Enums\PaymentMethod::Card,
            'payment_status' => \App\Enums\PaymentStatus::Paid,
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '10.00',
            'vat_amount' => '22.00',
            'grand_total' => '132.00',
            'shipping_name' => 'Test Customer',
            'shipping_address_line1' => '123 Test St',
            'shipping_city' => 'Test City',
            'shipping_postal_code' => '12345',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
        ]);
    }

    #[Test]
    public function admin_can_view_orders_list()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index'));

        $response->assertOk();
        $response->assertSee('Order Management');
        $response->assertSee($this->order->order_number);
    }

    #[Test]
    public function admin_can_filter_orders_by_status()
    {
        $pendingOrder = Order::create([
            'order_number' => 'ORD-PENDING-' . time(),
            'user_id' => $this->customer->id,
            'status' => OrderStatus::Pending,
            'payment_method' => \App\Enums\PaymentMethod::Card,
            'payment_status' => \App\Enums\PaymentStatus::Pending,
            'subtotal' => '50.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '5.00',
            'vat_amount' => '11.00',
            'grand_total' => '66.00',
            'shipping_name' => 'Test Customer',
            'shipping_address_line1' => '123 Test St',
            'shipping_city' => 'Test City',
            'shipping_postal_code' => '12345',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
        ]);

        $shippedOrder = Order::create([
            'order_number' => 'ORD-SHIPPED-' . time(),
            'user_id' => $this->customer->id,
            'status' => OrderStatus::Shipped,
            'payment_method' => \App\Enums\PaymentMethod::Card,
            'payment_status' => \App\Enums\PaymentStatus::Paid,
            'subtotal' => '75.00',
            'discount_amount' => '0.00',
            'shipping_cost' => '8.00',
            'vat_amount' => '16.50',
            'grand_total' => '99.50',
            'shipping_name' => 'Test Customer',
            'shipping_address_line1' => '123 Test St',
            'shipping_city' => 'Test City',
            'shipping_postal_code' => '12345',
            'shipping_country_code' => 'DE',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee($pendingOrder->order_number);
    }

    #[Test]
    public function admin_can_view_order_details()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.show', $this->order));

        $response->assertOk();
        $response->assertSee('Order #' . $this->order->order_number);
        $response->assertSee($this->customer->email);
    }

    #[Test]
    public function admin_can_update_order_status()
    {
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Paid,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.update-status', $this->order), [
                'status' => OrderStatus::Processing->value,
                'note' => 'Starting processing',
                'notify_customer' => false,
            ]);

        $response->assertRedirect(route('admin.orders.show', $this->order));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'status' => OrderStatus::Processing,
        ]);

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $this->order->id,
            'old_status' => OrderStatus::Paid->value,
            'new_status' => OrderStatus::Processing->value,
            'admin_id' => $this->admin->id,
        ]);
    }

    #[Test]
    public function status_change_is_logged_in_history()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.update-status', $this->order), [
                'status' => OrderStatus::Shipped->value,
                'note' => 'Shipped via DHL',
                'notify_customer' => false,
            ]);

        $history = OrderStatusHistory::where('order_id', $this->order->id)->first();
        $this->assertNotNull($history);
        $this->assertEquals(OrderStatus::Paid->value, $history->old_status->value);
        $this->assertEquals(OrderStatus::Shipped->value, $history->new_status->value);
        $this->assertEquals('Shipped via DHL', $history->note);
    }

    #[Test]
    public function admin_can_add_note_to_order()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.add-note', $this->order), [
                'note' => 'Customer requested faster shipping',
            ]);

        $response->assertRedirect(route('admin.orders.show', $this->order));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('order_notes', [
            'order_id' => $this->order->id,
            'admin_id' => $this->admin->id,
            'note' => 'Customer requested faster shipping',
        ]);
    }

    #[Test]
    public function admin_can_update_tracking_information()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.update-tracking', $this->order), [
                'tracking_number' => 'TRK123456789',
                'carrier' => 'DHL',
                'note' => 'Tracking provided',
                'notify_customer' => false,
            ]);

        $response->assertRedirect(route('admin.orders.show', $this->order));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'tracking_number' => 'TRK123456789',
            'carrier' => 'DHL',
        ]);

        $this->assertDatabaseHas('order_notes', [
            'order_id' => $this->order->id,
            'admin_id' => $this->admin->id,
            'note' => 'Tracking updated: Tracking provided',
        ]);
    }

    #[Test]
    public function admin_can_export_orders_to_csv()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename="orders_' . date('Y-m-d_H-i') . '.csv"');
    }

    #[Test]
    public function non_admin_cannot_access_order_management()
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        // Try to access orders list
        $response = $this->actingAs($user)
            ->get(route('admin.orders.index'));

        $response->assertRedirect(route('admin.login'));

        // Try to update order status
        $response = $this->actingAs($user)
            ->post(route('admin.orders.update-status', $this->order));

        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function guest_cannot_access_order_management()
    {
        $response = $this->get(route('admin.orders.index'));
        $response->assertRedirect(route('admin.login'));

        $response = $this->get(route('admin.orders.show', $this->order));
        $response->assertRedirect(route('admin.login'));
    }

    #[Test]
    public function email_is_sent_when_notify_customer_is_selected()
    {
        \Illuminate\Support\Facades\Queue::fake();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.update-status', $this->order), [
                'status'          => \App\Enums\OrderStatus::Shipped->value,
                'note'            => 'Your order has shipped',
                'notify_customer' => true,
            ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendOrderStatusEmail::class);
    }

    #[Test]
    public function order_status_cannot_be_changed_to_invalid_value()
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.orders.update-status', $this->order), [
                'status' => 'invalid_status',
                'note' => 'Test',
                'notify_customer' => false,
            ]);

        $response->assertSessionHasErrors('status');
    }
}
