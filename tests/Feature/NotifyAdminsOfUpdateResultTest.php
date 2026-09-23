<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdateResult;
use App\Mail\UpdateResultMail;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update & Recovery System — the generalized apply-outcome notification
 * (formerly auto-apply-only: App\Jobs\NotifyAdminsOfAutoUpdate). Now
 * dispatched by UpdateApplier::complete()/fail() for BOTH a scheduled
 * auto-apply and a manually-triggered admin apply (see UpdateApplierTest and
 * AutoApplySecurityUpdateTest for the dispatch-site assertions) — this file
 * covers recipients + mail rendering for both trigger values.
 */
class NotifyAdminsOfUpdateResultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SettingsSeeder::class,
            RolesSeeder::class,
        ]);
    }

    #[Test]
    public function it_emails_active_super_admins_only(): void
    {
        Mail::fake();

        $super = Admin::factory()->create(['is_active' => true, 'email' => 'super@oeparts.test']);
        $super->assignRole('super_admin');

        $inactiveSuper = Admin::factory()->create(['is_active' => false, 'email' => 'inactive@oeparts.test']);
        $inactiveSuper->assignRole('super_admin');

        $support = Admin::factory()->create(['is_active' => true, 'email' => 'support@oeparts.test']);
        $support->assignRole('support');

        (new NotifyAdminsOfUpdateResult([
            'from_version' => '1.0.1', 'to_version' => '9.9.9', 'success' => true,
            'rolled_back' => false, 'error' => null, 'started_at' => now()->toIso8601String(),
            'trigger' => 'manual',
        ]))->handle();

        Mail::assertSent(UpdateResultMail::class, fn ($mail) => $mail->hasTo('super@oeparts.test'));
        Mail::assertNotSent(UpdateResultMail::class, fn ($mail) => $mail->hasTo('support@oeparts.test'));
        Mail::assertNotSent(UpdateResultMail::class, fn ($mail) => $mail->hasTo('inactive@oeparts.test'));
    }

    #[Test]
    public function the_manual_success_mailable_renders_without_auto_apply_wording(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.1', 'to_version' => '9.9.9', 'success' => true,
            'rolled_back' => false, 'error' => null, 'started_at' => now()->toIso8601String(),
            'trigger' => 'manual',
        ]))->render();

        $this->assertStringContainsString('administrator', $html);
        $this->assertStringNotContainsString('unattended', $html);
        $this->assertStringNotContainsString('OE_UPDATE_AUTO_SECURITY', $html);
    }

    #[Test]
    public function the_auto_apply_mailable_renders_with_unattended_wording(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.1', 'to_version' => '9.9.9', 'success' => true,
            'rolled_back' => false, 'error' => null, 'started_at' => now()->toIso8601String(),
            'trigger' => 'auto',
        ]))->render();

        $this->assertStringContainsString('unattended', $html);
        $this->assertStringContainsString('OE_UPDATE_AUTO_SECURITY', $html);
    }

    /**
     * Phase 15 (Email/Notification & Queue Failure Handling). The deleted
     * AutoUpdateResultMail's failed-and-not-rolled-back copy unconditionally
     * claimed "The update could not even start... nothing was changed" —
     * true only for a genuine pre-flight failure, not for a real failure
     * AFTER some irreversible-but-non-swap work had already happened
     * (UpdateApplier::fail()'s own rollback matrix: a failure before the
     * swap does not trigger a rollback, but "before the swap" isn't the
     * same as "before anything happened"). The replacement's copy was
     * corrected to cover both cases and point at the recovery console —
     * this pins the corrected wording directly, not just that SOME error
     * text renders.
     */
    #[Test]
    public function the_failed_mailable_renders_the_error(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.1', 'to_version' => '9.9.9', 'success' => false,
            'rolled_back' => false, 'error' => 'boom', 'started_at' => now()->toIso8601String(),
            'trigger' => 'manual',
        ]))->render();

        $this->assertStringContainsString('boom', $html);
        $this->assertStringContainsString('could not be automatically rolled back', $html);
        $this->assertStringContainsString('emergency recovery console', $html);
        // The old, narrower claim must not survive unqualified — it's now
        // conditioned on "if it never started", not asserted outright.
        $this->assertStringNotContainsString('could not even start', $html);
    }

    #[Test]
    public function the_rolled_back_mailable_renders(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.1', 'to_version' => '9.9.9', 'success' => false,
            'rolled_back' => true, 'error' => 'boom', 'started_at' => now()->toIso8601String(),
            'trigger' => 'auto',
        ]))->render();

        $this->assertStringContainsString('Rolled Back', $html);
        $this->assertStringContainsString('boom', $html);
    }

    /**
     * Pre-Phase-22 backlog sweep (2026-09-23). trigger='restore' (dispatched
     * by ProductionRestoreService/RunProductionRestoreJob for a full
     * production backup restore) used to fall through the same branch as
     * 'manual' and claim "This update was applied by an administrator via
     * System → System Updates" — false, and actively misleading during an
     * already-stressful disaster-recovery event (a restore is triggered from
     * Backup Dashboard, a completely different and more destructive flow).
     */
    #[Test]
    public function the_restore_mailable_renders_restore_specific_wording_not_system_updates(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.19', 'to_version' => 'restore-42', 'success' => true,
            'rolled_back' => false, 'error' => null, 'started_at' => now()->toIso8601String(),
            'trigger' => 'restore',
        ]))->render();

        $this->assertStringContainsString('Backup Dashboard', $html);
        $this->assertStringContainsString('production restore', $html);
        $this->assertStringNotContainsString('System &rarr; System Updates', $html);
        $this->assertStringNotContainsString('This update was applied', $html);
    }

    #[Test]
    public function a_failed_restore_mailable_says_restore_not_update(): void
    {
        $html = (new UpdateResultMail([
            'from_version' => '1.0.19', 'to_version' => 'restore-42', 'success' => false,
            'rolled_back' => true, 'error' => 'boom', 'started_at' => now()->toIso8601String(),
            'trigger' => 'restore',
        ]))->render();

        $this->assertStringContainsString('Restore Rolled Back', $html);
        $this->assertStringContainsString('The restore failed partway through', $html);
        $this->assertStringNotContainsString('The update failed', $html);
    }

    #[Test]
    public function the_restore_subject_says_restore_not_update(): void
    {
        $base = ['from_version' => '1.0.19', 'to_version' => 'restore-42', 'error' => null, 'started_at' => now()->toIso8601String()];

        $this->assertSame(
            'Restore applied — OeParts restore-42',
            (new UpdateResultMail($base + ['success' => true, 'rolled_back' => false, 'trigger' => 'restore']))->envelope()->subject
        );
        $this->assertSame(
            'Restore FAILED — OeParts restore-42',
            (new UpdateResultMail($base + ['success' => false, 'rolled_back' => false, 'trigger' => 'restore']))->envelope()->subject
        );
    }

    /**
     * Phase 15. The subject line is the one part of this email an admin is
     * guaranteed to actually read (inbox preview) — never covered by any
     * existing test, which only ever asserted on rendered body HTML.
     * envelope()'s prefix/success branching had no regression coverage at
     * all before this.
     */
    #[Test]
    public function the_subject_reflects_trigger_and_outcome(): void
    {
        $base = ['from_version' => '1.0.1', 'to_version' => '9.9.9', 'error' => null, 'started_at' => now()->toIso8601String()];

        $this->assertSame(
            'Auto-update applied — OeParts 9.9.9',
            (new UpdateResultMail($base + ['success' => true, 'rolled_back' => false, 'trigger' => 'auto']))->envelope()->subject
        );
        $this->assertSame(
            'Update applied — OeParts 9.9.9',
            (new UpdateResultMail($base + ['success' => true, 'rolled_back' => false, 'trigger' => 'manual']))->envelope()->subject
        );
        $this->assertSame(
            'Auto-update FAILED — OeParts 9.9.9',
            (new UpdateResultMail($base + ['success' => false, 'rolled_back' => false, 'trigger' => 'auto']))->envelope()->subject
        );
        $this->assertSame(
            'Update FAILED — OeParts 9.9.9',
            (new UpdateResultMail($base + ['success' => false, 'rolled_back' => true, 'trigger' => 'manual']))->envelope()->subject
        );
    }
}
