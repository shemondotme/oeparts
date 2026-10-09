<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Mail\UpdateResultMail;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\ContactMessageNotification;
use App\Notifications\PartInquiryNotification;
use App\Notifications\RefundRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** The shared email layout: slim brand band, different footers for customers and system messages. */
class EmailBandDesignTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function double_asterisks_in_email_copy_become_bold_and_everything_else_is_escaped(): void
    {
        $this->assertSame('Order <strong>ORD-1</strong> &lt;b&gt;x&lt;/b&gt;', (string) email_text('Order **ORD-1** <b>x</b>'));
        $this->assertSame('', (string) email_text(null));
    }

    #[Test]
    public function a_customer_email_shows_the_reason_footer_and_no_raw_asterisks(): void
    {
        $order = Order::factory()->create(['shipping_method_name_snapshot' => null, 'shipping_estimated_days_min' => null, 'shipping_estimated_days_max' => null]);

        $html = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString('You received this email because you have an account or placed an order with us.', $html);
        $this->assertStringNotContainsString('**', $html);
        $this->assertStringContainsString('<strong>'.$order->order_number.'</strong>', $html);
        $this->assertStringNotContainsString('EST. DELIVERY', $html, 'no empty delivery estimate row');
        $this->assertStringNotContainsString('COLOPHON', $html);
    }

    #[Test]
    public function a_delivery_estimate_is_shown_when_the_order_has_one(): void
    {
        $order = Order::factory()->create(['shipping_method_name_snapshot' => 'Courier', 'shipping_estimated_days_min' => 2, 'shipping_estimated_days_max' => 5]);

        $html = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString('Courier', $html);
        $this->assertStringContainsString('2–5 Days', $html);
    }

    #[Test]
    public function the_admin_notification_emails_use_the_brand_layout_not_the_laravel_default(): void
    {
        $order = Order::factory()->create(['grand_total' => '263.65']);
        $admin = new Admin;

        $mails = [
            (new ContactMessageNotification('Anna', 'anna@example.com', 'Brake discs', "Line one\nLine two"))->toMail($admin),
            (new PartInquiryNotification(7, 'A2024101247', 'buyer@example.com', 'Front left'))->toMail($admin),
            (new RefundRequestedNotification($order, 'Wrong part'))->toMail($admin),
        ];

        foreach ($mails as $mail) {
            $html = (string) view($mail->view[0], $mail->viewData)->render();
            $this->assertStringContainsString('automatic system message', $html);
            $this->assertStringNotContainsString('Regards', $html, 'not the stock Laravel notification template');
            $this->assertStringContainsString('btn-primary', $html);
        }

        $this->assertStringContainsString('Line one<br />', view($mails[0]->view[0], $mails[0]->viewData)->render());
        $refund = view($mails[2]->view[0], $mails[2]->viewData)->render();
        $this->assertStringContainsString('263.65', $refund, 'the order total is no longer blank');
        $this->assertStringContainsString('Wrong part', $refund);
    }

    #[Test]
    public function a_system_email_says_it_is_automatic_and_never_claims_the_reader_ordered_something(): void
    {
        $html = (new UpdateResultMail(['success' => false, 'rolled_back' => false, 'trigger' => 'manual', 'from_version' => '2.0.4', 'to_version' => '2.0.5', 'error' => 'boom']))->render();

        $this->assertStringContainsString('automatic system message', $html);
        $this->assertStringContainsString('SYSTEM · UPDATE', $html);
        $this->assertStringNotContainsString('placed an order with us', $html);
    }
}
