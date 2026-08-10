<?php

namespace App\Providers;

use App\Events\CartAbandoned;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\PaymentReceived;
use App\Listeners\LogEmailFailed;
use App\Listeners\LogEmailSent;
use App\Listeners\LogOrderStatusChange;
use App\Listeners\LogPaymentReceived;
use App\Listeners\RecoverAbandonedCart;
use App\Listeners\SendOrderConfirmation;
use App\Models\Admin;
use App\Models\AdminSession;
use App\Models\LoginLog;
use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageFailed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        MessageSent::class => [
            LogEmailSent::class,
        ],
        MessageFailed::class => [
            LogEmailFailed::class,
        ],
        OrderPlaced::class => [
            SendOrderConfirmation::class,
            \App\Listeners\UpdateInventory::class,
        ],
        OrderStatusChanged::class => [
            LogOrderStatusChange::class,
        ],
        PaymentReceived::class => [
            LogPaymentReceived::class,
        ],
        CartAbandoned::class => [
            RecoverAbandonedCart::class,
        ],
        \App\Events\RefundRequested::class => [
            \App\Listeners\NotifyAdminOfRefund::class,
        ],
        \App\Events\ContactMessageReceived::class => [
            \App\Listeners\NotifyAdminOfContactMessage::class,
        ],
        \App\Events\PartInquiryReceived::class => [
            \App\Listeners\NotifyAdminOfPartInquiry::class,
        ],
        \Illuminate\Queue\Events\JobFailed::class => [
            \App\Listeners\NotifyAdminsOnJobFailure::class,
        ],
    ];

    /**
     * Handle admin/customer login — create a LoginLog record for both
     * guards (customer logins/failures used to be silently unlogged — a
     * credential-stuffing run against storefront accounts left zero audit
     * trail), and for the 'admin' guard, invalidate that admin's other
     * active sessions.
     */
    public function onLogin(Login $event): void
    {
        $user = $event->user;

        if ($event->guard === 'admin' && $user instanceof Admin) {
            $this->invalidateOtherAdminSessions($user);
            $user->update(['last_login_at' => now()]);
        }

        LoginLog::create([
            'user_id'   => $user->getAuthIdentifier(),
            'user_type' => $event->guard === 'admin' ? LoginUserType::Admin : LoginUserType::Customer,
            'email'     => $user->email,
            'status'    => LogStatus::Success,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Handle failed login — create audit trail record. $event->user is null
     * whenever the submitted email doesn't match ANY account at all (wrong
     * password for a real email is the only case where it resolves) — a
     * distributed username-guessing scan used to leave zero trace because
     * the old code bailed out before ever reaching here in that case.
     */
    public function onLoginFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? $event->user?->email;

        if (! $email) {
            return;
        }

        LoginLog::create([
            'user_id'   => $event->user?->getAuthIdentifier(),
            'user_type' => $event->guard === 'admin' ? LoginUserType::Admin : LoginUserType::Customer,
            'email'     => $email,
            'status'    => LogStatus::Failed,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Drop this session's admin_sessions row on logout so
     * invalidateOtherAdminSessions() doesn't force-log-out a session the
     * admin already ended themselves on their next login elsewhere.
     */
    public function onLogout(Logout $event): void
    {
        if ($event->guard === 'admin') {
            AdminSession::where('session_id', session()->getId())->delete();
        }
    }

    /**
     * Force-logout this admin's other active sessions and record this one.
     * Deletes the underlying Laravel session rows directly (not
     * sessions.user_id, which reflects the default 'web' guard, never
     * 'admin' — see the admin_sessions migration for the full story).
     */
    private function invalidateOtherAdminSessions(Admin $admin): void
    {
        $otherSessionIds = AdminSession::where('admin_id', $admin->id)
            ->where('session_id', '!=', session()->getId())
            ->pluck('session_id');

        if ($otherSessionIds->isNotEmpty()) {
            DB::table('sessions')->whereIn('id', $otherSessionIds)->delete();
            AdminSession::where('admin_id', $admin->id)->whereIn('session_id', $otherSessionIds)->delete();
        }

        AdminSession::updateOrCreate(
            ['session_id' => session()->getId()],
            ['admin_id' => $admin->id],
        );
    }

    public function boot(): void
    {
        parent::boot();

        \Illuminate\Support\Facades\Event::listen(Login::class, [$this, 'onLogin']);
        \Illuminate\Support\Facades\Event::listen(Failed::class, [$this, 'onLoginFailed']);
        \Illuminate\Support\Facades\Event::listen(Logout::class, [$this, 'onLogout']);
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
