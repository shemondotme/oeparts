<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Events\OrderPlaced;
use App\Jobs\SendBankTransferInstructionsEmail;
use App\Jobs\SendOrderConfirmationEmail;
use App\Mail\BankTransferInstructions;
use App\Mail\OrderConfirmation;
use App\Models\InvoiceBankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The order → payment → email/invoice contract:
 *  - one confirmation per order, sent when the money is confirmed;
 *  - bank-transfer orders get payment instructions at once, the confirmation
 *    (with the invoice) only after the transfer arrives;
 *  - the invoice number exists the moment an order is paid and the cached PDF
 *    can never show a stale one.
 */
class OrderPaymentEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    private function bankAccount(): void
    {
        InvoiceBankAccount::create([
            'label' => 'Main', 'currency' => 'EUR', 'account_holder' => 'UAB OeParts', 'bank_name' => 'SEB',
            'iban' => 'LT601010012345678901', 'bic' => 'CBVILT2X', 'is_active' => true, 'sort_order' => 0,
        ]);
    }

    private function pendingOrder(PaymentMethod $method): Order
    {
        return Order::factory()->create([
            'status' => OrderStatus::Pending,
            'payment_method' => $method,
            'invoice_number' => null,
        ]);
    }

    #[Test]
    public function placing_a_bank_transfer_order_sends_payment_instructions_not_a_confirmation(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);

        OrderPlaced::dispatch($order);

        Queue::assertPushed(SendBankTransferInstructionsEmail::class, fn ($job) => $job->order->is($order));
        Queue::assertNotPushed(SendOrderConfirmationEmail::class);
    }

    #[Test]
    public function placing_a_card_order_sends_nothing_until_the_payment_is_confirmed(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMethod::Card);

        OrderPlaced::dispatch($order);

        Queue::assertNotPushed(SendOrderConfirmationEmail::class);
        Queue::assertNotPushed(SendBankTransferInstructionsEmail::class);
    }

    #[Test]
    public function an_admin_created_order_is_confirmed_with_its_invoice_straight_away(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);

        OrderPlaced::dispatch($order, attachInvoice: true);

        Queue::assertPushed(SendOrderConfirmationEmail::class, fn ($job) => $job->attachInvoice === true);
        Queue::assertNotPushed(SendBankTransferInstructionsEmail::class);
    }

    #[Test]
    public function a_confirmed_bank_transfer_gets_exactly_one_confirmation_and_an_invoice_number(): void
    {
        Queue::fake();
        $this->bankAccount();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);

        // Placement (instructions) then the transfer landing (confirmation).
        OrderPlaced::dispatch($order);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'gateway' => PaymentGateway::BankTransfer]);
        app(PaymentService::class)->confirmBankTransferPayment($payment, 'REF-1', null);

        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNotEmpty($order->invoice_number);
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{6}$/', $order->invoice_number);

        Queue::assertPushedTimes(SendOrderConfirmationEmail::class, 1);
        Queue::assertPushed(SendOrderConfirmationEmail::class, fn ($job) => $job->attachInvoice === true);
    }

    #[Test]
    public function a_succeeded_card_payment_gets_an_invoice_number_and_one_confirmation_with_the_invoice(): void
    {
        Queue::fake();
        $order = $this->pendingOrder(PaymentMethod::Card);
        Payment::factory()->create([
            'order_id' => $order->id, 'gateway' => PaymentGateway::Airwallex, 'transaction_id' => 'pi_flow_1',
        ]);

        OrderPlaced::dispatch($order);
        app(PaymentService::class)->processSuccessfulPayment([
            'id' => 'evt_flow_1', 'data' => ['object' => ['id' => 'pi_flow_1']],
        ]);

        $this->assertNotEmpty($order->refresh()->invoice_number);
        Queue::assertPushedTimes(SendOrderConfirmationEmail::class, 1);
        Queue::assertPushed(SendOrderConfirmationEmail::class, fn ($job) => $job->attachInvoice === true);
    }

    #[Test]
    public function the_order_email_jobs_wait_for_the_surrounding_transaction_to_commit(): void
    {
        // Dispatched from inside the order/payment transaction; a Redis worker that
        // runs one before the commit renders the mail from the pre-payment order
        // (no invoice number) or cannot find a brand-new order at all. Seen live:
        // the confirmation went out without its "Invoice no." line.
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);

        config(['queue.default' => 'redis']);
        foreach ([new SendOrderConfirmationEmail($order), new SendBankTransferInstructionsEmail($order)] as $job) {
            $this->assertTrue((bool) $job->afterCommit, $job::class.' must be dispatched after commit on a real queue');
        }

        // The sync connection runs inline inside the transaction; deferring would
        // only move a mail failure out of the caller's try/catch.
        config(['queue.default' => 'sync']);
        $this->assertFalse((bool) (new SendOrderConfirmationEmail($order))->afterCommit);
    }

    #[Test]
    public function the_invoice_number_is_assigned_once_and_never_changes(): void
    {
        $order = $this->pendingOrder(PaymentMethod::Card);
        $service = app(OrderService::class);

        $first = $service->ensureInvoiceNumber($order);
        $second = $service->ensureInvoiceNumber($order->fresh());

        $this->assertNotEmpty($first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $order->fresh()->invoice_number);
    }

    #[Test]
    public function the_bank_transfer_page_and_email_share_one_pending_payment_row(): void
    {
        $this->bankAccount();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);
        $payments = app(PaymentService::class);

        $payments->getBankTransferDetails($order);
        $payments->getBankTransferDetails($order);
        $details = $payments->getBankTransferDetails($order);

        $this->assertSame(1, Payment::where('order_id', $order->id)->where('gateway', PaymentGateway::BankTransfer)->count());
        $this->assertSame(Payment::where('order_id', $order->id)->value('id'), $details['payment_id']);
    }

    #[Test]
    public function a_cached_invoice_pdf_is_never_served_after_the_invoice_number_appears(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id, 'status' => OrderStatus::Processing, 'invoice_number' => null,
        ]);
        $invoices = app(InvoiceService::class);

        $beforePath = $invoices->saveToStorage($order);
        app(OrderService::class)->ensureInvoiceNumber($order);

        $order->refresh();
        $this->assertNotSame($beforePath, $invoices->cachePath($order));
        $this->assertFalse($invoices->exists($order), 'the pre-number PDF must not be found for the numbered order');

        $this->actingAs($user, 'web');
        $this->assertSame(200, $invoices->download($order)->getStatusCode());
        $this->assertTrue($invoices->exists($order));
    }

    #[Test]
    public function the_confirmation_attaches_the_invoice_and_links_a_signed_download(): void
    {
        $this->bankAccount();
        $order = Order::factory()->create([
            'status' => OrderStatus::Processing, 'invoice_number' => 'INV-202610-000042',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $mail = new OrderConfirmation($order->fresh(), 'en', true, '%PDF-fake');
        $html = $mail->render();

        $this->assertStringContainsString('INV-202610-000042', $html);
        $this->assertStringContainsString('signature=', $html);
        $this->assertCount(1, $mail->attachments());
    }

    #[Test]
    public function the_signed_invoice_link_works_without_login_and_rejects_tampering(): void
    {
        Storage::fake('local');
        $this->bankAccount();
        $order = Order::factory()->create([
            'status' => OrderStatus::Processing, 'invoice_number' => 'INV-202610-000043',
        ]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $url = URL::temporarySignedRoute('frontend.order.invoice.signed', now()->addDay(), ['lang' => 'en', 'order' => $order->id]);

        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('frontend.order.invoice.signed', ['lang' => 'en', 'order' => $order->id]))->assertForbidden();
        $this->get($url.'x')->assertForbidden();
    }

    #[Test]
    public function the_signed_invoice_link_404s_for_an_order_with_no_invoice_number(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Pending, 'invoice_number' => null]);

        $url = URL::temporarySignedRoute('frontend.order.invoice.signed', now()->addDay(), ['lang' => 'en', 'order' => $order->id]);

        $this->get($url)->assertNotFound();
    }

    #[Test]
    public function the_bank_transfer_instructions_email_carries_the_iban_reference_and_deadline(): void
    {
        $this->bankAccount();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);
        OrderItem::factory()->create(['order_id' => $order->id]);
        $bank = app(PaymentService::class)->getBankTransferDetails($order);

        $html = (new BankTransferInstructions($order->fresh(), $bank))->render();

        $this->assertStringContainsString('LT60 1010 0123 4567 8901', $html);
        $this->assertStringContainsString($bank['reference'], $html);
        $this->assertStringContainsString('UAB OeParts', $html);
        $this->assertStringNotContainsString('processing it now', $html);
    }

    #[Test]
    public function the_instructions_job_sends_one_mail_and_skips_orders_that_no_longer_need_paying(): void
    {
        Mail::fake();
        $this->bankAccount();
        $order = $this->pendingOrder(PaymentMethod::BankTransfer);
        $order->update(['guest_email' => 'buyer@example.com', 'user_id' => null]);

        (new SendBankTransferInstructionsEmail($order->fresh()))->handle(app(PaymentService::class));
        Mail::assertSent(BankTransferInstructions::class, 1);

        $order->update(['status' => OrderStatus::Cancelled]);
        (new SendBankTransferInstructionsEmail($order->fresh()))->handle(app(PaymentService::class));
        Mail::assertSent(BankTransferInstructions::class, 1);
    }
}
