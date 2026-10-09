<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\InvoicePaymentMethod;
use App\Enums\SettingType;
use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\CustomInvoiceResource\Pages\ListCustomInvoices;
use App\Mail\CustomInvoiceMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\Setting;
use App\Services\CustomInvoiceService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quotation -> proforma -> invoice, and credit notes: each type has its own number
 * series, its own PDF wording, and only invoices/proformas ask to be paid.
 */
class CustomInvoiceDocumentTypesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

        foreach (['bank_name' => 'SEB', 'bank_iban' => 'LT601010012345678901', 'bank_bic' => 'CBVILT2X', 'bank_account_holder' => 'UAB OeParts'] as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }
        Cache::flush();
    }

    private function document(InvoiceDocumentType $type = InvoiceDocumentType::Invoice, array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'document_type' => $type,
            'invoice_number' => 'TEST-'.$type->value.'-'.++$this->seq,
            'status' => CustomInvoiceStatus::Sent,
            'client_name' => 'Tomoko Spivey', 'client_email' => 'tomoko@example.com',
            'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka', 'client_country_code' => 'JP',
            'currency' => 'EUR', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['description' => 'Parts kit', 'part_number' => 'A2024101247', 'quantity' => 2, 'unit_price' => 100]],
            'vat_rate' => 21, 'po_number' => 'PO-9',
        ], $overrides));
    }

    private function html(CustomInvoice $document): string
    {
        return view('pdf.custom-invoice', app(CustomInvoiceService::class)->viewData($document))->render();
    }

    // ---- numbering -------------------------------------------------------------------

    #[Test]
    public function each_document_type_draws_from_its_own_number_series(): void
    {
        $service = app(CustomInvoiceService::class);

        $quote = $service->nextNumber(InvoiceDocumentType::Quote);
        $proforma = $service->nextNumber(InvoiceDocumentType::Proforma);
        $invoice = $service->nextNumber(InvoiceDocumentType::Invoice);
        $credit = $service->nextNumber(InvoiceDocumentType::CreditNote);

        $this->assertStringStartsWith('QUO-', $quote);
        $this->assertStringStartsWith('PRO-', $proforma);
        $this->assertStringStartsWith('INV-', $invoice);
        $this->assertStringStartsWith('CRN-', $credit);
        $this->assertStringEndsWith('-000001', $quote);
        $this->assertStringEndsWith('-000002', $service->nextNumber(InvoiceDocumentType::Quote), 'a series counts up on its own');
    }

    #[Test]
    public function the_form_creates_a_quotation_with_a_quotation_number(): void
    {
        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'document_type' => 'quote',
                'client_name' => 'Tomoko Spivey', 'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka',
                'client_country_code' => 'JP', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
                'currency' => 'EUR', 'items' => [['description' => 'Parts kit', 'quantity' => 1, 'unit_price' => 100]], 'vat_rate' => 21,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $quote = CustomInvoice::firstOrFail();
        $this->assertSame(InvoiceDocumentType::Quote, $quote->document_type);
        $this->assertStringStartsWith('QUO-', $quote->invoice_number);
        $this->assertSame(CustomInvoiceStatus::Draft, $quote->status);
    }

    // ---- conversions -----------------------------------------------------------------

    #[Test]
    public function a_quotation_converts_to_a_proforma_and_then_an_invoice_keeping_its_lines_and_links(): void
    {
        $service = app(CustomInvoiceService::class);
        $quote = $this->document(InvoiceDocumentType::Quote);

        $proforma = $service->convert($quote, InvoiceDocumentType::Proforma);
        $this->assertSame(InvoiceDocumentType::Proforma, $proforma->document_type);
        $this->assertStringStartsWith('PRO-', $proforma->invoice_number);
        $this->assertSame($quote->id, $proforma->parent_id);
        $this->assertSame(CustomInvoiceStatus::Draft, $proforma->status);
        $this->assertSame((string) $quote->total, (string) $proforma->total);
        $this->assertSame('A2024101247', $proforma->items[0]['part_number']);
        $this->assertSame(CustomInvoiceStatus::Accepted, $quote->refresh()->status, 'converting means the client said yes');

        $proforma->forceFill(['status' => CustomInvoiceStatus::Sent])->save();
        $invoice = $service->convert($proforma, InvoiceDocumentType::Invoice);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertSame($proforma->id, $invoice->parent_id);
    }

    #[Test]
    public function impossible_conversions_are_refused(): void
    {
        $service = app(CustomInvoiceService::class);

        $this->expectException(\RuntimeException::class);
        $service->convert($this->document(InvoiceDocumentType::Invoice), InvoiceDocumentType::Quote);
    }

    #[Test]
    public function a_cancelled_or_declined_quotation_cannot_be_converted(): void
    {
        $service = app(CustomInvoiceService::class);

        $this->assertSame([], $service->conversionTargets($this->document(InvoiceDocumentType::Quote, ['status' => CustomInvoiceStatus::Declined])));
        $this->assertSame([], $service->conversionTargets($this->document(InvoiceDocumentType::Quote, ['status' => CustomInvoiceStatus::Cancelled])));
        $this->assertSame([InvoiceDocumentType::Proforma, InvoiceDocumentType::Invoice], $service->conversionTargets($this->document(InvoiceDocumentType::Quote)));
    }

    // ---- credit notes ------------------------------------------------------------------

    #[Test]
    public function a_credit_note_is_a_linked_draft_with_the_same_lines_and_no_payment_block(): void
    {
        $invoice = $this->document();

        $note = app(CustomInvoiceService::class)->issueCreditNote($invoice, 'Goods returned');

        $this->assertSame(InvoiceDocumentType::CreditNote, $note->document_type);
        $this->assertStringStartsWith('CRN-', $note->invoice_number);
        $this->assertSame($invoice->id, $note->parent_id);
        $this->assertSame(CustomInvoiceStatus::Draft, $note->status);
        $this->assertSame((string) $invoice->total, (string) $note->total);
        $this->assertSame(InvoicePaymentMethod::None, $note->payment_method);
        $this->assertStringContainsString('Goods returned', (string) $note->notes);
        $this->assertStringContainsString($invoice->invoice_number, (string) $note->notes);
        $this->assertSame(CustomInvoiceStatus::Sent, $invoice->refresh()->status, 'the invoice itself is never altered');
    }

    #[Test]
    public function a_credit_note_needs_an_issued_invoice(): void
    {
        $service = app(CustomInvoiceService::class);

        foreach ([
            $this->document(InvoiceDocumentType::Invoice, ['status' => CustomInvoiceStatus::Draft]),
            $this->document(InvoiceDocumentType::Invoice, ['status' => CustomInvoiceStatus::Cancelled]),
            $this->document(InvoiceDocumentType::Quote),
        ] as $notIssued) {
            try {
                $service->issueCreditNote($notIssued);
                $this->fail('expected a refusal for a '.$notIssued->document_type->value.' that is '.$notIssued->status->value);
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function duplicating_makes_a_new_numbered_draft_of_the_same_type(): void
    {
        $original = $this->document(InvoiceDocumentType::Proforma);

        $copy = app(CustomInvoiceService::class)->duplicate($original);

        $this->assertNotSame($original->invoice_number, $copy->invoice_number);
        $this->assertStringStartsWith('PRO-', $copy->invoice_number);
        $this->assertSame(CustomInvoiceStatus::Draft, $copy->status);
        $this->assertNull($copy->parent_id);
        $this->assertSame($original->items, $copy->items);
        $this->assertNull($copy->sent_at);
    }

    // ---- what each PDF says --------------------------------------------------------------

    #[Test]
    public function a_quotation_pdf_is_titled_says_valid_until_and_asks_for_no_payment(): void
    {
        $html = $this->html($this->document(InvoiceDocumentType::Quote));

        $this->assertStringContainsString('QUOTATION', $html);
        $this->assertStringContainsString('Valid until', $html);
        $this->assertStringContainsString('Prepared For', $html);
        $this->assertStringContainsString('not an invoice', $html);
        $this->assertStringNotContainsString('IBAN', $html);
    }

    #[Test]
    public function a_proforma_pdf_is_not_a_tax_invoice_but_does_carry_the_bank_details(): void
    {
        $html = $this->html($this->document(InvoiceDocumentType::Proforma));

        $this->assertStringContainsString('PROFORMA INVOICE', $html);
        $this->assertStringContainsString('not a tax invoice', $html);
        $this->assertStringContainsString('Pay before', $html);
        $this->assertStringContainsString('LT601010012345678901', $html);
    }

    #[Test]
    public function a_credit_note_pdf_shows_negative_amounts_the_original_number_and_no_due_date_or_payment(): void
    {
        $invoice = $this->document();
        $note = app(CustomInvoiceService::class)->issueCreditNote($invoice);
        $html = $this->html($note->refresh());

        $this->assertStringContainsString('CREDIT NOTE', $html);
        $this->assertStringContainsString('Credit for', $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);
        $this->assertStringContainsString('-€242.00', $html, 'total of 2 × 100 + 21% VAT, shown as a credit');
        $this->assertStringNotContainsString('>Due<', $html);
        $this->assertStringNotContainsString('IBAN', $html);
    }

    #[Test]
    public function an_ordinary_invoice_pdf_is_unchanged_in_substance(): void
    {
        $html = $this->html($this->document());

        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('QUOTATION', $html);
        $this->assertStringContainsString('€242.00', $html);
        $this->assertStringNotContainsString('-€242.00', $html);
        $this->assertStringContainsString('LT601010012345678901', $html);
    }

    #[Test]
    public function the_email_is_named_for_the_document_type(): void
    {
        $quote = $this->document(InvoiceDocumentType::Quote);

        $mail = new CustomInvoiceMail($quote, 'pdf');

        $this->assertStringContainsString('Quotation', $mail->envelope()->subject);
        $this->assertStringContainsString('Quotation', $mail->render());
        $this->assertSame('quotation-'.$quote->invoice_number.'.pdf', app(CustomInvoiceService::class)->filename($quote));
    }

    // ---- the admin list ---------------------------------------------------------------------

    #[Test]
    public function the_list_offers_the_actions_that_fit_each_document(): void
    {
        $quote = $this->document(InvoiceDocumentType::Quote);
        $invoice = $this->document(InvoiceDocumentType::Invoice);

        Livewire::test(ListCustomInvoices::class)
            ->assertTableActionVisible('convertToInvoice', $quote)
            ->assertTableActionVisible('acceptQuote', $quote)
            ->assertTableActionHidden('markPaid', $quote)
            ->assertTableActionHidden('convertToInvoice', $invoice)
            ->assertTableActionVisible('issueCreditNote', $invoice)
            ->assertTableActionHidden('issueCreditNote', $quote)
            ->assertTableActionVisible('markPaid', $invoice);
    }

    #[Test]
    public function converting_from_the_list_creates_the_draft(): void
    {
        $quote = $this->document(InvoiceDocumentType::Quote);

        Livewire::test(ListCustomInvoices::class)->callTableAction('convertToInvoice', $quote);

        $invoice = CustomInvoice::where('parent_id', $quote->id)->firstOrFail();
        $this->assertSame(InvoiceDocumentType::Invoice, $invoice->document_type);
    }
}
