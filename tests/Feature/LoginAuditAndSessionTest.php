<?php

namespace Tests\Feature;

use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use App\Models\Admin;
use App\Models\AdminSession;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EventServiceProvider's login/logout handling used to: (1) only ever log
 * Admin-guard logins/failures — a customer credential-stuffing run left zero
 * audit trail; (2) drop failed-login logging entirely whenever the
 * submitted email didn't match any account at all ($event->user is null in
 * that case); (3) try to "invalidate this admin's other sessions" via
 * sessions.user_id, which Laravel's DatabaseSessionHandler only ever
 * populates from the default 'web' guard — a no-op during an admin-only
 * session, or worse, capable of deleting an unrelated customer's session
 * that happens to share the same numeric ID.
 */
class LoginAuditAndSessionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function customer_login_is_logged(): void
    {
        $user = User::factory()->create();
        $this->app['session']->start();

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'user_type' => LoginUserType::Customer->value,
            'email' => $user->email,
            'status' => LogStatus::Success->value,
        ]);
    }

    #[Test]
    public function customer_login_failure_is_logged(): void
    {
        event(new Failed('web', null, ['email' => 'nope@example.com', 'password' => 'x']));

        $this->assertDatabaseHas('login_logs', [
            'user_type' => LoginUserType::Customer->value,
            'email' => 'nope@example.com',
            'status' => LogStatus::Failed->value,
        ]);
    }

    #[Test]
    public function failed_login_with_a_completely_unknown_email_is_still_logged(): void
    {
        // $event->user is null here — the email matched no account at all,
        // not even a wrong-password case. Distributed username-guessing
        // scans hit this path.
        event(new Failed('admin', null, ['email' => 'totally-unknown@example.com', 'password' => 'x']));

        $this->assertDatabaseHas('login_logs', [
            'user_type' => LoginUserType::Admin->value,
            'email' => 'totally-unknown@example.com',
            'status' => LogStatus::Failed->value,
        ]);
    }

    #[Test]
    public function login_log_rows_get_a_created_at_timestamp(): void
    {
        $user = User::factory()->create();
        $this->app['session']->start();

        event(new Login('web', $user, false));

        $log = LoginLog::where('email', $user->email)->firstOrFail();
        $this->assertNotNull($log->created_at);
    }

    #[Test]
    public function admin_login_invalidates_that_admins_other_sessions_without_touching_unrelated_ones(): void
    {
        $admin = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();

        // A pre-existing session belonging to the same admin, elsewhere.
        DB::table('sessions')->insert([
            'id' => 'stale-admin-session', 'user_id' => null, 'ip_address' => '10.0.0.1',
            'user_agent' => 'x', 'payload' => base64_encode('x'), 'last_activity' => now()->timestamp,
        ]);
        AdminSession::create(['admin_id' => $admin->id, 'session_id' => 'stale-admin-session']);

        // An unrelated session — must survive. This is exactly the
        // wrong-user-deleted scenario the old sessions.user_id-based query
        // could hit if a customer happened to share the admin's numeric ID.
        DB::table('sessions')->insert([
            'id' => 'unrelated-session', 'user_id' => $admin->id, 'ip_address' => '10.0.0.2',
            'user_agent' => 'x', 'payload' => base64_encode('x'), 'last_activity' => now()->timestamp,
        ]);
        AdminSession::create(['admin_id' => $otherAdmin->id, 'session_id' => 'unrelated-session']);

        $this->app['session']->start();
        $currentSessionId = $this->app['session']->getId();

        event(new Login('admin', $admin, false));

        $this->assertDatabaseMissing('sessions', ['id' => 'stale-admin-session']);
        $this->assertDatabaseMissing('admin_sessions', ['session_id' => 'stale-admin-session']);

        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-session']);
        $this->assertDatabaseHas('admin_sessions', ['session_id' => 'unrelated-session', 'admin_id' => $otherAdmin->id]);

        $this->assertDatabaseHas('admin_sessions', ['session_id' => $currentSessionId, 'admin_id' => $admin->id]);
    }

    #[Test]
    public function admin_logout_removes_its_admin_sessions_row(): void
    {
        $admin = Admin::factory()->create();
        $this->app['session']->start();
        $sessionId = $this->app['session']->getId();

        AdminSession::create(['admin_id' => $admin->id, 'session_id' => $sessionId]);

        event(new Logout('admin', $admin));

        $this->assertDatabaseMissing('admin_sessions', ['session_id' => $sessionId]);
    }
}
