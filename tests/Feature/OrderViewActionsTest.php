<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendTrackingUpdateEmail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderViewActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    private function orderPage(Order $order)
    {
        return Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
    }

    #[Test]
    public function the_confirmation_can_be_resent_with_the_invoice_attached(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'buyer@example.com', 'invoice_number' => null]);

        $this->orderPage($order)->callAction('resendConfirmation', ['attach_invoice' => true])->assertNotified();

        Queue::assertPushed(SendOrderConfirmationEmail::class, fn (SendOrderConfirmationEmail $j) => $j->attachInvoice === true);
        $this->assertNotEmpty($order->refresh()->invoice_number);
        $this->assertDatabaseHas('order_notes', ['order_id' => $order->id]);
    }

    #[Test]
    public function tracking_info_can_be_sent_from_the_order_page(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Processing]);

        $this->orderPage($order)->callAction('sendTracking', ['tracking_number' => 'DHL-123', 'carrier_id' => null])->assertNotified();

        Queue::assertPushed(SendTrackingUpdateEmail::class);
        $this->assertSame('DHL-123', $order->refresh()->tracking_number);
    }

    #[Test]
    public function the_packing_slip_downloads_as_a_pdf_without_prices(): void
    {
        $order = Order::factory()->create();

        $response = $this->get(route('admin.orders.packing-slip', ['order' => $order]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $html = view('pdf.packing-slip', ['order' => $order->load('items.product'), 'settings' => ['company_name' => 'X', 'company_email' => '', 'company_phone' => '']])->render();
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringNotContainsString('Unit price', $html);
    }

    #[Test]
    public function the_packing_slip_action_redirects_to_the_download_instead_of_spa_linking_to_it(): void
    {
        $order = Order::factory()->create();

        $this->orderPage($order)
            ->callAction('printPackingSlip')
            ->assertRedirect(route('admin.orders.packing-slip', ['order' => $order]));
    }

    #[Test]
    public function an_order_can_be_marked_paid_and_back_to_unpaid_with_a_note(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::Card,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->orderPage($order)->callAction('markPaid', ['reason' => 'Cash at the counter']);
        $this->assertSame(PaymentStatus::Paid, $order->refresh()->payment_status);

        $this->orderPage($order)->callAction('markUnpaid', ['reason' => 'Clicked by mistake']);
        $this->assertSame(PaymentStatus::Pending, $order->refresh()->payment_status);
        $this->assertSame(2, $order->notes()->count());
    }

    #[Test]
    public function mark_paid_is_not_offered_for_a_pending_bank_transfer_which_has_its_own_button(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_status' => PaymentStatus::Pending,
        ]);

        $this->orderPage($order)->assertActionHidden('markPaid');
    }

    #[Test]
    public function an_order_can_be_cancelled_with_a_reason(): void
    {
        Queue::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        $this->orderPage($order)->callAction('cancelOrder', ['reason' => 'Customer asked', 'notify_customer' => false])->assertNotified();

        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
    }

    #[Test]
    public function cancel_is_hidden_once_the_order_has_shipped(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

        $this->orderPage($order)->assertActionHidden('cancelOrder');
    }

    #[Test]
    public function the_customer_actions_only_show_for_registered_customers(): void
    {
        $guest = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.com']);
        $this->orderPage($guest)->assertActionHidden('openCustomer')->assertActionHidden('copyOrderLink');

        $registered = Order::factory()->create(['user_id' => User::factory()->create()->id]);
        $this->orderPage($registered)->assertActionVisible('openCustomer')->assertActionVisible('copyOrderLink');
    }

    #[Test]
    public function duplicating_an_order_prefills_a_new_unsaved_order_with_its_customer_and_items(): void
    {
        $source = Order::factory()->create(['user_id' => null, 'guest_email' => 'dup@example.com', 'shipping_name' => 'Dup Name', 'invoice_number' => 'INV-1', 'tracking_number' => 'T-1']);
        $product = Product::factory()->create();
        OrderItem::create([
            'order_id' => $source->id, 'product_id' => $product->id, 'oem_number_snapshot' => 'X1', 'manufacturer_snapshot' => 'Bosch', 'condition_snapshot' => 'Used',
            'quantity' => 2, 'unit_price' => '10.00', 'total_price' => '20.00',
        ]);

        $before = Order::count();

        Livewire::withQueryParams(['duplicate' => $source->id])
            ->test(CreateOrder::class)
            ->assertFormSet([
                'guest_email' => 'dup@example.com',
                'shipping_name' => 'Dup Name',
                'invoice_number' => null,
                'tracking_number' => null,
            ])
            ->assertFormSet(fn (array $state) => $state['line_items'] !== [] && (int) array_values($state['line_items'])[0]['quantity'] === 2);

        $this->assertSame($before, Order::count());
    }
}
