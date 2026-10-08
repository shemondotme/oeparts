<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SettingType;
use App\Filament\Resources\CustomInvoiceResource;
use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\CustomInvoiceResource\Pages\EditCustomInvoice;
use App\Filament\Resources\CustomInvoiceResource\Pages\ListCustomInvoices;
use App\Mail\CustomInvoiceMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\Order;
use App\Models\Setting;
use App\Policies\CustomInvoicePolicy;
use App\Services\CustomInvoiceService;
use App\Services\InvoiceService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Stand-alone ("custom") invoices + the bank-transfer block on every invoice.
 */
class CustomInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesSeeder::class]);

        $this->admin = Admin::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');

        $this->setBank();
    }

    private function setBank(array $overrides = []): void
    {
        foreach (array_merge([
            'bank_name' => 'SEB Bankas',
            'bank_iban' => 'LT601010012345678901',
            'bank_bic' => 'CBVILT2X',
            'bank_account_holder' => 'UAB OeParts Europe',
        ], $overrides) as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }

        Cache::flush();
    }

    private function invoice(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'invoice_number' => 'INV-TEST-000001',
            'status' => CustomInvoiceStatus::Draft,
            'client_name' => 'Jonas Jonaitis',
            'client_company' => 'UAB Klientas',
            'client_email' => 'client@example.com',
            'client_address_line1' => 'Gedimino pr. 1',
            'client_city' => 'Vilnius',
            'client_postal_code' => '01103',
            'client_country_code' => 'LT',
            'currency' => 'EUR',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'items' => [
                ['description' => 'Brake discs (pair)', 'quantity' => 2, 'unit_price' => 50.10],
                ['description' => 'Freight', 'quantity' => 1, 'unit_price' => 20],
            ],
            'discount_amount' => 10,
            'vat_rate' => 21,
        ], $overrides));
    }

    #[Test]
    public function totals_are_computed_server_side_from_the_line_items(): void
    {
        $invoice = $this->invoice();

        // 2 × 50.10 + 20 = 120.20; − 10 discount = 110.20; 21% VAT = 23.14
        $this->assertSame('120.20', (string) $invoice->subtotal);
        $this->assertSame('23.14', (string) $invoice->vat_amount);
        $this->assertSame('133.34', (string) $invoice->total);
    }

    #[Test]
    public function reverse_charge_zeroes_vat_and_a_discount_cannot_exceed_the_subtotal(): void
    {
        $invoice = $this->invoice(['reverse_charge' => true, 'discount_amount' => 9999]);

        $this->assertSame('0.00', (string) $invoice->vat_amount);
        $this->assertSame('120.20', (string) $invoice->discount_amount);
        $this->assertSame('0.00', (string) $invoice->total);
    }

    #[Test]
    public function an_admin_can_create_a_draft_that_takes_the_next_invoice_number(): void
    {
        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'client_name' => 'Jonas Jonaitis',
                'client_address_line1' => 'Gedimino pr. 1',
                'client_city' => 'Vilnius',
                'client_country_code' => 'LT',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'EUR',
                'items' => [['description' => 'Turbo', 'quantity' => 1, 'unit_price' => 100]],
                'vat_rate' => 21,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = CustomInvoice::firstOrFail();

        $this->assertStringStartsWith('INV-'.now()->format('Ym').'-', $invoice->invoice_number);
        $this->assertSame(CustomInvoiceStatus::Draft, $invoice->status);
        $this->assertSame($this->admin->id, $invoice->created_by);
        $this->assertSame('121.00', (string) $invoice->total);
    }

    #[Test]
    public function the_pdf_shows_bank_details_until_the_invoice_is_paid(): void
    {
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();

        $html = view('pdf.custom-invoice', $service->viewData($invoice))->render();

        $this->assertStringContainsString('LT601010012345678901', $html);
        $this->assertStringContainsString('CBVILT2X', $html);
        $this->assertStringContainsString('UAB OeParts Europe', $html);
        $this->assertStringContainsString('INV-TEST-000001', $html);

        $invoice->forceFill(['status' => CustomInvoiceStatus::Paid])->save();
        $paidHtml = view('pdf.custom-invoice', $service->viewData($invoice->fresh()))->render();

        $this->assertStringNotContainsString('LT601010012345678901', $paidHtml);
        $this->assertStringStartsWith('%PDF', $service->pdf($invoice)->output());
    }

    #[Test]
    public function the_bank_block_is_omitted_when_no_iban_is_configured(): void
    {
        $this->setBank(['bank_iban' => '']);

        $html = view('pdf.custom-invoice', app(CustomInvoiceService::class)->viewData($this->invoice()))->render();

        $this->assertStringNotContainsString('Payment Details', $html);
        $this->assertNull(app(InvoiceService::class)->bankDetails());
    }

    #[Test]
    public function order_invoices_show_bank_details_only_while_unpaid(): void
    {
        $service = app(InvoiceService::class);

        $unpaid = Order::factory()->create(['payment_status' => PaymentStatus::Pending]);
        $paid = Order::factory()->create(['payment_status' => PaymentStatus::Paid]);

        $unpaidHtml = $service->generate($unpaid, false, true)->getDomPDF()->outputHtml();
        $paidHtml = $service->generate($paid, false, true)->getDomPDF()->outputHtml();

        $this->assertStringContainsString('LT601010012345678901', $unpaidHtml);
        $this->assertStringContainsString('CBVILT2X', $unpaidHtml);
        $this->assertStringContainsString($unpaid->order_number, $unpaidHtml);
        $this->assertStringNotContainsString('LT601010012345678901', $paidHtml);
    }

    #[Test]
    public function sending_emails_the_pdf_and_locks_the_draft(): void
    {
        Mail::fake();
        $invoice = $this->invoice();

        app(CustomInvoiceService::class)->send($invoice);

        Mail::assertSent(CustomInvoiceMail::class, function (CustomInvoiceMail $mail) {
            return $mail->hasTo('client@example.com')
                && count($mail->attachments()) === 1
                && str_contains($mail->render(), 'LT601010012345678901');
        });

        $invoice->refresh();
        $this->assertSame(CustomInvoiceStatus::Sent, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertFalse($invoice->isEditable());
        $this->assertFalse(CustomInvoiceResource::canEdit($invoice));
    }

    #[Test]
    public function sending_without_an_email_or_after_cancelling_is_refused(): void
    {
        Mail::fake();
        $service = app(CustomInvoiceService::class);

        $noEmail = $this->invoice(['client_email' => null]);
        try {
            $service->send($noEmail);
            $this->fail('Expected a RuntimeException for a missing client email.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no client email', $e->getMessage());
        }

        $cancelled = $this->invoice(['invoice_number' => 'INV-TEST-000002', 'status' => CustomInvoiceStatus::Cancelled]);
        $this->expectException(\RuntimeException::class);
        $service->send($cancelled);
    }

    #[Test]
    public function invoices_cannot_be_deleted_and_the_list_page_renders(): void
    {
        $invoice = $this->invoice();

        // Policy-level (super_admin bypasses policies via Gate::before, but the
        // resource exposes no delete action or page at all).
        $this->assertFalse((new CustomInvoicePolicy)->delete($this->admin, $invoice));

        Livewire::test(ListCustomInvoices::class)
            ->assertSuccessful()
            ->loadTable()
            ->assertCanSeeTableRecords([$invoice]);
    }

    #[Test]
    public function a_draft_can_be_edited_and_totals_are_recalculated(): void
    {
        $invoice = $this->invoice(['discount_amount' => 0, 'vat_rate' => 0]);

        Livewire::test(EditCustomInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertSuccessful()
            ->fillForm([
                'items' => [['description' => 'Only item', 'quantity' => 3, 'unit_price' => 10]],
                'vat_rate' => 10,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $invoice->refresh();
        $this->assertSame('30.00', (string) $invoice->subtotal);
        $this->assertSame('3.00', (string) $invoice->vat_amount);
        $this->assertSame('33.00', (string) $invoice->total);
    }

    #[Test]
    public function the_pdf_download_route_is_permission_gated(): void
    {
        $invoice = $this->invoice();

        $this->get(route('admin.custom-invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $noPerms = Admin::factory()->create();
        $this->actingAs($noPerms, 'admin');

        $this->get(route('admin.custom-invoices.pdf', $invoice))->assertForbidden();
    }
}
