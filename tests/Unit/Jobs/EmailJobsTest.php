<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendOtpEmail;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendOrderStatusEmail;
use App\Jobs\SendTrackingUpdateEmail;
use App\Jobs\SendAbandonedCartEmail;
use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailJobsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function send_otp_email_is_queued_on_critical(): void
    {
        Queue::fake();

        dispatch(new SendOtpEmail('user@example.com', '123456', 'en'));

        Queue::assertPushedOn('critical', SendOtpEmail::class);
    }

    #[Test]
    public function send_otp_email_job_sends_mailable(): void
    {
        Mail::fake();

        $job = new SendOtpEmail('user@example.com', '123456', 'en');
        $job->handle();

        Mail::assertSent(\App\Mail\OtpEmail::class, function ($mail) {
            return $mail->email === 'user@example.com'
                && $mail->code === '123456'
                && $mail->locale === 'en';
        });
    }

    #[Test]
    public function send_otp_email_creates_email_log(): void
    {
        Mail::fake();

        $job = new SendOtpEmail('test@example.com', '999888', 'de');
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'test@example.com',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function send_otp_email_supports_multiple_locales(): void
    {
        Mail::fake();
        $locales = ['en', 'de', 'fr', 'lt', 'es'];

        foreach ($locales as $locale) {
            Mail::reset();
            $job = new SendOtpEmail('user@example.com', '111111', $locale);
            $job->handle();

            Mail::assertSent(\App\Mail\OtpEmail::class, fn($m) => $m->locale === $locale);
        }
    }

    #[Test]
    public function send_order_confirmation_email_is_queued_on_critical(): void
    {
        Queue::fake();
        $order = Order::factory()->create();

        dispatch(new SendOrderConfirmationEmail($order));

        Queue::assertPushedOn('critical', SendOrderConfirmationEmail::class);
    }

    #[Test]
    public function send_order_confirmation_email_job_sends_mailable(): void
    {
        Mail::fake();
        $order = Order::factory()->create();

        $job = new SendOrderConfirmationEmail($order);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderConfirmation::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }

    #[Test]
    public function send_order_confirmation_email_for_guest_order(): void
    {
        Mail::fake();
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest@example.com',
        ]);

        $job = new SendOrderConfirmationEmail($order);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderConfirmation::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }

    #[Test]
    public function send_order_confirmation_email_includes_order_items(): void
    {
        Mail::fake();
        $order = Order::factory()->create();
        $order->items()->createMany([
            ['product_id' => null, 'quantity' => 2, 'price' => '50.00'],
            ['product_id' => null, 'quantity' => 1, 'price' => '100.00'],
        ]);

        $job = new SendOrderConfirmationEmail($order);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderConfirmation::class);
    }

    #[Test]
    public function send_order_confirmation_creates_email_log(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $job = new SendOrderConfirmationEmail($order);
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'buyer@example.com',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function send_order_status_email_is_queued_on_default(): void
    {
        Queue::fake();
        $order = Order::factory()->create();

        dispatch(new SendOrderStatusEmail($order, OrderStatus::Pending, OrderStatus::Processing));

        Queue::assertPushedOn('default', SendOrderStatusEmail::class);
    }

    #[Test]
    public function send_order_status_email_job_sends_mailable(): void
    {
        Mail::fake();
        $order = Order::factory()->create();

        $job = new SendOrderStatusEmail($order, OrderStatus::Pending, OrderStatus::Processing);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderStatusUpdate::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }

    #[Test]
    public function send_order_status_email_tracks_status_transition(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['status' => OrderStatus::Processing]);
        $oldStatus = OrderStatus::Pending;
        $newStatus = OrderStatus::Processing;

        $job = new SendOrderStatusEmail($order, $oldStatus, $newStatus);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderStatusUpdate::class);
    }

    #[Test]
    public function send_order_status_email_multiple_transitions(): void
    {
        Mail::fake();
        $order = Order::factory()->create();
        $transitions = [
            [OrderStatus::Pending, OrderStatus::Processing],
            [OrderStatus::Processing, OrderStatus::Shipped],
        ];

        foreach ($transitions as [$old, $new]) {
            Mail::reset();
            $job = new SendOrderStatusEmail($order, $old, $new);
            $job->handle();
            Mail::assertSent(\App\Mail\OrderStatusUpdate::class);
        }
    }

    #[Test]
    public function send_order_status_email_creates_log(): void
    {
        Mail::fake();
        $order = Order::factory()->create(['user_id' => User::factory()->create()->id]);

        $job = new SendOrderStatusEmail($order, OrderStatus::Pending, OrderStatus::Processing);
        $job->handle();

        $this->assertDatabaseHas('email_logs', ['status' => 'success']);
    }

    #[Test]
    public function send_tracking_update_email_is_queued_on_default(): void
    {
        Queue::fake();
        $order = Order::factory()->create();

        dispatch(new SendTrackingUpdateEmail($order));

        Queue::assertPushedOn('default', SendTrackingUpdateEmail::class);
    }

    #[Test]
    public function send_tracking_update_email_job_sends_mailable(): void
    {
        Mail::fake();
        $order = Order::factory()->create();

        $job = new SendTrackingUpdateEmail($order);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderShipped::class, function ($mail) use ($order) {
            return $mail->order->id === $order->id;
        });
    }

    #[Test]
    public function send_tracking_update_email_includes_tracking_number(): void
    {
        Mail::fake();
        $order = Order::factory()->create([
            'tracking_number' => 'TRACK-123456789',
            'carrier' => 'DHL',
        ]);

        $job = new SendTrackingUpdateEmail($order);
        $job->handle();

        Mail::assertSent(\App\Mail\OrderShipped::class);
    }

    #[Test]
    public function send_tracking_update_email_creates_log(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'shipping@example.com']);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $job = new SendTrackingUpdateEmail($order);
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'shipping@example.com',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function send_abandoned_cart_email_is_queued_on_default(): void
    {
        Queue::fake();

        dispatch(new SendAbandonedCartEmail('user@example.com', ['items' => []]));

        Queue::assertPushedOn('default', SendAbandonedCartEmail::class);
    }

    #[Test]
    public function send_abandoned_cart_email_job_sends_mailable(): void
    {
        Mail::fake();
        $cartSnapshot = ['items' => [['id' => 1, 'quantity' => 2]]];

        $job = new SendAbandonedCartEmail('user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(\App\Mail\AbandonedCartReminder::class, function ($mail) {
            return $mail->email === 'user@example.com';
        });
    }

    #[Test]
    public function send_abandoned_cart_email_includes_cart_snapshot(): void
    {
        Mail::fake();
        $cartSnapshot = [
            'items' => [
                ['id' => 1, 'name' => 'Product 1', 'quantity' => 2, 'price' => '50.00'],
            ],
            'subtotal' => '100.00',
        ];

        $job = new SendAbandonedCartEmail('user@example.com', $cartSnapshot);
        $job->handle();

        Mail::assertSent(\App\Mail\AbandonedCartReminder::class);
    }

    #[Test]
    public function send_abandoned_cart_email_has_retry_policy(): void
    {
        $job = new SendAbandonedCartEmail('user@example.com', []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 300, 600], $job->backoff());
    }

    #[Test]
    public function send_abandoned_cart_email_uses_default_queue(): void
    {
        $job = new SendAbandonedCartEmail('user@example.com', []);

        $this->assertEquals('default', $job->queue);
    }

    #[Test]
    public function send_abandoned_cart_email_creates_log(): void
    {
        Mail::fake();

        $job = new SendAbandonedCartEmail('recovery@example.com', []);
        $job->handle();

        $this->assertDatabaseHas('email_logs', [
            'to_email' => 'recovery@example.com',
            'status' => 'success',
        ]);
    }
}
