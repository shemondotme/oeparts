<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfPaymentDispute;
use App\Mail\PaymentDisputeMail;
use App\Models\Admin;
use App\Services\AdminNotificationService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 10 (Fraud & Abuse Prevention) backlog item — chargeback/dispute
 * handling was previously documented as a gap. The user's explicit choice
 * was "alert admin only", never auto-hold/auto-cancel — see
 * [[project_bulletproof_testing_2026_09]].
 */
class NotifyAdminsOfPaymentDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    #[Test]
    public function it_emails_every_active_super_admin(): void
    {
        Mail::fake();

        $superAdmin = Admin::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('super_admin');

        $inactiveSuperAdmin = Admin::factory()->create(['is_active' => false]);
        $inactiveSuperAdmin->assignRole('super_admin');

        $manager = Admin::factory()->create(['is_active' => true]);
        $manager->assignRole('manager');

        (new NotifyAdminsOfPaymentDispute(
            eventType: 'dispute.created',
            orderNumber: 'ORD-1001',
            disputeId: 'dst_123',
            status: 'REQUIRES_RESPONSE',
            stage: 'CHARGEBACK',
            amount: '49.99',
            currency: 'EUR',
            reason: 'Fraudulent transaction',
        ))->handle(app(AdminNotificationService::class));

        Mail::assertSent(PaymentDisputeMail::class, function ($mail) use ($superAdmin) {
            return $mail->hasTo($superAdmin->email) && $mail->disputeId === 'dst_123';
        });
        Mail::assertNotSent(PaymentDisputeMail::class, fn ($mail) => $mail->hasTo($inactiveSuperAdmin->email));
        Mail::assertNotSent(PaymentDisputeMail::class, fn ($mail) => $mail->hasTo($manager->email));
    }

    #[Test]
    public function the_bell_summary_names_the_real_airwallex_event_without_mangling_it(): void
    {
        Mail::fake();

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        // Airwallex's real event is payment_dispute.created. A plain
        // str_replace('dispute.', '') on it produced "payment_created".
        (new NotifyAdminsOfPaymentDispute(
            eventType: 'payment_dispute.created',
            orderNumber: 'ORD-1001',
            status: 'REQUIRES_RESPONSE',
            amount: '49.99',
            currency: 'EUR',
        ))->handle(app(AdminNotificationService::class));

        $stored = DB::table('notifications')->where('notifiable_id', $admin->id)->pluck('data')->implode(' ');

        $this->assertStringContainsString('created', $stored);
        $this->assertStringNotContainsString('payment_created', $stored);
        $this->assertStringContainsString('REQUIRES_RESPONSE', $stored);
    }

    #[Test]
    public function the_bell_summary_also_handles_the_paysera_chargeback_event_name(): void
    {
        Mail::fake();

        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole('super_admin');

        (new NotifyAdminsOfPaymentDispute(
            eventType: 'paysera.payment.chargeback',
            orderNumber: 'ORD-1001',
            status: 'chargeback',
            amount: '125.00',
            currency: 'EUR',
        ))->handle(app(AdminNotificationService::class));

        $stored = DB::table('notifications')->where('notifiable_id', $admin->id)->pluck('data')->implode(' ');

        $this->assertStringContainsString('paysera.payment.chargeback', $stored);
        $this->assertStringContainsString('125.00', $stored);
    }

    #[Test]
    public function it_creates_a_bell_notification_for_every_active_admin(): void
    {
        Mail::fake();

        $superAdmin = Admin::factory()->create(['is_active' => true]);
        $superAdmin->assignRole('super_admin');

        $manager = Admin::factory()->create(['is_active' => true]);
        $manager->assignRole('manager');

        (new NotifyAdminsOfPaymentDispute(
            eventType: 'dispute.created',
            orderId: 42,
            orderNumber: 'ORD-1001',
        ))->handle(app(AdminNotificationService::class));

        $this->assertGreaterThan(0, app(AdminNotificationService::class)->unreadCount($superAdmin->id));
        $this->assertGreaterThan(0, app(AdminNotificationService::class)->unreadCount($manager->id));
    }
}
