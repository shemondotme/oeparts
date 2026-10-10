<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendOrderStatusEmail;
use App\Mail\BankTransferInstructions;
use App\Mail\OrderConfirmation;
use App\Mail\OrderStatusUpdate;
use App\Models\InvoiceBankAccount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Order emails are written in the language the customer ordered in — never hard-coded English. */
class OrderEmailLocaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_order_remembers_the_language_it_was_placed_in_and_falls_back_to_the_default(): void
    {
        $this->assertSame('de', Order::factory()->make(['locale' => 'de'])->mailLocale());
        // Unknown / retired / empty codes must never reach the translator.
        $this->assertSame('en', Order::factory()->make(['locale' => 'xx'])->mailLocale());
        $this->assertSame('en', Order::factory()->make(['locale' => null])->mailLocale());
    }

    #[Test]
    public function the_confirmation_job_sends_in_the_orders_language(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['locale' => 'de', 'guest_email' => 'kunde@example.com', 'user_id' => null]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        (new SendOrderConfirmationEmail($order))->handle();

        Mail::assertSent(OrderConfirmation::class, fn ($m) => $m->locale === 'de');
    }

    #[Test]
    public function the_status_job_sends_in_the_orders_language(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['locale' => 'lt', 'guest_email' => 'klientas@example.com', 'user_id' => null]);

        (new SendOrderStatusEmail($order, OrderStatus::Pending, OrderStatus::Processing))->handle();

        Mail::assertSent(OrderStatusUpdate::class, fn ($m) => $m->locale === 'lt');
    }

    #[Test]
    public function the_confirmation_labels_are_translated_not_hard_coded_english(): void
    {
        $order = Order::factory()->create(['locale' => 'de', 'status' => OrderStatus::Processing]);
        OrderItem::factory()->create(['order_id' => $order->id]);

        $html = (new OrderConfirmation($order->fresh(), 'de'))->render();

        $this->assertStringContainsString('ZWISCHENSUMME', mb_strtoupper($html));
        $this->assertStringNotContainsString('ITEM MANIFEST', $html);
        $this->assertStringNotContainsString('DELIVERING TO', $html);
        $this->assertStringNotContainsString('VIEW ORDER DETAILS', $html);
    }

    #[Test]
    public function the_status_email_chrome_and_status_name_are_translated(): void
    {
        $order = Order::factory()->create(['locale' => 'de']);

        $html = (new OrderStatusUpdate($order, OrderStatus::Processing, OrderStatus::Shipped, 'de'))->render();

        $this->assertStringContainsString('VERSENDET', $html);
        $this->assertStringContainsString('AKTUELLER STATUS', $html);
        $this->assertStringNotContainsString('CURRENT STATUS', $html);
        $this->assertStringContainsString('ist auf dem Weg zu Ihnen', $html);
    }

    #[Test]
    public function the_bank_transfer_instructions_are_translated(): void
    {
        InvoiceBankAccount::create([
            'label' => 'Main', 'currency' => 'EUR', 'account_holder' => 'UAB OeParts', 'bank_name' => 'SEB',
            'iban' => 'LT601010012345678901', 'bic' => 'CBVILT2X', 'is_active' => true, 'sort_order' => 0,
        ]);
        $order = Order::factory()->create(['locale' => 'lt', 'payment_method' => PaymentMethod::BankTransfer]);
        OrderItem::factory()->create(['order_id' => $order->id]);
        $bank = app(PaymentService::class)->getBankTransferDetails($order);

        $html = (new BankTransferInstructions($order->fresh(), $bank, 'lt'))->render();

        $this->assertStringContainsString('MOKĖJIMO PASKIRTIS', mb_strtoupper($html));
        $this->assertStringNotContainsString('emails.bank_transfer', $html, 'a raw translation key leaked into the email');
    }

    #[Test]
    public function every_email_language_defines_every_key_the_english_file_does(): void
    {
        $flatten = function (array $a, string $prefix = '') use (&$flatten): array {
            $out = [];
            foreach ($a as $k => $v) {
                is_array($v) ? $out += $flatten($v, $prefix.$k.'.') : $out[$prefix.$k] = true;
            }

            return $out;
        };

        $en = array_keys($flatten(require lang_path('en/emails.php')));

        foreach (['de', 'fr', 'es', 'lt'] as $lang) {
            $keys = array_keys($flatten(require lang_path($lang.'/emails.php')));
            $missing = array_values(array_filter(
                array_diff($en, $keys),
                fn ($k) => str_starts_with($k, 'bank_transfer.')
                    || str_starts_with($k, 'order_status_update.')
                    || str_starts_with($k, 'order_confirmation.'),
            ));

            $this->assertSame([], $missing, "lang/{$lang}/emails.php is missing order-email keys");
        }
    }
}
