<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\PartInquiryResource\Pages\ListPartInquiries;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\OrderItem;
use App\Models\PartInquiry;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** A part inquiry turns into a quotation in one click; order lines carry an internal cost and margin. */
class PartInquiryQuoteTest extends TestCase
{
    use RefreshDatabase;

    private PartInquiry $inquiry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

        $this->inquiry = PartInquiry::create([
            'email' => 'buyer@example.com', 'phone' => '+370 600 00000', 'oem_number' => 'A2024101247',
            'manufacturer' => 'Mercedes-Benz', 'car_model' => 'C-Class', 'year' => '2012',
            'vin_number' => 'WDD2040001A000001', 'quantity' => 3, 'urgency' => 'normal', 'notes' => 'Front left please', 'ip_address' => '127.0.0.1',
        ]);
    }

    #[Test]
    public function the_inquiry_list_offers_a_create_quotation_link_to_the_prefilled_form(): void
    {
        Livewire::test(ListPartInquiries::class)
            ->assertTableActionVisible('createQuote', $this->inquiry)
            ->assertTableActionHasUrl('createQuote', route('filament.admin.resources.custom-invoices.create', ['type' => 'quote', 'inquiry' => $this->inquiry->id]));
    }

    #[Test]
    public function the_quotation_form_opens_prefilled_from_the_inquiry_and_saves_as_a_quote(): void
    {
        $page = Livewire::withQueryParams(['type' => 'quote', 'inquiry' => $this->inquiry->id])
            ->test(CreateCustomInvoice::class)
            ->assertFormSet([
                'document_type' => 'quote',
                'client_email' => 'buyer@example.com',
                'client_phone' => '+370 600 00000',
            ]);

        $items = array_values($page->instance()->data['items']);
        $this->assertSame('A2024101247', $items[0]['part_number']);
        $this->assertSame(3, (int) $items[0]['quantity']);
        $this->assertStringContainsString('Mercedes-Benz C-Class 2012', $items[0]['description']);
        $this->assertStringContainsString('VIN WDD2040001A000001', $page->instance()->data['internal_notes']);

        $page->fillForm([
            'client_name' => 'Buyer', 'client_address_line1' => 'Main 1', 'client_city' => 'Vilnius', 'client_country_code' => 'LT',
            'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'currency' => 'EUR',
            'items' => [['description' => 'OEM A2024101247', 'part_number' => 'A2024101247', 'quantity' => 3, 'unit_price' => 80]],
            'vat_rate' => 21,
        ])->call('create')->assertHasNoFormErrors();

        $this->assertSame('quote', CustomInvoice::firstOrFail()->document_type->value);
    }

    #[Test]
    public function an_unknown_inquiry_id_leaves_the_form_empty(): void
    {
        Livewire::withQueryParams(['inquiry' => 999999])
            ->test(CreateCustomInvoice::class)
            ->assertFormSet(['client_email' => null]);
    }

    #[Test]
    public function margin_is_selling_total_minus_cost_and_unknown_without_a_cost(): void
    {
        $line = new OrderItem(['quantity' => 2, 'unit_price' => '100.00', 'total_price' => '200.00', 'cost_price' => '60.00']);
        $this->assertSame('80.00', $line->margin());
        $this->assertSame('40.0', $line->marginPercent());

        $loss = new OrderItem(['quantity' => 1, 'unit_price' => '10.00', 'total_price' => '10.00', 'cost_price' => '12.50']);
        $this->assertSame('-2.50', $loss->margin());

        $unknown = new OrderItem(['quantity' => 1, 'unit_price' => '10.00', 'total_price' => '10.00']);
        $this->assertNull($unknown->margin());
        $this->assertNull($unknown->marginPercent());

        $free = new OrderItem(['quantity' => 1, 'unit_price' => '0.00', 'total_price' => '0.00', 'cost_price' => '5.00']);
        $this->assertSame('-5.00', $free->margin());
        $this->assertNull($free->marginPercent());
    }
}
