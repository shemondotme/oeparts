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

class InvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
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
        $order = Order::factory()->create([
            'invoice_number' => 'INV-2024-001',
            'order_number' => 'ORD-2024-001',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('public')->assertExists('invoices/INV-2024-001.pdf');
    }

    #[Test]
    public function generated_pdf_file_has_content(): void
    {
        $order = Order::factory()->create([
            'invoice_number' => 'INV-TEST-001',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        $path = 'invoices/INV-TEST-001.pdf';
        Storage::disk('public')->assertExists($path);

        $content = Storage::disk('public')->get($path);
        $this->assertNotEmpty($content);
        $this->assertStringStartsWith('%PDF', $content);
    }

    #[Test]
    public function invoice_uses_order_invoice_number_in_filename(): void
    {
        $order = Order::factory()->create([
            'invoice_number' => 'UNIQUE-INV-123',
            'order_number' => 'ORD-456',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('public')->assertExists('invoices/UNIQUE-INV-123.pdf');
        Storage::disk('public')->assertMissing('invoices/ORD-456.pdf');
    }

    #[Test]
    public function invoice_is_stored_in_invoices_subdirectory(): void
    {
        $order = Order::factory()->create([
            'invoice_number' => 'INV-PATH-TEST',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('public')->assertExists('invoices/INV-PATH-TEST.pdf');
    }

    #[Test]
    public function generate_invoice_for_order_with_items(): void
    {
        $order = Order::factory()->create([
            'invoice_number' => 'INV-ITEMS-001',
        ]);

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

        Storage::disk('public')->assertExists('invoices/INV-ITEMS-001.pdf');
    }

    #[Test]
    public function generate_invoice_for_guest_order(): void
    {
        $order = Order::factory()->guest()->create([
            'invoice_number' => 'INV-GUEST-001',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('public')->assertExists('invoices/INV-GUEST-001.pdf');
    }

    #[Test]
    public function generate_invoice_for_order_with_shipping_address(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-ADDR-001',
            'shipping_address_line1' => '123 Main St',
            'shipping_city' => 'Berlin',
            'shipping_postal_code' => '10115',
            'shipping_country_code' => 'DE',
        ]);

        $job = new GenerateInvoicePdf($order);
        $job->handle(app(InvoiceService::class));

        Storage::disk('public')->assertExists('invoices/INV-ADDR-001.pdf');
    }

    #[Test]
    public function invoice_job_can_handle_multiple_concurrent_orders(): void
    {
        $orders = [];
        foreach (range(1, 3) as $i) {
            $user = User::factory()->create();
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'invoice_number' => "INV-CONCURRENT-00{$i}",
            ]);
            $orders[] = $order;
        }

        foreach ($orders as $order) {
            $job = new GenerateInvoicePdf($order);
            $job->handle(app(InvoiceService::class));
        }

        foreach ($orders as $order) {
            Storage::disk('public')->assertExists("invoices/{$order->invoice_number}.pdf");
        }
    }

    #[Test]
    public function invoice_job_uses_invoice_service(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'invoice_number' => 'INV-SERVICE-001',
        ]);

        $invoiceService = app(InvoiceService::class);
        $job = new GenerateInvoicePdf($order);
        $job->handle($invoiceService);

        Storage::disk('public')->assertExists('invoices/INV-SERVICE-001.pdf');
    }
}
