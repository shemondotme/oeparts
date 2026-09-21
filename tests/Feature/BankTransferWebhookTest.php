<?php

namespace Tests\Feature;

use App\Enums\PaymentGateway;
use App\Models\Payment;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * POST /webhooks/bank-transfer-confirm — an admin-facing webhook that
 * confirms a bank-transfer payment given the shared X-Webhook-Key header.
 * No PHPUnit coverage existed for this endpoint at all before this file.
 */
class BankTransferWebhookTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_correct_webhook_key_confirms_a_bank_transfer_payment(): void
    {
        app(SettingsService::class)->set('payment.webhook_secret', 'correct-secret');
        $payment = Payment::factory()->create(['gateway' => PaymentGateway::BankTransfer]);

        $response = $this->postJson('/webhooks/bank-transfer-confirm', [
            'payment_id' => $payment->id,
        ], ['X-Webhook-Key' => 'correct-secret']);

        $response->assertOk()->assertJson(['success' => true]);
    }

    #[Test]
    public function an_incorrect_webhook_key_is_rejected(): void
    {
        app(SettingsService::class)->set('payment.webhook_secret', 'correct-secret');
        $payment = Payment::factory()->create(['gateway' => PaymentGateway::BankTransfer]);

        $response = $this->postJson('/webhooks/bank-transfer-confirm', [
            'payment_id' => $payment->id,
        ], ['X-Webhook-Key' => 'wrong-secret']);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }

    /**
     * hash_equals(expected, given) alone would return true for two empty
     * strings — if payment.webhook_secret was never configured, an attacker
     * simply omitting the header would otherwise sail through. The explicit
     * $expectedKey === '' guard exists specifically to keep this closed.
     */
    #[Test]
    public function a_missing_webhook_secret_setting_rejects_even_with_no_header_sent(): void
    {
        app(SettingsService::class)->set('payment.webhook_secret', '');
        $payment = Payment::factory()->create(['gateway' => PaymentGateway::BankTransfer]);

        $response = $this->postJson('/webhooks/bank-transfer-confirm', [
            'payment_id' => $payment->id,
        ]);

        $response->assertStatus(403)->assertJson(['success' => false]);
    }
}
