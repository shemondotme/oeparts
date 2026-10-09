<?php

namespace Tests\Feature;

use App\Enums\CustomInvoiceStatus;
use App\Enums\InvoiceDocumentType;
use App\Enums\SettingType;
use App\Filament\Resources\CustomInvoiceResource\Pages\ListCustomInvoices;
use App\Filament\Resources\CustomInvoiceResource\Pages\ViewCustomInvoice;
use App\Mail\CustomInvoiceReminderMail;
use App\Models\Admin;
use App\Models\CustomInvoice;
use App\Models\Setting;
use App\Services\CustomInvoiceService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Partial payments, an overdue state, and reminders. "Paid" used to be a bare switch
 * with no record of when, how much, or against which reference.
 */
class InvoicePaymentsAndRemindersTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    }

    private function invoice(array $overrides = []): CustomInvoice
    {
        return CustomInvoice::create(array_merge([
            'document_type' => InvoiceDocumentType::Invoice,
            'invoice_number' => 'INV-PAY-'.++$this->seq,
            'status' => CustomInvoiceStatus::Sent,
            'sent_at' => now()->subDays(20),
            'client_name' => 'Tomoko Spivey', 'client_email' => 'tomoko@example.com',
            'client_address_line1' => '2-2-24 Ogami', 'client_city' => 'Hiratsuka', 'client_country_code' => 'JP',
            'currency' => 'EUR', 'issue_date' => now()->subDays(20)->toDateString(), 'due_date' => now()->subDays(5)->toDateString(),
            'items' => [['description' => 'Parts kit', 'quantity' => 1, 'unit_price' => 100]],
            'vat_rate' => 0,
        ], $overrides));
    }

    private function setting(string $key, string $value, SettingType $type = SettingType::String): void
    {
        Setting::updateOrCreate(['group' => 'invoice', 'key' => $key], ['value' => $value, 'type' => $type]);
        Cache::flush();
    }

    // ---- payments ---------------------------------------------------------------------------

    #[Test]
    public function a_partial_payment_keeps_the_invoice_open_and_a_second_one_closes_it(): void
    {
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();

        $service->recordPayment($invoice, '40.00', now()->subDay(), 'bank_transfer', 'REF-1');
        $invoice->refresh();
        $this->assertSame(CustomInvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame('40.00', $invoice->amountPaid());
        $this->assertSame('60.00', $invoice->balanceDue());
        $this->assertNull($invoice->paid_at);

        $service->recordPayment($invoice, '60.00', now(), 'bank_transfer', 'REF-2');
        $invoice->refresh();
        $this->assertSame(CustomInvoiceStatus::Paid, $invoice->status);
        $this->assertSame('0.00', $invoice->balanceDue());
        $this->assertSame(now()->toDateString(), $invoice->paid_at->toDateString(), 'paid on the date of the last payment');
        $this->assertCount(2, $invoice->payments);
    }

    #[Test]
    public function over_payments_zero_payments_and_payments_on_the_wrong_documents_are_refused(): void
    {
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();

        foreach ([['150.00', 'more than the balance'], ['0', 'more than zero'], ['-5', 'more than zero']] as [$amount, $why]) {
            try {
                $service->recordPayment($invoice, $amount);
                $this->fail("expected a refusal: {$why}");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString($why, $e->getMessage());
            }
        }

        foreach ([
            $this->invoice(['document_type' => InvoiceDocumentType::Quote]),
            $this->invoice(['document_type' => InvoiceDocumentType::CreditNote]),
            $this->invoice(['status' => CustomInvoiceStatus::Cancelled]),
            $this->invoice(['status' => CustomInvoiceStatus::Paid]),
        ] as $notPayable) {
            try {
                $service->recordPayment($notPayable, '10');
                $this->fail('expected a refusal for '.$notPayable->document_type->value.'/'.$notPayable->status->value);
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertCount(0, $invoice->payments, 'nothing was recorded');
    }

    #[Test]
    public function mark_as_paid_records_the_remaining_balance_as_a_payment(): void
    {
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();
        $service->recordPayment($invoice, '30.00');

        $service->markPaid($invoice->refresh());
        $invoice->refresh();

        $this->assertSame(CustomInvoiceStatus::Paid, $invoice->status);
        $this->assertSame('100.00', $invoice->amountPaid(), 'the 30.00 already received + a 70.00 payment for the rest');
        $this->assertCount(2, $invoice->payments);
    }

    #[Test]
    public function removing_a_payment_moves_the_status_back(): void
    {
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();
        $first = $service->recordPayment($invoice, '40.00');
        $service->recordPayment($invoice->refresh(), '60.00');
        $this->assertSame(CustomInvoiceStatus::Paid, $invoice->refresh()->status);

        $service->removePayment($invoice->payments()->reorder('id', 'desc')->first());
        $this->assertSame(CustomInvoiceStatus::PartiallyPaid, $invoice->refresh()->status);

        $service->removePayment($first->refresh());
        $this->assertSame(CustomInvoiceStatus::Sent, $invoice->refresh()->status, 'nothing paid and it was emailed: sent again');
        $this->assertNull($invoice->paid_at);
    }

    // ---- overdue ------------------------------------------------------------------------------

    #[Test]
    public function overdue_means_an_open_invoice_past_its_due_date(): void
    {
        $this->assertTrue($this->invoice()->isOverdue());
        $this->assertSame(5, $this->invoice()->daysOverdue());

        $this->assertFalse($this->invoice(['due_date' => now()->addDay()->toDateString()])->isOverdue(), 'not yet due');
        $this->assertFalse($this->invoice(['due_date' => now()->toDateString()])->isOverdue(), 'due today is not late yet');
        $this->assertFalse($this->invoice(['status' => CustomInvoiceStatus::Paid])->isOverdue());
        $this->assertFalse($this->invoice(['status' => CustomInvoiceStatus::Draft])->isOverdue());
        $this->assertFalse($this->invoice(['document_type' => InvoiceDocumentType::Quote])->isOverdue(), 'a quotation cannot be overdue');
        $this->assertTrue($this->invoice(['status' => CustomInvoiceStatus::PartiallyPaid])->isOverdue());
    }

    #[Test]
    public function the_overdue_filter_in_the_list_finds_only_overdue_invoices(): void
    {
        $late = $this->invoice();
        $fine = $this->invoice(['due_date' => now()->addDays(10)->toDateString()]);

        Livewire::test(ListCustomInvoices::class)
            ->loadTable()
            ->filterTable('overdue')
            ->assertCanSeeTableRecords([$late])
            ->assertCanNotSeeTableRecords([$fine]);
    }

    // ---- reminders -----------------------------------------------------------------------------

    #[Test]
    public function the_schedule_is_parsed_sorted_and_cleaned(): void
    {
        $this->setting('reminder_days', ' 10, 3 ,3,21,x,-2 ');

        $this->assertSame([3, 10, 21], app(CustomInvoiceService::class)->reminderSchedule());
    }

    #[Test]
    public function a_manual_reminder_emails_the_client_with_the_outstanding_amount_and_counts_it(): void
    {
        Mail::fake();
        $service = app(CustomInvoiceService::class);
        $invoice = $this->invoice();
        $service->recordPayment($invoice, '25.00');

        $service->sendReminder($invoice->refresh());

        Mail::assertSent(CustomInvoiceReminderMail::class, function (CustomInvoiceReminderMail $mail) {
            return $mail->hasTo('tomoko@example.com') && str_contains($mail->render(), '€75.00') && str_contains($mail->render(), 'due 5 days ago');
        });
        $this->assertSame(1, $invoice->refresh()->reminder_count);
        $this->assertNotNull($invoice->last_reminded_at);
    }

    #[Test]
    public function a_reminder_is_refused_for_a_paid_a_draft_or_an_address_less_invoice(): void
    {
        Mail::fake();
        $service = app(CustomInvoiceService::class);

        foreach ([
            $this->invoice(['status' => CustomInvoiceStatus::Paid]),
            $this->invoice(['status' => CustomInvoiceStatus::Draft]),
            $this->invoice(['client_email' => null]),
            $this->invoice(['document_type' => InvoiceDocumentType::Quote]),
        ] as $invoice) {
            try {
                $service->sendReminder($invoice);
                $this->fail('expected a refusal');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }

        Mail::assertNothingSent();
    }

    #[Test]
    public function the_command_does_nothing_while_automatic_reminders_are_switched_off(): void
    {
        Mail::fake();
        $this->invoice(['due_date' => now()->subDays(30)->toDateString()]);

        $this->artisan('oeparts:invoices:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    #[Test]
    public function the_command_follows_the_schedule_one_reminder_at_a_time(): void
    {
        Mail::fake();
        $this->setting('reminders_enabled', '1', SettingType::Boolean);
        $this->setting('reminder_days', '3,10');

        $invoice = $this->invoice(['due_date' => now()->subDays(4)->toDateString()]); // 4 days late: the "3" reminder is due

        $this->artisan('oeparts:invoices:remind')->assertSuccessful();
        Mail::assertSent(CustomInvoiceReminderMail::class, 1);
        $this->assertSame(1, $invoice->refresh()->reminder_count);

        // Same day again: nothing more, even though the command ran twice.
        $this->artisan('oeparts:invoices:remind')->assertSuccessful();
        Mail::assertSent(CustomInvoiceReminderMail::class, 1);

        // 11 days late, last reminder yesterday: the "10" one is due now.
        $invoice->forceFill(['due_date' => now()->subDays(11)->toDateString(), 'last_reminded_at' => now()->subDay()])->save();
        $this->artisan('oeparts:invoices:remind')->assertSuccessful();
        Mail::assertSent(CustomInvoiceReminderMail::class, 2);
        $this->assertSame(2, $invoice->refresh()->reminder_count);

        // The schedule is used up: no third reminder, however late.
        $invoice->forceFill(['due_date' => now()->subDays(60)->toDateString(), 'last_reminded_at' => now()->subDays(5)])->save();
        $this->artisan('oeparts:invoices:remind')->assertSuccessful();
        Mail::assertSent(CustomInvoiceReminderMail::class, 2);
    }

    #[Test]
    public function the_command_skips_paid_not_yet_overdue_and_not_yet_scheduled_invoices(): void
    {
        Mail::fake();
        $this->setting('reminders_enabled', '1', SettingType::Boolean);
        $this->setting('reminder_days', '7');

        $this->invoice(['status' => CustomInvoiceStatus::Paid, 'due_date' => now()->subDays(30)->toDateString()]);
        $this->invoice(['due_date' => now()->addDays(3)->toDateString()]);
        $this->invoice(['due_date' => now()->subDays(2)->toDateString()]); // late, but the first reminder is at day 7

        $this->artisan('oeparts:invoices:remind')->assertSuccessful();

        Mail::assertNothingSent();
    }

    // ---- the admin screens ------------------------------------------------------------------------

    #[Test]
    public function a_sent_invoice_can_be_opened_and_a_payment_recorded_from_its_page(): void
    {
        $invoice = $this->invoice();

        Livewire::test(ViewCustomInvoice::class, ['record' => $invoice->getKey()])
            ->assertSuccessful()
            ->callAction('recordPayment', ['amount' => '100.00', 'paid_on' => now()->toDateString(), 'method' => 'bank_transfer', 'reference' => 'BANK-77'])
            ->assertHasNoActionErrors();

        $invoice->refresh();
        $this->assertSame(CustomInvoiceStatus::Paid, $invoice->status);
        $this->assertSame('BANK-77', $invoice->payments->first()->reference);
    }

    #[Test]
    public function the_record_payment_action_only_shows_where_money_can_be_received(): void
    {
        $open = $this->invoice();
        $quote = $this->invoice(['document_type' => InvoiceDocumentType::Quote]);
        $paid = $this->invoice(['status' => CustomInvoiceStatus::Paid]);

        Livewire::test(ListCustomInvoices::class)
            ->assertTableActionVisible('recordPayment', $open)
            ->assertTableActionVisible('sendReminder', $open)
            ->assertTableActionHidden('recordPayment', $quote)
            ->assertTableActionHidden('recordPayment', $paid)
            ->assertTableActionHidden('sendReminder', $paid);
    }
}
