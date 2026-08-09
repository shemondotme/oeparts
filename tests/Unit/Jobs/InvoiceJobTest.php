<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateInvoicePdf;
use App\Models\Order;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GenerateInvoicePdf used to build its own save path from
 * "invoices/{Y}/{m}/{invoice_number}.pdf" — but this job is dispatched at
 * order creation (CheckoutService::createOrder()), before invoice_number is
 * ever set (it's only assigned once an order reaches Paid status), and it
 * wrote to a path InvoiceService::exists()/getFromStorage() never checked.
 * Every pending order placed in the same month collided on the exact same
 * "invoices/{Y}/{m}/.pdf" path, each overwriting the last. The job now
 * delegates to InvoiceService::saveToStorage(), which keys the file on the
 * order's order_number — unique and always set from the moment the order
 * exists.
 */
class InvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function generate_invoice_job_is_queued_on_default(): void
    {
        Bus::fake();
        $order = Order::factory()->create();

        dispatch(new GenerateInvoicePdf($order));

        Bus::assertDispatched(GenerateInvoicePdf::class);
    }

    #[Test]
    public function generate_invoice_job_creates_pdf_file(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-2024-001']);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-2024-001.pdf');
    }

    #[Test]
    public function generated_pdf_file_has_content(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-TEST-001']);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        $path = 'invoices/ORD-TEST-001.pdf';
        Storage::disk('local')->assertExists($path);

        $content = Storage::disk('local')->get($path);
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    #[Test]
    public function invoice_is_keyed_on_order_number_regardless_of_invoice_number(): void
    {
        // invoice_number is deliberately left unset here — that's the normal
        // state at order-creation time, when this job actually runs.
        $order = Order::factory()->create([
            'order_number' => 'ORD-456',
            'invoice_number' => null,
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-456.pdf');
    }

    #[Test]
    public function invoice_is_stored_flat_under_the_invoices_directory(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-PATH-TEST']);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-PATH-TEST.pdf');
    }

    #[Test]
    public function generate_invoice_for_order_with_items(): void
    {
        $order = Order::factory()->create(['order_number' => 'ORD-ITEMS-001']);

        $order->items()->createMany([
            [
                'product_id' => null,
                'oem_number_snapshot' => 'OEM001',
                'manufacturer_snapshot' => 'VW',
                'condition_snapshot' => 'new',
                'quantity' => 2,
                'unit_price' => '50.00',
                'total_price' => '100.00',
            ],
            [
                'product_id' => null,
                'oem_number_snapshot' => 'OEM002',
                'manufacturer_snapshot' => 'VW',
                'condition_snapshot' => 'new',
                'quantity' => 1,
                'unit_price' => '100.00',
                'total_price' => '100.00',
            ],
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-ITEMS-001.pdf');
    }

    #[Test]
    public function generate_invoice_for_guest_order(): void
    {
        $order = Order::factory()->guest()->create(['order_number' => 'ORD-GUEST-001']);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-GUEST-001.pdf');
    }

    #[Test]
    public function generate_invoice_for_order_with_shipping_address(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-ADDR-001',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country_code' => 'DE',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('local')->assertExists('invoices/ORD-ADDR-001.pdf');
    }

    #[Test]
    public function invoice_job_can_handle_multiple_concurrent_orders_without_collision(): void
    {
        $orders = [];
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create();
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'order_number' => "ORD-CONCURRENT-00{$i}",
            ]);
            $orders[] = $order;
        }

        foreach ($orders as $order) {
            $job = new GenerateInvoicePdf($order);
            $job->handle(app(InvoiceService::class));
        }

        foreach ($orders as $order) {
            Storage::disk('local')->assertExists("invoices/{$order->order_number}.pdf");
        }
    }

    #[Test]
    public function invoice_job_uses_invoice_service(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_number' => 'ORD-SERVICE-001',
        ]);

        $invoiceService = app(InvoiceService::class);
        $job = new GenerateInvoicePdf($order);
        $job->handle($invoiceService);

        Storage::disk('local')->assertExists('invoices/ORD-SERVICE-001.pdf');
    }
}
