<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Mail\OrderConfirmation;
use App\Mail\OrderInvoiceMail;
use App\Models\Admin;
use App\Models\Order;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderInvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    #[Test]
    public function the_invoice_mail_renders_and_carries_the_pdf(): void
    {
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com', 'invoice_number' => 'INV-77']);
        $mail = new OrderInvoiceMail($order);

        $this->assertStringContainsString('INV-77', $mail->render());
        $attachments = $mail->attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame("invoice-{$order->order_number}.pdf", $attachments[0]->as);
        $this->assertSame('application/pdf', $attachments[0]->mime);
    }

    #[Test]
    public function the_confirmation_only_carries_the_invoice_when_asked_to(): void
    {
        $order = Order::factory()->create(['guest_email' => 'buyer@example.com']);

        $this->assertCount(0, (new OrderConfirmation($order))->attachments());
        $this->assertCount(1, (new OrderConfirmation($order, 'en', true))->attachments());
        $this->assertStringContainsString('attached', (new OrderConfirmation($order, 'en', true))->render());
    }

    #[Test]
    public function the_admin_can_email_the_invoice_from_the_order_page(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'buyer@example.com', 'invoice_number' => null]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('emailInvoice')
            ->assertNotified('Invoice sent');

        Mail::assertSent(OrderInvoiceMail::class, fn (OrderInvoiceMail $m) => $m->hasTo('buyer@example.com'));
        $this->assertNotEmpty($order->refresh()->invoice_number);
        $this->assertDatabaseHas('order_notes', ['order_id' => $order->id]);
    }

    #[Test]
    public function the_action_is_hidden_when_the_order_has_no_customer_email(): void
    {
        $order = Order::factory()->create(['guest_email' => null, 'user_id' => null]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('emailInvoice');
    }
}
