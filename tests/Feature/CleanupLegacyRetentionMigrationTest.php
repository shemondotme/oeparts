<?php

namespace Tests\Feature;

use App\Services\Cleanup\TargetRegistry;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One number (security.log_retention_days) used to govern six log tables. An admin who changed it keeps their choice
 * in each table's own cleanup setting; an install on the default needs nothing.
 */
class CleanupLegacyRetentionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_11_000003_carry_log_retention_into_cleanup_settings.php'))->up();
    }

    private function legacy(?string $value): void
    {
        DB::table('settings')->where(['group' => 'security', 'key' => 'log_retention_days'])->delete();
        if ($value !== null) {
            DB::table('settings')->insert(['group' => 'security', 'key' => 'log_retention_days', 'value' => $value, 'type' => 'integer']);
        }
        DB::table('settings')->where('group', 'cleanup')->delete();
    }

    #[Test]
    public function a_customised_value_is_copied_to_the_six_tables_it_used_to_govern(): void
    {
        $this->legacy('45');

        $this->migrate();

        foreach (['search_logs', 'failed_search_logs', 'login_logs', 'email_logs', 'activity_logs', 'cron_logs'] as $target) {
            $this->assertDatabaseHas('settings', ['group' => 'cleanup', 'key' => "retention_{$target}", 'value' => '45']);
        }
        $this->assertDatabaseMissing('settings', ['group' => 'cleanup', 'key' => 'retention_notifications']); // only the six it governed
    }

    #[Test]
    public function the_default_or_nonsense_leaves_the_engine_defaults_alone(): void
    {
        foreach (['90', '0', '-3', null] as $value) {
            $this->legacy($value);
            $this->migrate();
            $this->assertSame(0, DB::table('settings')->where('group', 'cleanup')->count(), (string) json_encode($value));
        }
    }

    #[Test]
    public function it_never_overwrites_a_value_already_chosen_for_a_table_and_is_idempotent(): void
    {
        $this->legacy('45');
        DB::table('settings')->insert(['group' => 'cleanup', 'key' => 'retention_search_logs', 'value' => '120', 'type' => 'integer']);

        $this->migrate();
        $this->migrate();

        $this->assertSame('120', DB::table('settings')->where(['group' => 'cleanup', 'key' => 'retention_search_logs'])->value('value'));
        $this->assertSame(6, DB::table('settings')->where('group', 'cleanup')->count());
    }

    #[Test]
    public function the_engine_still_enforces_each_tables_floor_on_a_carried_value(): void
    {
        $this->legacy('10'); // shorter than activity_logs' 30-day floor
        $this->migrate();

        $registry = app(TargetRegistry::class);
        app(SettingsService::class)->forget('cleanup');

        $this->assertSame(30, $registry->retentionDays($registry->get('activity_logs')));
        $this->assertSame(10, $registry->retentionDays($registry->get('search_logs')));
    }
}
