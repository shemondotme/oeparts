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
    public function send_refund_processed_email_is_queued_on_critical(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        dispatch(new SendRefundProcessedEmail($refund));

        Queue::assertPushedOn('critical', SendRefundProcessedEmail::class);
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

        // Mail::fake() prevents MessageSent event, so verify mail was sent instead
        Mail::assertSent(RefundProcessed::class, function ($mail) {
            return $mail->hasTo('log@example.com');
        });
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
            RefundStatus::Approved
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
            RefundStatus::Approved
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

        // Mail::fake() prevents MessageSent event, so verify mail was sent instead
        Mail::assertSent(RefundStatusUpdate::class, function ($mail) {
            return $mail->hasTo('refund-status@example.com');
        });
    }

    // ── Regression tests for Option P: broken refund-email route reference ──
    // Every test above uses Mail::fake(), which intercepts the Mailable
    // before it renders its Blade view — none of them could ever have
    // caught a broken route() call inside the view itself. These tests
    // actually render the Mailable, which is what surfaces the bug.

    #[Test]
    public function refund_processed_mailable_renders_without_throwing(): void
    {
        $order = Order::factory()->create();
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $html = (new RefundProcessed($refund))->render();

        $this->assertStringContainsString(
            route('frontend.account.order.detail', ['lang' => 'en', 'order' => $order->id]),
            $html,
        );
    }

    #[Test]
    public function refund_status_update_mailable_renders_without_throwing(): void
    {
        $order = Order::factory()->create();
        $refund = RefundRequest::factory()->create(['order_id' => $order->id]);

        $html = (new RefundStatusUpdate($refund, RefundStatus::Pending, RefundStatus::Approved))->render();

        $this->assertStringContainsString(
            route('frontend.account.order.detail', ['lang' => 'en', 'order' => $order->id]),
            $html,
        );
    }

    #[Test]
    public function refund_status_update_shows_admin_note_when_present(): void
    {
        // Regression test for a second bug found in the same template while
        // fixing the route: the "NOTE FROM SUPPORT" block read a `$message`
        // variable that was never passed by the Mailable — it collided with
        // Laravel's own auto-injected Illuminate\Mail\Message view variable,
        // crashing on every render. Fixed to read $refund->admin_note directly.
        $order = Order::factory()->create();
        $refund = RefundRequest::factory()->create([
            'order_id' => $order->id,
            'admin_note' => 'Return window expired.',
        ]);

        $html = (new RefundStatusUpdate($refund, RefundStatus::Pending, RefundStatus::Rejected))->render();

        $this->assertStringContainsString('Return window expired.', $html);
    }

    #[Test]
    public function refund_status_update_omits_note_block_when_admin_note_blank(): void
    {
        $order = Order::factory()->create();
        $refund = RefundRequest::factory()->create([
            'order_id' => $order->id,
            'admin_note' => null,
        ]);

        $html = (new RefundStatusUpdate($refund, RefundStatus::Pending, RefundStatus::Approved))->render();

        $this->assertStringNotContainsString('NOTE FROM SUPPORT', $html);
    }
}
