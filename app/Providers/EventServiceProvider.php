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
use App\Models\LoginLog;
use App\Enums\LoginUserType;
use App\Enums\LogStatus;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
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
     * Handle admin login — create LoginLog record and update last_login_at.
     */
    public function onLogin(Login $event): void
    {
        $user = $event->user;

        if (!$user instanceof Admin) {
            return;
        }

        // Invalidate previous sessions for this admin (concurrent session limiting)
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        $user->update(['last_login_at' => now()]);

        LoginLog::create([
            'user_id'   => $user->id,
            'user_type' => LoginUserType::Admin,
            'email'     => $user->email,
            'status'    => LogStatus::Success,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Handle failed admin login — create audit trail record.
     */
    public function onLoginFailed(Failed $event): void
    {
        if (!$event->user instanceof Admin) {
            return;
        }

        LoginLog::create([
            'user_id'   => $event->user->id,
            'user_type' => LoginUserType::Admin,
            'email'     => $event->credentials['email'] ?? $event->user->email,
            'status'    => LogStatus::Failed,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        \Illuminate\Support\Facades\Event::listen(Login::class, [$this, 'onLogin']);
        \Illuminate\Support\Facades\Event::listen(Failed::class, [$this, 'onLoginFailed']);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
