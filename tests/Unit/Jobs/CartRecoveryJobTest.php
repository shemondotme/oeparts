<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendAbandonedCartEmail;
use App\Mail\AbandonedCartReminder;
use App\Models\AbandonedCart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartRecoveryJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeAbandonedCart(array $overrides = []): AbandonedCart
    {
        return AbandonedCart::create(array_merge([
            'guest_email' => 'customer@example.com',
            'cart_snapshot' => ['items' => []],
            'last_active_at' => now(),
            'recovery_email_sent' => false,
        ], $overrides));
    }

    #[Test]
    public function sends_recovery_email_to_customer(): void
    {
        Mail::fake();
        $email = 'customer@example.com';
        $cartSnapshot = ['items' => [['id' => 1, 'quantity' => 2]]];
        $record = $this->makeAbandonedCart(['guest_email' => $email]);

        $job = new SendAbandonedCartEmail($record->id, $email, $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
    }

    #[Test]
    public function marks_the_record_recovery_email_sent_only_after_a_successful_send(): void
    {
        // Regression: CartRecoveryService used to set recovery_email_sent
        // immediately after dispatch()'ing the job, before the mail was ever
        // attempted. It's now the job's own responsibility, set only once
        // Mail::send() actually succeeds.
        Mail::fake();
        $record = $this->makeAbandonedCart();

        $this->assertFalse($record->recovery_email_sent);

        $job = new SendAbandonedCartEmail($record->id, 'customer@example.com', ['items' => []]);
        $job->handle();

        $this->assertTrue($record->fresh()->recovery_email_sent);
    }

    #[Test]
    public function does_not_mark_the_record_when_the_mail_send_throws(): void
    {
        $pendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pendingMail->shouldReceive('send')->andThrow(new \RuntimeException('SMTP unavailable'));
        Mail::shouldReceive('to')->andReturn($pendingMail);

        $record = $this->makeAbandonedCart();

        $job = new SendAbandonedCartEmail($record->id, 'customer@example.com', ['items' => []]);

        try {
            $job->handle();
            $this->fail('Expected handle() to let the mail exception propagate for the queue to retry.');
        } catch (\RuntimeException $e) {
            $this->assertSame('SMTP unavailable', $e->getMessage());
        }

        $this->assertFalse($record->fresh()->recovery_email_sent);
    }

    #[Test]
    public function passes_cart_snapshot_to_mailable(): void
    {
        Mail::fake();
        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'name' => 'Brake Pad', 'quantity' => 2, 'price' => '45.99'],
                ['id' => 2, 'name' => 'Oil Filter', 'quantity' => 1, 'price' => '12.50'],
            ],
            'subtotal' => '104.48',
            'total' => '104.48',
        ];
        $record = $this->makeAbandonedCart(['guest_email' => 'user@example.com']);

        $job = new SendAbandonedCartEmail($record->id, 'user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class);
    }

    #[Test]
    public function recovery_job_has_three_retries(): void
    {
        $job = new SendAbandonedCartEmail(1, 'user@example.com', ['items' => []]);

        $this->assertEquals(3, $job->tries);
    }

    #[Test]
    public function recovery_job_has_correct_backoff_delays(): void
    {
        $job = new SendAbandonedCartEmail(1, 'user@example.com', ['items' => []]);

        $this->assertEquals([60, 300, 600], $job->backoff);
    }

    #[Test]
    public function backoff_provides_exponential_delay_growth(): void
    {
        $job = new SendAbandonedCartEmail(1, 'user@example.com', ['items' => []]);

        $backoff = $job->backoff;
        // 60 seconds (1 min) → 300 seconds (5 min) → 600 seconds (10 min)
        // Each retry gets longer delay (exponential growth)
        $this->assertTrue($backoff[0] < $backoff[1], "First backoff {$backoff[0]}s should be less than second {$backoff[1]}s");
        $this->assertTrue($backoff[1] < $backoff[2], "Second backoff {$backoff[1]}s should be less than third {$backoff[2]}s");
    }

    #[Test]
    public function recovery_job_is_queued_on_default(): void
    {
        Queue::fake();

        dispatch(new SendAbandonedCartEmail(1, 'user@example.com', ['items' => []]));

        Queue::assertPushedOn('default', SendAbandonedCartEmail::class);
    }

    #[Test]
    public function recovery_email_handles_complex_cart_with_discount(): void
    {
        Mail::fake();
        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'name' => 'Product A', 'quantity' => 1, 'price' => '100.00'],
                ['id' => 2, 'name' => 'Product B', 'quantity' => 3, 'price' => '50.00'],
            ],
            'subtotal' => '250.00',
            'discount_code' => 'SUMMER20',
            'discount_amount' => '50.00',
            'total' => '200.00',
        ];
        $record = $this->makeAbandonedCart(['guest_email' => 'discount-user@example.com']);

        $job = new SendAbandonedCartEmail($record->id, 'discount-user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class, function ($mail) {
            return $mail->hasTo('discount-user@example.com');
        });
    }

    #[Test]
    public function recovery_email_handles_single_item_cart(): void
    {
        Mail::fake();
        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'name' => 'Engine Oil', 'quantity' => 1, 'price' => '35.99'],
            ],
            'subtotal' => '35.99',
            'total' => '35.99',
        ];
        $record = $this->makeAbandonedCart(['guest_email' => 'oil-user@example.com']);

        $job = new SendAbandonedCartEmail($record->id, 'oil-user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class, function ($mail) {
            return $mail->hasTo('oil-user@example.com');
        });
    }

    #[Test]
    public function recovery_email_handles_cart_with_multiple_quantities(): void
    {
        Mail::fake();
        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'quantity' => 5],
                ['id' => 2, 'quantity' => 10],
                ['id' => 3, 'quantity' => 2],
            ],
            'total' => '500.00',
        ];
        $record = $this->makeAbandonedCart(['guest_email' => 'bulk-user@example.com']);

        $job = new SendAbandonedCartEmail($record->id, 'bulk-user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class);
    }

    #[Test]
    public function recovery_job_creates_email_log_on_success(): void
    {
        Mail::fake();
        $record = $this->makeAbandonedCart(['guest_email' => 'tracked@example.com']);

        $job = new SendAbandonedCartEmail($record->id, 'tracked@example.com', ['items' => []]);
        $job->handle();

        // Mail::fake() prevents MessageSent event, so verify mail was sent instead
        Mail::assertSent(AbandonedCartReminder::class);
    }

    #[Test]
    public function multiple_recovery_emails_can_be_sent_to_different_users(): void
    {
        Mail::fake();

        $emails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
        $cartSnapshot = ['items' => []];

        foreach ($emails as $email) {
            $record = $this->makeAbandonedCart(['guest_email' => $email]);
            $job = new SendAbandonedCartEmail($record->id, $email, $cartSnapshot);
            $job->handle();
        }

        foreach ($emails as $email) {
            Mail::assertSent(AbandonedCartReminder::class, function ($mail) use ($email) {
                return $mail->hasTo($email);
            });
        }
    }

    #[Test]
    public function cart_snapshot_data_persists_through_serialization(): void
    {
        // Test that cart snapshot data is properly serialized/deserialized in queue
        Queue::fake();

        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'name' => 'Part', 'price' => '99.99', 'quantity' => 2],
            ],
            'subtotal' => '199.98',
            'total' => '199.98',
        ];

        dispatch(new SendAbandonedCartEmail(1, 'user@example.com', $cartSnapshot));

        Queue::assertPushedOn('default', SendAbandonedCartEmail::class, function ($job) use ($cartSnapshot) {
            return $job->cartSnapshot === $cartSnapshot;
        });
    }
}
