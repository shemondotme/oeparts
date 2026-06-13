<div class="op-notification-trigger relative">
    <button
        type="button"
        x-on:click="$wire.openDatabaseNotifications()"
        class="op-notification-btn fi-topbar-database-notifications-btn"
        :class="{ 'op-notification-btn--active': $wire.isOpen ?? false }"
        title="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
        aria-label="{{ __('filament-panels::layout.actions.open_database_notifications.label') }}"
        aria-haspopup="true"
    >
        <svg class="op-notification-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadNotificationsCount)
            <span
                class="op-notification-badge"
                aria-label="{{ $unreadNotificationsCount }} unread notifications"
            >{{ $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount }}</span>
        @endif
    </button>
</div>
