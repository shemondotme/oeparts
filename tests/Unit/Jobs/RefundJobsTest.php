<?php

namespace Tests\Unit\Jobs;

use App\Enums\RefundStatus;
use App\Jobs\SendRefundProcessedEmail;
use App\Jobs\SendRefundStatusEmail;
use App\Mail\RefundProcessed;
use App\Mail\RefundStatusUpdate;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RefundJobsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function send_refund_processed_email_is_queued_on_default(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        dispatch(new SendRefundProcessedEmail($refund));

        Queue::assertPushedOn('default', SendRefundProcessedEmail::class);
    }

    #[Test]
    public function send_refund_processed_email_sends_mailable(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundProcessedEmail($refund);
        $job->handle();

        Mail::assertSent(RefundProcessed::class, function ($mail) {
            return $mail->hasTo('buyer@example.com');
        });
    }

    #[Test]
    public function send_refund_processed_email_for_registered_user(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'registered@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundProcessedEmail($refund);
        $job->handle();

        Mail::assertSent(RefundProcessed::class, function ($mail) {
            return $mail->hasTo('registered@example.com');
        });
    }

    #[Test]
    public function send_refund_processed_email_for_guest_order(): void
    {
        Mail::fake();
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundProcessedEmail($refund);
        $job->handle();

        Mail::assertSent(RefundProcessed::class, function ($mail) {
            return $mail->hasTo('guest@example.com');
        });
    }

    #[Test]
    public function send_refund_processed_email_creates_email_log(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'log@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundProcessedEmail($refund);
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'log@example.com',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function send_refund_status_email_is_queued_on_default(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        dispatch(new SendRefundStatusEmail(
            $refund,
            RefundStatus::Pending,
            RefundStatus::Processing
        ));

        Queue::assertPushedOn('default', SendRefundStatusEmail::class);
    }

    #[Test]
    public function send_refund_status_email_tracks_status_transition(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'status@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundStatusEmail(
            $refund,
            RefundStatus::Pending,
            RefundStatus::Processing
        );
        $job->handle();

        Mail::assertSent(RefundStatusUpdate::class, function ($mail) {
            return $mail->hasTo('status@example.com');
        });
    }

    #[Test]
    public function send_refund_status_email_creates_email_log(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'refund-status@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $job = new SendRefundStatusEmail(
            $refund,
            RefundStatus::Pending,
            RefundStatus::Approved
        );
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'refund-status@example.com',
            'status' => 'success',
        ]);
    }
}
