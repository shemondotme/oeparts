<?php

namespace Tests\Feature;

use App\Enums\EmailTemplate;
use App\Enums\LogStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendOrderConfirmationEmail;
use App\Jobs\SendOtpEmail;
use App\Jobs\SendOrderStatusEmail;
use App\Jobs\SendTrackingUpdateEmail;
use App\Mail\OrderConfirmation;
use App\Mail\OtpEmail;
use App\Mail\OrderStatusUpdate;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTest extends TestCase
{
    use RefreshDatabase;

    // ── SendOtpEmail job ───────────────────────────────────────────────────────

    #[Test]
    public function send_otp_email_job_is_queued_on_critical_queue(): void
    {
        Queue::fake();

        dispatch(new SendOtpEmail('user@example.com', '123456', 'en'));

        Queue::assertPushedOn('critical', SendOtpEmail::class);
    }

    #[Test]
    public function send_otp_email_job_sends_otp_mailable(): void
    {
        Mail::fake();

        (new SendOtpEmail('user@example.com', '123456', 'en'))->handle();

        Mail::assertSent(OtpEmail::class, function (OtpEmail $mail) {
            return $mail->email === 'user@example.com'
                && $mail->code === '123456'
                && $mail->locale === 'en';
        });
    }

    #[Test]
    public function otp_email_dispatched_on_registration(): void
    {
        Queue::fake();

        $this->postJson('/en/register', [
            'name'                  => 'Test User',
            'email'                 => 'newuser@example.com',
            'password'              => 'Qz7#mV2!xP',
            'password_confirmation' => 'Qz7#mV2!xP',
            'agree_terms'           => true,
        ]);

        Queue::assertPushedOn('critical', SendOtpEmail::class);
    }

    // ── SendOrderConfirmationEmail job ─────────────────────────────────────────

    #[Test]
    public function send_order_confirmation_email_job_is_queued_on_critical_queue(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        dispatch(new SendOrderConfirmationEmail($order));

        Queue::assertPushedOn('critical', SendOrderConfirmationEmail::class);
    }

    #[Test]
    public function send_order_confirmation_email_job_sends_mailable(): void
    {
        Mail::fake();

        $order = $this->createOrder();

        (new SendOrderConfirmationEmail($order))->handle();

        Mail::assertSent(OrderConfirmation::class, fn($m) => $m->order->id === $order->id);
    }

    // ── SendOrderStatusEmail job ────────────────────────────────────────────────

    #[Test]
    public function send_order_status_email_job_is_queued_on_default_queue(): void
    {
        Queue::fake();

        $order = $this->createOrder();

        dispatch(new SendOrderStatusEmail(
            $order,
            OrderStatus::Pending,
            OrderStatus::Processing,
        ));

        Queue::assertPushedOn('default', SendOrderStatusEmail::class);
    }

    #[Test]
    public function send_order_status_email_sends_mailable(): void
    {
        Mail::fake();

        $order = $this->createOrder();

        (new SendOrderStatusEmail(
            $order,
            OrderStatus::Pending,
            OrderStatus::Processing,
        ))->handle();

        Mail::assertSent(OrderStatusUpdate::class);
    }

    // ── EmailLog listener ────────────────────────────────────────────────────

    #[Test]
    public function email_log_is_created_when_mail_is_sent(): void
    {
        $order = $this->createOrder();

        Mail::to('buyer@example.com')->send(new OrderConfirmation($order, 'en'));

        $this->assertDatabaseHas('email_logs', [
            'to_email'      => 'buyer@example.com',
            'template_type' => EmailTemplate::OrderConfirmation->value,
            'status'        => LogStatus::Success->value,
        ]);
    }

    #[Test]
    public function email_log_correct_template_type_for_otp(): void
    {
        Mail::to('user@example.com')->send(new OtpEmail('user@example.com', '654321', 'en'));

        $this->assertDatabaseHas('email_logs', [
            'to_email'      => 'user@example.com',
            'template_type' => EmailTemplate::Otp->value,
            'status'        => LogStatus::Success->value,
        ]);
    }

    #[Test]
    public function email_log_correct_template_type_for_order_status(): void
    {
        $order = $this->createOrder();

        Mail::to('buyer@example.com')->send(
            new OrderStatusUpdate($order, OrderStatus::Pending, OrderStatus::Processing, 'en')
        );

        $this->assertDatabaseHas('email_logs', [
            'to_email'      => 'buyer@example.com',
            'template_type' => EmailTemplate::OrderStatus->value,
            'status'        => LogStatus::Success->value,
        ]);
    }

    // ── Multilang ────────────────────────────────────────────────────────────

    #[Test]
    public function otp_email_respects_locale(): void
    {
        Mail::fake();

        (new SendOtpEmail('user@example.com', '999888', 'de'))->handle();

        Mail::assertSent(OtpEmail::class, fn($m) => $m->locale === 'de');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createOrder(): Order
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);

        $order = Order::create([
            'order_number'            => 'ORD-202603-000001',
            'user_id'                 => $user->id,
            'status'                  => OrderStatus::Pending,
            'payment_method'          => \App\Enums\PaymentMethod::BankTransfer,
            'payment_status'          => \App\Enums\PaymentStatus::Pending,
            'subtotal'                => '100.00',
            'discount_amount'         => '0.00',
            'shipping_cost'           => '10.00',
            'vat_amount'              => '21.00',
            'grand_total'             => '131.00',
            'shipping_name'           => 'John Doe',
            'shipping_address_line1'  => 'Test Street 1',
            'shipping_city'           => 'Berlin',
            'shipping_postal_code'    => '10115',
            'shipping_country_code'   => 'DE',
            'ip_address'              => '127.0.0.1',
        ]);

        OrderItem::create([
            'order_id'            => $order->id,
            'oem_number_snapshot' => 'BM-12345',
            'manufacturer_snapshot' => 'BMW',
            'condition_snapshot'  => 'new',
            'quantity'            => 1,
            'unit_price'          => '100.00',
            'total_price'         => '100.00',
        ]);

        return $order->fresh(['items']);
    }
}
