<?php

namespace Tests\Unit;

use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Order;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(InvoiceService::class);

        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $this->order = Order::factory()->create([
            'user_id'               => $user->id,
            'order_number'          => 'ORD-TEST-001',
            'invoice_number'        => 'INV-TEST-001',
            'shipping_name'         => 'John Doe',
            'shipping_address_line1'=> '123 Main St',
            'shipping_city'         => 'Berlin',
            'shipping_postal_code'  => '10115',
            'shipping_country_code' => 'DE',
        ]);
    }

    #[Test]
    public function generate_returns_a_pdf_instance(): void
    {
        $pdf = $this->service->generate($this->order, false, true);

        $this->assertInstanceOf(\Barryvdh\DomPDF\PDF::class, $pdf);
    }

    #[Test]
    public function generate_produces_non_empty_pdf_output(): void
    {
        $pdf = $this->service->generate($this->order, false, true);

        $this->assertNotEmpty($pdf->output());
    }

    #[Test]
    public function generate_pdf_output_begins_with_pdf_header(): void
    {
        $pdf = $this->service->generate($this->order, false, true);
        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF', $output);
    }

    #[Test]
    public function generate_skips_authorization_when_flag_is_set(): void
    {
        // Calling without an authenticated user should not throw when skipAuthorization = true
        $pdf = $this->service->generate($this->order, skipAuthorization: true);

        $this->assertNotNull($pdf);
    }

    #[Test]
    public function generate_download_returns_http_response(): void
    {
        $response = $this->service->generate($this->order, download: true, skipAuthorization: true);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }
}
