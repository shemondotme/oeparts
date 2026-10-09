<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoicePaymentMethod;
use App\Enums\SettingType;
use App\Filament\Resources\CustomInvoiceResource;
use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\InvoiceBankAccountResource\Pages\CreateInvoiceBankAccount;
use App\Mail\CustomInvoiceMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\InvoiceBankAccount;
use App\Models\Setting;
use App\Services\CustomInvoiceService;
use App\Services\InvoiceService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Custom invoices could only print the one bank account saved in Settings and
 * the form never showed it. Extra accounts (per currency / international) and a
 * per-invoice payment method now exist.
 */
class InvoiceBankAccountTest extends TestCase
{
    use RefreshDatabase;

    // Syntactically valid IBANs (correct mod-97 check digits).
    private const LT_IBAN = 'LT121000011101001000';

    private const DE_IBAN = 'DE89370400440532013000';

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

    private function account(array $overrides = []): InvoiceBankAccount
    {
        return InvoiceBankAccount::create(array_merge([
            'label' => 'Swedbank EUR', 'currency' => 'EUR', 'account_holder' => 'UAB OeParts Europe',
            'bank_name' => 'Swedbank', 'iban' => self::LT_IBAN, 'bic' => 'HABALT22',
        ], $overrides));
    }

    private int $invoiceSeq = 0;

    private function invoice(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'invoice_number' => 'INV-BANK-'.str_pad((string) ++$this->invoiceSeq, 6, '0', STR_PAD_LEFT), 'status' => CustomInvoiceStatus::Sent,
            'client_name' => 'Tomoko Spivey', 'client_email' => 'tomoko@example.com',
            'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka', 'client_country_code' => 'JP',
            'currency' => 'EUR', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'items' => [['description' => 'Parts kit', 'quantity' => 1, 'unit_price' => 296.87]],
            'vat_rate' => 0,
        ], $overrides));
    }

    // ---- IBAN handling -----------------------------------------------------

    #[Test]
    public function the_iban_checksum_catches_a_typo(): void
    {
        $this->assertTrue(InvoiceBankAccount::isValidIban(self::LT_IBAN));
        $this->assertTrue(InvoiceBankAccount::isValidIban('de89 3704 0044 0532 0130 00'));
        $this->assertFalse(InvoiceBankAccount::isValidIban('LT121000011101001001'), 'one wrong digit');
        $this->assertFalse(InvoiceBankAccount::isValidIban('not an iban'));
    }

    #[Test]
    public function an_iban_is_stored_in_canonical_form_and_printed_in_groups_of_four(): void
    {
        $account = $this->account(['iban' => 'lt12 1000 0111 0100 1000']);

        $this->assertSame(self::LT_IBAN, $account->iban);
        $this->assertSame('LT12 1000 0111 0100 1000', $account->formattedIban());
    }

    #[Test]
    public function the_admin_form_rejects_an_iban_with_wrong_check_digits_but_accepts_a_plain_account_number(): void
    {
        Livewire::test(CreateInvoiceBankAccount::class)
            ->fillForm(['label' => 'Bad', 'account_holder' => 'UAB X', 'iban' => 'LT121000011101001001'])
            ->call('create')
            ->assertHasFormErrors(['iban']);

        // A US-style account number is not an IBAN and must still be enterable.
        Livewire::test(CreateInvoiceBankAccount::class)
            ->fillForm(['label' => 'Wise USD', 'currency' => 'USD', 'account_holder' => 'UAB X', 'iban' => '1234567890', 'bic' => 'TRWIUS35'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, InvoiceBankAccount::count());
    }

    // ---- which account is printed -------------------------------------------

    #[Test]
    public function a_chosen_account_wins_then_the_currency_account_then_the_default_account(): void
    {
        $service = app(InvoiceService::class);
        $eur = $this->account();
        $usd = $this->account(['label' => 'Wise USD', 'currency' => 'USD', 'iban' => self::DE_IBAN]);

        $this->assertSame($usd->formattedIban(), $service->bankDetailsFor($usd->id, 'EUR')['iban'], 'explicit choice wins');
        $this->assertSame($eur->formattedIban(), $service->bankDetailsFor(null, 'EUR')['iban'], 'automatic by currency');
        $this->assertSame($usd->formattedIban(), $service->bankDetailsFor(null, 'usd')['iban']);
        $this->assertSame($eur->formattedIban(), $service->bankDetailsFor(null, 'GBP')['iban'], 'no GBP account: the default (first active) account');
    }

    #[Test]
    public function an_inactive_account_is_not_chosen_automatically_but_an_old_invoice_keeps_it(): void
    {
        $account = $this->account(['is_active' => false]);
        $service = app(InvoiceService::class);

        $this->assertSame('LT601010012345678901', $service->bankDetailsFor(null, 'EUR')['iban']);
        $this->assertSame($account->formattedIban(), $service->bankDetailsFor($account->id, 'EUR')['iban']);
    }

    #[Test]
    public function without_any_bank_account_the_form_warns_instead_of_silently_printing_nothing(): void
    {
        Setting::where('group', 'payment')->where('key', 'bank_iban')->update(['value' => '']);
        Cache::flush();

        $this->assertNull(app(InvoiceService::class)->bankDetailsFor(null, 'EUR'));
        $this->assertStringContainsString('no bank account is set up', CustomInvoiceResource::bankPreview(null, 'EUR'));
    }

    #[Test]
    public function the_preview_names_the_account_and_notes_a_currency_fallback(): void
    {
        $this->assertStringContainsString('LT601010012345678901', CustomInvoiceResource::bankPreview(null, 'EUR'));
        $this->assertStringContainsString('no account is set up for USD', CustomInvoiceResource::bankPreview(null, 'USD'));
    }

    // ---- what the PDF / email carry -------------------------------------------

    private function renderPdfView(CustomInvoice $invoice): string
    {
        return view('pdf.custom-invoice', app(CustomInvoiceService::class)->viewData($invoice))->render();
    }

    #[Test]
    public function the_pdf_prints_the_chosen_account_with_swift_intermediary_bank_and_instructions(): void
    {
        $account = $this->account([
            'label' => 'Wise USD', 'currency' => 'USD', 'iban' => self::DE_IBAN,
            'intermediary_bank' => 'Correspondent: DEUTUS33', 'instructions' => 'Charges: OUR',
        ]);

        $html = $this->renderPdfView($this->invoice(['currency' => 'USD', 'bank_account_id' => $account->id]));

        $this->assertStringContainsString('DE89 3704 0044 0532 0130 00', $html);
        $this->assertStringContainsString('HABALT22', $html);
        $this->assertStringContainsString('Correspondent: DEUTUS33', $html);
        $this->assertStringContainsString('Charges: OUR', $html);
        $this->assertStringNotContainsString('LT601010012345678901', $html, 'the other account must not appear');
    }

    #[Test]
    public function a_payment_link_invoice_prints_the_link_and_no_bank_block(): void
    {
        $html = $this->renderPdfView($this->invoice([
            'payment_method' => InvoicePaymentMethod::PaymentLink, 'payment_link_url' => 'https://pay.example.com/abc123',
        ]));

        $this->assertStringContainsString('https://pay.example.com/abc123', $html);
        $this->assertStringNotContainsString('IBAN', $html);
    }

    #[Test]
    public function the_none_method_and_a_paid_invoice_print_no_payment_block_and_free_text_is_printed(): void
    {
        $none = $this->renderPdfView($this->invoice(['payment_method' => InvoicePaymentMethod::None, 'payment_instructions' => 'ignored']));
        $this->assertStringNotContainsString('IBAN', $none);
        $this->assertStringNotContainsString('ignored', $none);

        $paid = $this->renderPdfView($this->invoice(['status' => CustomInvoiceStatus::Paid]));
        $this->assertStringNotContainsString('IBAN', $paid);

        $text = $this->renderPdfView($this->invoice(['payment_method' => InvoicePaymentMethod::Other, 'payment_instructions' => '50% in advance']));
        $this->assertStringContainsString('50% in advance', $text);
    }

    #[Test]
    public function the_email_carries_the_same_account_as_the_pdf(): void
    {
        $account = $this->account(['label' => 'Wise USD', 'currency' => 'USD', 'iban' => self::DE_IBAN]);
        $invoice = $this->invoice(['currency' => 'USD', 'bank_account_id' => $account->id]);

        $html = (new CustomInvoiceMail($invoice, 'pdf-bytes'))->render();

        $this->assertStringContainsString('DE89 3704 0044 0532 0130 00', $html);
        $this->assertStringNotContainsString('LT601010012345678901', $html);
    }

    // ---- the form ----------------------------------------------------------------

    #[Test]
    public function an_invoice_can_be_created_with_a_bank_account_and_instructions(): void
    {
        $account = $this->account();

        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'client_name' => 'Tomoko Spivey', 'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka',
                'client_country_code' => 'JP', 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
                'currency' => 'EUR', 'items' => [['description' => 'Parts kit', 'quantity' => 1, 'unit_price' => 296.87]],
                'vat_rate' => 0, 'payment_method' => 'bank_transfer', 'bank_account_id' => $account->id,
                'payment_instructions' => 'Please add the invoice number.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = CustomInvoice::firstOrFail();
        $this->assertSame(InvoicePaymentMethod::BankTransfer, $invoice->payment_method);
        $this->assertSame($account->id, $invoice->bank_account_id);
        $this->assertSame('Please add the invoice number.', $invoice->payment_instructions);
    }

    #[Test]
    public function a_payment_link_invoice_requires_the_link(): void
    {
        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'client_name' => 'X', 'client_address_line1' => 'a', 'client_city' => 'b', 'client_country_code' => 'LT',
                'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'currency' => 'EUR',
                'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => 1]],
                'payment_method' => 'payment_link', 'payment_link_url' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['payment_link_url']);
    }

    // ---- permission ----------------------------------------------------------------

    #[Test]
    public function only_admins_with_the_dedicated_permission_may_see_bank_accounts(): void
    {
        $account = $this->account();

        // Act as each admin in turn: the app's super-admin gate looks at the logged-in user.
        $plain = Admin::factory()->create();
        $plain->givePermissionTo('view custom invoices', 'create custom invoices', 'edit custom invoices');
        $this->actingAs($plain, 'admin');

        $this->assertFalse($plain->can('viewAny', InvoiceBankAccount::class));
        $this->assertFalse($plain->can('update', $account), 'invoice permissions must not allow changing where money goes');

        $trusted = Admin::factory()->create();
        $trusted->givePermissionTo('manage bank accounts');
        $this->actingAs($trusted, 'admin');
        $this->assertTrue($trusted->can('viewAny', InvoiceBankAccount::class));
        $this->assertTrue($trusted->can('update', $account));
    }
}
