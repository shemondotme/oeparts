<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfUpdate;
use App\Mail\UpdateAvailableMail;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Update & Recovery System (Module 21) — Chunk 1.4 notify job.
 */
class NotifyAdminsOfUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
        ]);
    }

    #[Test]
    public function the_update_available_mailable_renders(): void
    {
        $html = (new UpdateAvailableMail([
            'security' => true,
            'latest_version' => '9.9.9',
            'current_version' => '1.0.1',
            'channel' => 'stable',
            'release_date' => '2026-07-02',
            'migration_count' => 3,
            'changelog_url' => 'https://x/CHANGELOG.md',
        ]))->render();

        $this->assertStringContainsString('9.9.9', $html);
        $this->assertStringContainsString('Security', $html);
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

        (new NotifyAdminsOfUpdate(['latest_version' => '9.9.9', 'security' => true, 'channel' => 'stable']))->handle();

        Mail::assertSent(UpdateAvailableMail::class, fn ($mail) => $mail->hasTo('super@oeparts.test'));
        Mail::assertNotSent(UpdateAvailableMail::class, fn ($mail) => $mail->hasTo('support@oeparts.test'));
        Mail::assertNotSent(UpdateAvailableMail::class, fn ($mail) => $mail->hasTo('inactive@oeparts.test'));
    }
}
