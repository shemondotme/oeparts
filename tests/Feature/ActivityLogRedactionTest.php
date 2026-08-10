<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminObserver/UserObserver used to log via getAttributes()/getChanges()/
 * getOriginal(), which bypass the model's $hidden — so every admin/customer
 * create, update, or delete wrote the raw bcrypt password hash (and
 * remember_token, when it changed) straight into activity_logs, visible to
 * any admin holding the "view activity logs" permission.
 */
class ActivityLogRedactionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_creation_does_not_log_the_password_hash(): void
    {
        $admin = Admin::create([
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => bcrypt('super-secret-password'),
            'is_active' => true,
        ]);

        $log = ActivityLog::where('model_type', Admin::class)
            ->where('model_id', $admin->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertSame('***', $log->new_values['password']);
        $this->assertStringNotContainsString($admin->password, json_encode($log->new_values));
    }

    #[Test]
    public function admin_password_change_is_logged_as_redacted_on_both_sides(): void
    {
        $admin = Admin::create([
            'name' => 'Existing Admin',
            'email' => 'existing-admin@example.com',
            'password' => bcrypt('old-password'),
            'is_active' => true,
        ]);
        $oldHash = $admin->password;

        $admin->update(['password' => bcrypt('brand-new-password')]);

        $log = ActivityLog::where('model_type', Admin::class)
            ->where('model_id', $admin->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('***', $log->old_values['password']);
        $this->assertSame('***', $log->new_values['password']);
        $this->assertStringNotContainsString($oldHash, json_encode($log->old_values));
        $this->assertStringNotContainsString($admin->password, json_encode($log->new_values));
    }

    #[Test]
    public function customer_creation_does_not_log_the_password_hash(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('customer-secret'),
        ]);

        $log = ActivityLog::where('model_type', User::class)
            ->where('model_id', $user->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertSame('***', $log->new_values['password']);
        $this->assertStringNotContainsString($user->password, json_encode($log->new_values));
    }

    #[Test]
    public function unrelated_admin_fields_are_still_logged_in_full(): void
    {
        $admin = Admin::create([
            'name' => 'Name Change Admin',
            'email' => 'name-change@example.com',
            'password' => bcrypt('whatever'),
            'is_active' => true,
        ]);

        $admin->update(['name' => 'Renamed Admin']);

        $log = ActivityLog::where('model_type', Admin::class)
            ->where('model_id', $admin->id)
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('Renamed Admin', $log->new_values['name']);
        $this->assertSame('Name Change Admin', $log->old_values['name']);
    }
}
