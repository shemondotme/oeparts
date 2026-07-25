<?php

namespace Tests\Unit;

use App\Models\ActivityLog;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * created_at is deliberately not mass-assignable and the model has
 * $timestamps = false, so before ActivityLog::booted()'s creating() hook
 * was added, every ActivityLog::create() call across the app (including
 * pre-existing ones like HealthCheckDashboard::logAction()) silently
 * persisted a NULL created_at — discovered while building the Cache
 * Dashboard's "last cleared"/"last warmed" feature, which reads it back.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_a_log_via_mass_assignment_still_stamps_created_at(): void
    {
        $admin = Admin::factory()->create(['is_active' => true]);

        $log = ActivityLog::create([
            'admin_id' => $admin->id,
            'action' => 'test_action',
            'model_type' => 'test',
            'model_id' => null,
            'old_values' => [],
            'new_values' => [],
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertNotNull($log->fresh()->created_at);
    }
}
