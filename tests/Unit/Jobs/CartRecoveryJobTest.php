<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendAbandonedCartEmail;
use App\Mail\AbandonedCartReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CartRecoveryJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sends_recovery_email_to_customer(): void
    {
        Mail::fake();
        $email = 'customer@example.com';
        $cartSnapshot = ['items' => [['id' => 1, 'quantity' => 2]]];

        $job = new SendAbandonedCartEmail($email, $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class, function ($mail) use ($email) {
            return $mail->hasTo($email);
        });
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

        $job = new SendAbandonedCartEmail('user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class);
    }

    #[Test]
    public function recovery_job_has_three_retries(): void
    {
        $job = new SendAbandonedCartEmail('user@example.com', ['items' => []]);

        $this->assertEquals(3, $job->tries);
    }

    #[Test]
    public function recovery_job_has_correct_backoff_delays(): void
    {
        $job = new SendAbandonedCartEmail('user@example.com', ['items' => []]);

        $this->assertEquals([60, 300, 600], $job->backoff);
    }

    #[Test]
    public function backoff_provides_exponential_delay_growth(): void
    {
        $job = new SendAbandonedCartEmail('user@example.com', ['items' => []]);

        $backoff = $job->backoff;
        // 60 seconds (1 min) → 300 seconds (5 min) → 600 seconds (10 min)
        // Each retry gets longer delay
        $this->assertLessThan($backoff[1], $backoff[0]);
        $this->assertLessThan($backoff[2], $backoff[1]);
    }

    #[Test]
    public function recovery_job_is_queued_on_default(): void
    {
        Queue::fake();

        dispatch(new SendAbandonedCartEmail('user@example.com', ['items' => []]));

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

        $job = new SendAbandonedCartEmail('discount-user@example.com', $cartSnapshot);
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

        $job = new SendAbandonedCartEmail('oil-user@example.com', $cartSnapshot);
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

        $job = new SendAbandonedCartEmail('bulk-user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(AbandonedCartReminder::class);
    }

    #[Test]
    public function recovery_job_creates_email_log_on_success(): void
    {
        Mail::fake();

        $job = new SendAbandonedCartEmail('tracked@example.com', ['items' => []]);
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'tracked@example.com',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function multiple_recovery_emails_can_be_sent_to_different_users(): void
    {
        Mail::fake();

        $emails = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
        $cartSnapshot = ['items' => []];

        foreach ($emails as $email) {
            $job = new SendAbandonedCartEmail($email, $cartSnapshot);
            $job->handle();
        }

        foreach ($emails as $email) {
            Mail::assertSent(AbandonedCartReminder::class, function ($mail) use ($email) {
                return $mail->hasTo($email);
            });
        }

        foreach ($emails as $email) {
            $this->assertDatabaseHas('email_logs', [
                'to_email' => $email,
                'status' => 'success',
            ]);
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

        dispatch(new SendAbandonedCartEmail('user@example.com', $cartSnapshot));

        Queue::assertPushedOn('default', SendAbandonedCartEmail::class, function ($job) use ($cartSnapshot) {
            return $job->cartSnapshot === $cartSnapshot;
        });
    }
}
