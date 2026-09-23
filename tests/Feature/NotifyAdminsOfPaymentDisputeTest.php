<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfPaymentDispute;
use App\Mail\PaymentDisputeMail;
use App\Models\Admin;
use App\Services\AdminNotificationService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
