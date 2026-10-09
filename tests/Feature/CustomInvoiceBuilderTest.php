<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceVatTreatment;
use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Services\CustomInvoiceService;
use App\Services\InvoiceCalculator;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The custom-invoice builder v2: per-line part number / unit / discount / VAT rate,
 * percentage or fixed invoice discount, a real VAT treatment (reverse charge,
 * intra-EU supply, export, exempt) and the reference fields printed on the PDF.
 */
class CustomInvoiceBuilderTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    private function calc(array $items, string $treatment = 'standard', mixed $rate = 21, string $type = 'amount', mixed $discount = 0): array
    {
        return app(InvoiceCalculator::class)->calculate($items, $treatment, $rate, $type, $discount);
    }

    private function invoice(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'invoice_number' => 'INV-BLD-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'status' => CustomInvoiceStatus::Sent,
            'client_name' => 'Tomoko Spivey', 'client_company' => 'Sakamoto Engineering Co. Ltd.',
            'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka', 'client_country_code' => 'JP',
            'currency' => 'EUR', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['description' => 'PARTS KIT, VIB. ABSORBER', 'part_number' => 'A2024101247', 'quantity' => 1, 'unit_price' => 296.87]],
            'vat_rate' => 21,
        ], $overrides));
    }

    private function html(CustomInvoice $invoice): string
    {
        return view('pdf.custom-invoice', app(CustomInvoiceService::class)->viewData($invoice))->render();
    }

    // ---- the calculation ------------------------------------------------------

    #[Test]
    public function lines_with_different_vat_rates_and_a_discount_are_taxed_per_rate(): void
    {
        $r = $this->calc([
            ['description' => 'A', 'quantity' => 2, 'unit_price' => '50.00'],                                  // 100.00 @ default 21%
            ['description' => 'B', 'quantity' => 1, 'unit_price' => '200.00', 'discount_percent' => 10, 'vat_rate' => 9], // 180.00 @ 9%
        ], 'standard', 21, 'percent', 10);

        $this->assertSame('280.00', $r['subtotal']);
        $this->assertSame('28.00', $r['discount_amount']);   // 10% of the subtotal
        // discount spread in proportion: 10.00 off the 21% group, 18.00 off the 9% group
        $this->assertEquals([['rate' => '21.00', 'base' => '90.00', 'vat' => '18.90'], ['rate' => '9.00', 'base' => '162.00', 'vat' => '14.58']], $r['breakdown']);
        $this->assertSame('33.48', $r['vat_amount']);
        $this->assertSame('285.48', $r['total']);
    }

    #[Test]
    public function rounding_is_half_up_not_truncated(): void
    {
        // 2.5% of 1.00 = 0.025 -> 0.03 (a truncating calculation gives 0.02)
        $this->assertSame('0.03', $this->calc([['quantity' => 1, 'unit_price' => '1.00']], 'standard', '2.5')['vat_amount']);
        $this->assertSame('2.11', $this->calc([['quantity' => 1, 'unit_price' => '10.05']])['vat_amount']);
    }

    #[Test]
    public function every_non_standard_treatment_is_zero_percent_on_every_line(): void
    {
        foreach (['reverse_charge', 'intra_eu', 'export', 'exempt'] as $treatment) {
            $r = $this->calc([['quantity' => 2, 'unit_price' => '100.00', 'vat_rate' => 21]], $treatment);

            $this->assertSame('0.00', $r['vat_amount'], $treatment);
            $this->assertSame('200.00', $r['total'], $treatment);
            $this->assertSame('0.00', $r['lines'][0]['vat_rate'], 'a line rate is ignored under '.$treatment);
        }
    }

    #[Test]
    public function discounts_are_clamped_and_garbage_input_is_harmless(): void
    {
        $this->assertSame('100.00', $this->calc([['quantity' => 1, 'unit_price' => '100']], 'standard', 0, 'amount', 9999)['discount_amount']);
        $this->assertSame('100.00', $this->calc([['quantity' => 1, 'unit_price' => '100']], 'standard', 0, 'percent', 250)['discount_amount']);

        $r = $this->calc([['quantity' => 'abc', 'unit_price' => '-5', 'discount_percent' => 'x'], ['quantity' => '1,5', 'unit_price' => '10,00']]);
        $this->assertSame('15.00', $r['subtotal'], 'garbage counts as 0, a decimal comma is understood');
    }

    #[Test]
    public function an_empty_invoice_totals_zero_without_dividing_by_zero(): void
    {
        $r = $this->calc([], 'standard', 21, 'percent', 10);

        $this->assertSame('0.00', $r['total']);
        $this->assertSame([], $r['breakdown']);
    }

    // ---- the model ----------------------------------------------------------------

    #[Test]
    public function the_old_reverse_charge_flag_still_means_reverse_charge_and_stays_in_step(): void
    {
        $invoice = $this->invoice(['reverse_charge' => true]);

        $this->assertSame(InvoiceVatTreatment::ReverseCharge, $invoice->vat_treatment);
        $this->assertSame('0.00', (string) $invoice->vat_amount);

        $invoice->update(['vat_treatment' => InvoiceVatTreatment::Export, 'reverse_charge' => false]);
        $invoice->refresh();
        $this->assertSame(InvoiceVatTreatment::Export, $invoice->vat_treatment);
        $this->assertFalse($invoice->reverse_charge);
    }

    #[Test]
    public function the_per_rate_breakdown_is_stored_and_rebuilt_for_older_invoices(): void
    {
        $invoice = $this->invoice();
        $this->assertSame('21.00', $invoice->vat_breakdown[0]['rate']);

        $invoice->forceFill(['vat_breakdown' => null])->saveQuietly();
        $this->assertSame('21.00', $invoice->refresh()->breakdownRows()[0]['rate'], 'computed on the fly when never stored');
    }

    // ---- the PDF --------------------------------------------------------------------

    #[Test]
    public function the_pdf_prints_part_numbers_references_terms_and_never_the_internal_notes(): void
    {
        $html = $this->html($this->invoice([
            'po_number' => 'PO-7788', 'delivery_terms' => 'DAP Tokyo', 'supply_date' => '2026-10-01',
            'terms_text' => 'Title passes on payment.', 'internal_notes' => 'SECRET margin note',
        ]));

        $this->assertStringContainsString('A2024101247', $html);
        $this->assertStringContainsString('PO-7788', $html);
        $this->assertStringContainsString('DAP Tokyo', $html);
        $this->assertStringContainsString('01/10/2026', $html);
        $this->assertStringContainsString('Title passes on payment.', $html);
        $this->assertStringNotContainsString('SECRET margin note', $html);
    }

    #[Test]
    public function an_export_invoice_prints_zero_vat_and_the_export_wording_and_the_admins_own_wording_wins(): void
    {
        $html = $this->html($this->invoice(['vat_treatment' => InvoiceVatTreatment::Export]));
        $this->assertStringContainsString('Article 146', $html);
        $this->assertStringNotContainsString('VAT (21%)', $html);

        $custom = $this->html($this->invoice(['vat_treatment' => InvoiceVatTreatment::Exempt, 'vat_exemption_note' => 'Exempt per customs ref. 123']));
        $this->assertStringContainsString('Exempt per customs ref. 123', $custom);
    }

    #[Test]
    public function an_invoice_with_mixed_vat_rates_prints_one_vat_line_per_rate(): void
    {
        $html = $this->html($this->invoice(['items' => [
            ['description' => 'Parts', 'quantity' => 1, 'unit_price' => 100],
            ['description' => 'Freight', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 9],
        ]]));

        $this->assertStringContainsString('VAT (21% of', $html);
        $this->assertStringContainsString('VAT (9% of', $html);
    }

    #[Test]
    public function a_pre_existing_single_rate_invoice_still_shows_its_vat_line(): void
    {
        $invoice = $this->invoice();
        $invoice->forceFill(['vat_breakdown' => null])->saveQuietly();

        $this->assertStringContainsString('VAT (21%)', $this->html($invoice->refresh()));
    }

    // ---- the form -------------------------------------------------------------------

    #[Test]
    public function an_export_invoice_with_line_level_fields_can_be_created_from_the_form(): void
    {
        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'client_name' => 'Tomoko Spivey', 'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka',
                'client_country_code' => 'JP', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'EUR', 'po_number' => 'PO-1', 'delivery_terms' => 'DAP Tokyo',
                'items' => [
                    ['part_number' => 'A2024101247', 'description' => 'PARTS KIT', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 296.87, 'discount_percent' => 10],
                    ['description' => 'Air freight', 'quantity' => 1, 'unit_price' => 80],
                ],
                'vat_treatment' => 'export', 'discount_type' => 'percent', 'discount_percent' => 5,
                'payment_method' => 'bank_transfer',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = CustomInvoice::firstOrFail();

        // 2 × 296.87 = 593.74 less 10% (59.37) = 534.37, + 80.00 = 614.37; 5% off = 30.72
        $this->assertSame('614.37', (string) $invoice->subtotal);
        $this->assertSame('30.72', (string) $invoice->discount_amount);
        $this->assertSame('0.00', (string) $invoice->vat_amount);
        $this->assertSame('583.65', (string) $invoice->total);
        $this->assertSame(InvoiceVatTreatment::Export, $invoice->vat_treatment);
        $this->assertSame('PO-1', $invoice->po_number);
    }
}
