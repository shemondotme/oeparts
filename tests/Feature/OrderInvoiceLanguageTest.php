<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The order invoice PDF is written in the language the customer ordered in. */
class OrderInvoiceLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function html(Order $order): string
    {
        $address = (object) [
            'first_name' => 'Anna', 'last_name' => 'Muster', 'company' => null,
            'address_line_1' => 'Hauptstr. 1', 'address_line_2' => null, 'city' => 'Berlin',
            'state' => '', 'postal_code' => '10115', 'country_code' => 'DE', 'phone' => null,
        ];

        return view('pdf.invoice', [
            'order' => $order,
            'user' => $order->user,
            'items' => $order->items,
            'billingAddress' => $address,
            'shippingAddress' => $address,
            'bank' => null,
            'settings' => [
                'company_name' => 'UAB OeParts', 'company_address' => 'Vilnius', 'company_vat' => 'LT100',
                'company_registration' => '1', 'company_email' => 'info@example.com', 'company_phone' => '',
            ],
        ])->render();
    }

    private function order(string $locale, array $overrides = []): Order
    {
        $order = Order::factory()->create(array_merge([
            'locale' => $locale, 'status' => OrderStatus::Processing, 'invoice_number' => 'INV-202610-000001',
            'shipping_cost' => '10.00',
        ], $overrides));
        OrderItem::factory()->create(['order_id' => $order->id, 'condition_snapshot' => 'New']);

        return $order->fresh();
    }

    #[Test]
    public function a_german_order_gets_a_german_invoice_with_no_leftover_english_labels(): void
    {
        $html = $this->html($this->order('de'));

        $this->assertStringContainsString('lang="de"', $html);
        $this->assertStringContainsString('Zustand', $html);
        $this->assertStringContainsString('Versand', $html);
        $this->assertStringContainsString('Sperrige Teile', $html);
        $this->assertStringNotContainsString('>Condition<', $html);
        $this->assertStringNotContainsString('Oversized parts', $html);
        $this->assertStringNotContainsString('<td>Shipping</td>', $html);
    }

    #[Test]
    public function an_english_order_keeps_the_english_invoice(): void
    {
        $html = $this->html($this->order('en'));

        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringContainsString('Condition', $html);
        $this->assertStringContainsString('Oversized parts — shipping notice.', $html);
    }

    #[Test]
    public function an_unknown_order_language_falls_back_to_the_site_default(): void
    {
        $html = $this->html($this->order('xx'));

        $this->assertStringContainsString('lang="en"', $html);
        $this->assertStringNotContainsString('invoice_doc.', $html, 'a raw translation key leaked into the invoice');
    }

    #[Test]
    public function the_legal_reverse_charge_sentence_is_translated_too(): void
    {
        $html = $this->html($this->order('lt', ['vat_exempt' => true, 'is_b2b' => true, 'vat_number' => 'DE123456789', 'vat_amount' => '0.00']));

        $this->assertStringContainsString('2006/112/EB', $html);
        $this->assertStringNotContainsString('Council Directive', $html);
    }

    #[Test]
    public function every_language_defines_every_invoice_key_the_english_file_does(): void
    {
        $en = array_keys(require lang_path('en/invoice_doc.php'));

        foreach (['de', 'fr', 'es', 'lt'] as $lang) {
            $missing = array_values(array_diff($en, array_keys(require lang_path($lang.'/invoice_doc.php'))));

            $this->assertSame([], $missing, "lang/{$lang}/invoice_doc.php is missing keys");
        }
    }
}
