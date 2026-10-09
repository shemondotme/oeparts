<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\SettingType;
use App\Filament\Resources\CustomInvoiceResource\Pages\CreateCustomInvoice;
use App\Filament\Resources\CustomInvoiceResource\Pages\ListCustomInvoices;
use App\Mail\CustomInvoiceMail;
use App\Mail\CustomInvoiceReminderMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\InvoiceClient;
use App\Models\Setting;
use App\Services\CustomInvoiceService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Documents in the client's language, and the sending options (CC, BCC, a personal
 * message, a copy for the sender) plus the PDF preview.
 */
class CustomInvoiceLanguageAndSendingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = Admin::factory()->create(['email' => 'sender@oeparts.test'])->assignRole('super_admin');
        $this->actingAs($this->admin, 'admin');

        foreach (['bank_name' => 'SEB', 'bank_iban' => 'LT601010012345678901', 'bank_bic' => 'CBVILT2X', 'bank_account_holder' => 'UAB OeParts'] as $key => $value) {
            Setting::updateOrCreate(['group' => 'payment', 'key' => $key], ['value' => $value, 'type' => SettingType::String]);
        }
        Cache::flush();
    }

    private function document(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'document_type' => InvoiceDocumentType::Invoice,
            'invoice_number' => 'INV-LNG-'.++$this->seq,
            'status' => CustomInvoiceStatus::Sent,
            'client_name' => 'Anna Muster', 'client_email' => 'anna@muster.de',
            'client_address_line1' => 'Teststrasse 1', 'client_city' => 'Berlin', 'client_country_code' => 'DE',
            'currency' => 'EUR', 'issue_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(5)->toDateString(),
            'items' => [['description' => 'Bremsscheiben', 'part_number' => 'A2024101247', 'lead_time' => 'In stock', 'quantity' => 2, 'unit_price' => 100]],
            'vat_rate' => 21,
        ], $overrides));
    }

    private function html(CustomInvoice $document): string
    {
        return app(CustomInvoiceService::class)->html($document);
    }

    // ---- languages ----------------------------------------------------------------------

    #[Test]
    public function a_german_invoice_is_printed_in_german(): void
    {
        $html = $this->html($this->document(['language' => 'de']));

        foreach (['RECHNUNG', 'Zwischensumme', 'Gesamtbetrag', 'Rechnungsempfänger', 'Zahlungsdetails · Banküberweisung', 'Verwendungszweck', 'Verfügbarkeit'] as $word) {
            $this->assertStringContainsString($word, $html, $word);
        }
        $this->assertStringNotContainsString('Subtotal', $html);
        $this->assertStringNotContainsString('Bank Transfer', $html);
        $this->assertStringContainsString('lang="de"', $html);
    }

    #[Test]
    public function every_supported_language_has_every_label_the_english_file_has(): void
    {
        $english = array_keys(require base_path('lang/en/invoice_doc.php'));

        foreach (['de', 'es', 'fr', 'lt'] as $locale) {
            $keys = array_keys(require base_path("lang/{$locale}/invoice_doc.php"));

            $this->assertSame([], array_values(array_diff($english, $keys)), "{$locale} is missing labels");
            $this->assertSame([], array_values(array_diff($keys, $english)), "{$locale} has labels English does not");
        }
    }

    #[Test]
    public function the_other_languages_use_their_own_document_titles(): void
    {
        $this->assertStringContainsString('DEVIS', $this->html($this->document(['language' => 'fr', 'document_type' => InvoiceDocumentType::Quote])));
        $this->assertStringContainsString('PRESUPUESTO', $this->html($this->document(['language' => 'es', 'document_type' => InvoiceDocumentType::Quote])));
        $this->assertStringContainsString('SĄSKAITA FAKTŪRA', $this->html($this->document(['language' => 'lt'])));
        $this->assertStringContainsString('GUTSCHRIFT', $this->html($this->document(['language' => 'de', 'document_type' => InvoiceDocumentType::CreditNote])));
    }

    #[Test]
    public function an_unknown_language_falls_back_to_english_and_prices_follow_the_language(): void
    {
        $this->assertStringContainsString('Subtotal', $this->html($this->document(['language' => 'xx'])));

        $de = $this->html($this->document(['language' => 'de']));
        $en = $this->html($this->document(['language' => 'en']));
        $this->assertStringContainsString('242,00', $de, 'German decimal comma');
        $this->assertStringContainsString('242.00', $en);
    }

    #[Test]
    public function rendering_does_not_leak_the_documents_language_into_the_rest_of_the_app(): void
    {
        App::setLocale('fr');

        $this->html($this->document(['language' => 'de']));

        $this->assertSame('fr', App::getLocale());
    }

    #[Test]
    public function the_bank_block_of_an_order_invoice_stays_english_whatever_the_site_language(): void
    {
        App::setLocale('de');

        $html = view('pdf.partials.bank-details', [
            'bank' => ['account_holder' => 'UAB', 'bank_name' => 'SEB', 'iban' => 'LT60', 'bic' => 'X', 'intermediary_bank' => '', 'instructions' => ''],
            'paymentReference' => 'ORD-1',
        ])->render();

        $this->assertStringContainsString('Payment Details · Bank Transfer', $html);
    }

    #[Test]
    public function the_email_and_its_subject_are_in_the_documents_language(): void
    {
        $document = $this->document(['language' => 'de']);
        $mail = new CustomInvoiceMail($document, 'pdf');

        $this->assertStringContainsString('Rechnung INV-LNG-', $mail->envelope()->subject);
        $this->assertStringContainsString('Guten Tag Anna Muster', $mail->render());
    }

    #[Test]
    public function the_payment_reminder_is_in_the_documents_language_with_the_right_plural(): void
    {
        $de = (new CustomInvoiceReminderMail($this->document(['language' => 'de']), 'pdf'))->render();
        $this->assertStringContainsString('vor 5 Tagen fällig', $de);
        $this->assertStringContainsString('OFFEN', $de);

        $one = (new CustomInvoiceReminderMail($this->document(['language' => 'de', 'due_date' => now()->subDay()->toDateString()]), 'pdf'))->render();
        $this->assertStringContainsString('vor 1 Tag fällig', $one);

        $lt = (new CustomInvoiceReminderMail($this->document(['language' => 'lt', 'due_date' => now()->subDays(12)->toDateString()]), 'pdf'))->render();
        $this->assertStringContainsString('prieš 12 dienų', $lt, 'Lithuanian uses a third plural form from 10 up');
    }

    #[Test]
    public function a_saved_clients_language_travels_to_the_invoice_and_the_form_saves_it(): void
    {
        $client = InvoiceClient::create([
            'name' => 'Anna Muster', 'address_line1' => 'Teststrasse 1', 'city' => 'Berlin', 'country_code' => 'DE', 'language' => 'de',
        ]);
        $this->assertSame('de', $client->toInvoiceFields()['language']);
        $this->assertSame('en', InvoiceClient::create(['name' => 'X', 'address_line1' => 'a', 'city' => 'b', 'country_code' => 'LT'])->toInvoiceFields()['language']);

        Livewire::test(CreateCustomInvoice::class)
            ->fillForm([
                'client_name' => 'Anna Muster', 'client_address_line1' => 'Teststrasse 1', 'client_city' => 'Berlin', 'client_country_code' => 'DE',
                'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'currency' => 'EUR', 'language' => 'de',
                'items' => [['description' => 'Bremsscheiben', 'lead_time' => '5-7 Tage', 'quantity' => 1, 'unit_price' => 100]], 'vat_rate' => 21,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = CustomInvoice::firstOrFail();
        $this->assertSame('de', $invoice->language);
        $this->assertSame('5-7 Tage', $invoice->items[0]['lead_time']);
        $this->assertStringContainsString('5-7 Tage', $this->html($invoice));
    }

    // ---- sending ------------------------------------------------------------------------------

    #[Test]
    public function sending_supports_cc_bcc_a_personal_message_and_a_copy_for_the_sender(): void
    {
        Mail::fake();
        $document = $this->document(['status' => CustomInvoiceStatus::Draft]);

        app(CustomInvoiceService::class)->send($document, [
            'cc' => ['colleague@muster.de', 'not-an-email'],
            'bcc' => ['accounts@oeparts.test'],
            'message' => "Thanks for the call today.\nBest, Sam",
            'copy_to_sender' => true,
        ]);

        Mail::assertSent(CustomInvoiceMail::class, function (CustomInvoiceMail $mail) {
            return $mail->hasTo('anna@muster.de')
                && $mail->hasCc('colleague@muster.de')
                && ! $mail->hasCc('not-an-email')
                && $mail->hasBcc('accounts@oeparts.test')
                && $mail->hasBcc('sender@oeparts.test')
                && str_contains($mail->render(), 'Thanks for the call today.');
        });
        $this->assertSame(CustomInvoiceStatus::Sent, $document->refresh()->status);
    }

    #[Test]
    public function without_options_the_email_has_no_extra_recipients_and_no_message(): void
    {
        Mail::fake();

        app(CustomInvoiceService::class)->send($this->document());

        Mail::assertSent(CustomInvoiceMail::class, function (CustomInvoiceMail $mail) {
            return ! $mail->hasCc('colleague@muster.de') && ! $mail->hasBcc('sender@oeparts.test') && $mail->customMessage === null;
        });
    }

    #[Test]
    public function the_send_action_is_a_form_with_the_extra_fields(): void
    {
        Mail::fake();
        $document = $this->document(['status' => CustomInvoiceStatus::Draft]);

        Livewire::test(ListCustomInvoices::class)
            ->callTableAction('sendToClient', $document, ['message' => 'Hello there', 'cc' => ['cc@muster.de'], 'bcc' => [], 'copy_to_sender' => false])
            ->assertHasNoTableActionErrors();

        Mail::assertSent(CustomInvoiceMail::class, fn (CustomInvoiceMail $m) => $m->hasCc('cc@muster.de') && $m->customMessage === 'Hello there');
    }

    #[Test]
    public function the_preview_opens_inline_and_the_download_is_an_attachment(): void
    {
        $document = $this->document(['status' => CustomInvoiceStatus::Draft]);

        $preview = $this->get(route('admin.custom-invoices.pdf', ['customInvoice' => $document, 'inline' => 1]));
        $preview->assertOk();
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertSame('application/pdf', $preview->headers->get('content-type'));

        $download = $this->get(route('admin.custom-invoices.pdf', ['customInvoice' => $document]));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
    }
}
