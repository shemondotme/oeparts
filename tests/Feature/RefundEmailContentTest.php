<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Mail\RefundProcessed;
use App\Mail\RefundStatusUpdate;
use App\Models\Order;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The refund emails must state the amount actually refunded. They used to read
 * $refund->amount / ->shipping_refund / ->total_refund_amount, none of which
 * exist on RefundRequest, so every "Your refund has been processed" email told
 * the customer "TOTAL REFUND 0.00 €".
 */
class RefundEmailContentTest extends TestCase
{
    use RefreshDatabase;

    private function refund(array $overrides = []): RefundRequest
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::Cancelled, 'payment_method' => PaymentMethod::BankTransfer,
            'locale' => $overrides['locale'] ?? 'en',
        ]);

        return RefundRequest::factory()->create(array_merge([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount_requested' => '123.45',
            'status' => RefundStatus::Processed,
        ], array_diff_key($overrides, ['locale' => 1])));
    }

    #[Test]
    public function the_processed_email_states_the_real_refund_amount_in_html_and_text(): void
    {
        $refund = $this->refund();
        $mail = new RefundProcessed($refund);

        $this->assertStringContainsString('123.45', $mail->render());
        $this->assertStringNotContainsString('0.00 €', $mail->render());

        $text = view('emails.refund-processed-text', ['refund' => $refund, 'locale' => 'en'])->render();
        $this->assertStringContainsString('123.45', $text);
    }

    #[Test]
    public function the_processed_email_names_how_the_money_goes_back(): void
    {
        $refund = $this->refund();

        $text = view('emails.refund-processed-text', ['refund' => $refund, 'locale' => 'en'])->render();

        $this->assertStringContainsString('Bank transfer', $text);
    }

    #[Test]
    public function the_processed_email_is_fully_translated(): void
    {
        $refund = $this->refund(['locale' => 'de']);

        $html = (new RefundProcessed($refund, 'de'))->render();

        $this->assertStringContainsString('123.45', $html);
        $this->assertStringNotContainsString('REFUND ID', $html);
        $this->assertStringNotContainsString('TOTAL REFUND', $html);
        $this->assertStringNotContainsString('VIEW REFUND DETAILS', $html);
        $this->assertStringContainsString('ERSTATTUNGSBETRAG', mb_strtoupper($html));
    }

    #[Test]
    public function the_status_update_email_is_translated_and_uses_the_real_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $refund = $this->refund(['locale' => 'de', 'status' => RefundStatus::Approved, 'admin_note' => 'Betrag folgt']);

        $html = (new RefundStatusUpdate($refund, RefundStatus::Pending, RefundStatus::Approved, 'de'))->render();

        $this->assertStringNotContainsString('CURRENT STATUS', $html);
        $this->assertStringNotContainsString('NOTE FROM SUPPORT', $html);
        $this->assertStringNotContainsString(' CET', $html);
        $this->assertStringContainsString('AKTUELLER STATUS', mb_strtoupper($html));
        $this->assertStringContainsString('Betrag folgt', $html);
    }
}
