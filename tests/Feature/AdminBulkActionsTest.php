<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomInvoiceResource\Pages\ListCustomInvoices;
use App\Filament\Resources\InvoiceClientResource\Pages\ListInvoiceClients;
use App\Mail\CustomInvoiceMail;
use App\Mail\CustomInvoiceReminderMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\InvoiceClient;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Bulk actions on the customer, client and custom-invoice lists. */
class AdminBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    private function document(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'document_type' => InvoiceDocumentType::Invoice,
            'invoice_number' => 'INV-B-'.++$this->seq,
            'status' => CustomInvoiceStatus::Sent,
            'client_name' => 'Anna', 'client_email' => 'anna@example.com',
            'client_address_line1' => 'Main 1', 'client_city' => 'Berlin', 'client_country_code' => 'DE',
            'currency' => 'EUR', 'issue_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(5)->toDateString(),
            'items' => [['description' => 'Part', 'quantity' => 1, 'unit_price' => 100]], 'vat_rate' => 21,
        ], $overrides));
    }

    #[Test]
    public function customers_can_be_deactivated_and_activated_in_bulk(): void
    {
        $active = User::factory()->count(2)->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkDeactivate', $active->all());
        $this->assertSame(0, User::whereIn('id', $active->pluck('id'))->where('is_active', true)->count());

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkActivate', [$inactive, ...$active->all()]);
        $this->assertSame(3, User::whereIn('id', [$inactive->id, ...$active->pluck('id')])->where('is_active', true)->count());
    }

    #[Test]
    public function invoice_clients_get_a_language_and_currency_in_bulk_and_can_be_deleted(): void
    {
        $clients = collect([1, 2])->map(fn ($i) => InvoiceClient::create(['name' => "C{$i}", 'address_line1' => 'a', 'city' => 'b', 'country_code' => 'DE']));

        Livewire::test(ListInvoiceClients::class)
            ->callTableBulkAction('bulkSetLanguage', $clients->all(), ['language' => 'fr'])
            ->callTableBulkAction('bulkSetCurrency', $clients->all(), ['currency' => 'GBP']);

        $this->assertSame(2, InvoiceClient::where('language', 'fr')->where('currency', 'GBP')->count());

        Livewire::test(ListInvoiceClients::class)->callTableBulkAction('delete', $clients->all());
        $this->assertSame(0, InvoiceClient::count());
    }

    #[Test]
    public function bulk_send_emails_what_it_can_and_skips_the_rest(): void
    {
        Mail::fake();
        $draft = $this->document(['status' => CustomInvoiceStatus::Draft]);
        $noEmail = $this->document(['client_email' => null]);
        $cancelled = $this->document(['status' => CustomInvoiceStatus::Cancelled]);

        Livewire::test(ListCustomInvoices::class)->callTableBulkAction('bulkSend', [$draft, $noEmail, $cancelled]);

        Mail::assertSent(CustomInvoiceMail::class, 1); // only the draft: one has no email, one is cancelled
        $this->assertSame(CustomInvoiceStatus::Sent, $draft->refresh()->status);
        $this->assertSame(CustomInvoiceStatus::Cancelled, $cancelled->refresh()->status);
    }

    #[Test]
    public function bulk_reminders_only_go_to_open_invoices(): void
    {
        Mail::fake();
        $open = $this->document();
        $paid = $this->document(['status' => CustomInvoiceStatus::Paid]);
        $quote = $this->document(['document_type' => InvoiceDocumentType::Quote]);

        Livewire::test(ListCustomInvoices::class)->callTableBulkAction('bulkRemind', [$open, $paid, $quote]);

        Mail::assertSent(CustomInvoiceReminderMail::class, 1);
        $this->assertSame(1, $open->refresh()->reminder_count);
        $this->assertSame(0, $paid->refresh()->reminder_count);
    }

    #[Test]
    public function bulk_mark_paid_and_cancel_respect_the_status_rules(): void
    {
        $a = $this->document();
        $b = $this->document();
        $cancelled = $this->document(['status' => CustomInvoiceStatus::Cancelled]);

        Livewire::test(ListCustomInvoices::class)->callTableBulkAction('bulkMarkPaid', [$a, $cancelled]);
        $this->assertSame(CustomInvoiceStatus::Paid, $a->refresh()->status);
        $this->assertSame(CustomInvoiceStatus::Cancelled, $cancelled->refresh()->status);

        Livewire::test(ListCustomInvoices::class)->callTableBulkAction('bulkCancel', [$a, $b]);
        $this->assertSame(CustomInvoiceStatus::Paid, $a->refresh()->status, 'a paid invoice cannot be cancelled');
        $this->assertSame(CustomInvoiceStatus::Cancelled, $b->refresh()->status);
    }

    #[Test]
    public function the_pdfs_of_the_selection_download_as_one_zip(): void
    {
        $docs = [$this->document(), $this->document()];

        Livewire::test(ListCustomInvoices::class)
            ->callTableBulkAction('bulkDownloadPdfs', $docs)
            ->assertFileDownloaded();
    }

    #[Test]
    public function a_user_without_update_permission_cannot_run_the_bulk_changes(): void
    {
        $viewer = Admin::factory()->create();
        $viewer->givePermissionTo('view custom invoices');
        $this->actingAs($viewer, 'admin');
        $doc = $this->document();

        Livewire::test(ListCustomInvoices::class)->callTableBulkAction('bulkCancel', [$doc]);

        $this->assertSame(CustomInvoiceStatus::Sent, $doc->refresh()->status);
    }
}
