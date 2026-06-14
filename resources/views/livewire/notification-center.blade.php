<div
    class="op-nc-wrapper"
    wire:poll.60s="refresh"
    x-data="{ open: @entangle('open') }"
    @click.outside="$wire.close()"
    @keydown.escape.window="$wire.close()"
>
    {{-- Bell button --}}
    <button
        type="button"
        wire:click="toggle"
        class="op-notification-btn {{ $open ? 'op-notification-btn--active' : '' }}"
        :aria-expanded="open.toString()"
        aria-label="Notifications{{ $unreadCount > 0 ? ' ('.$unreadCount.' unread)' : '' }}"
    >
        <svg class="op-notification-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <span class="op-notification-badge op-notification-badge--pulse" aria-live="polite">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
        class="op-nc-panel"
        role="dialog"
        aria-label="Notifications panel"
        x-cloak
    >
        {{-- Header --}}
        <div class="op-nc-header">
            <h2 class="op-nc-title">Notifications</h2>
            <div class="op-nc-header-actions">
                @if ($unreadCount > 0)
                    <button
                        type="button"
                        wire:click="markAllRead"
                        class="op-nc-action-btn"
                        title="Mark all as read"
                    >
                        Mark all read
                    </button>
                @endif
            </div>
        </div>

        {{-- Notification list --}}
        <div class="op-nc-list" role="list">
            @forelse ($notifications as $categoryKey => $items)
                @php
                    $cat = \App\Enums\AdminNotificationCategory::tryFrom($categoryKey);
                    $isCollapsed = in_array($categoryKey, $collapsed);
                @endphp
                <div class="op-nc-category-group">
                    <button
                        type="button"
                        wire:click="toggleCategory('{{ $categoryKey }}')"
                        class="op-nc-category-header"
                        :aria-expanded="{{ $isCollapsed ? 'false' : 'true' }}"
                    >
                        <span class="op-nc-category-icon" aria-hidden="true">
                            {{ $cat?->icon() ?? '🔔' }}
                        </span>
                        <span class="op-nc-category-label">{{ $cat?->label() ?? ucfirst($categoryKey) }}</span>
                        <span class="op-nc-category-count">{{ count($items) }}</span>
                        <svg class="op-nc-chevron {{ $isCollapsed ? '' : 'rotate-180' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    @unless ($isCollapsed)
                        <ul class="op-nc-items" role="list">
                            @foreach ($items as $notification)
                                <li
                                    class="op-nc-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}"
                                    style="--nc-accent: {{ $cat?->cssAccent() ?? 'var(--accent-brand)' }}"
                                    role="listitem"
                                >
                                    <div class="op-nc-item-body">
                                        <p class="op-nc-item-title">{{ $notification->title }}</p>
                                        <p class="op-nc-item-detail">{{ $notification->detail }}</p>
                                        <time class="op-nc-item-time" datetime="{{ $notification->created_at }}">
                                            {{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                                        </time>
                                    </div>

                                    <div class="op-nc-item-actions">
                                        @if ($notification->action_url)
                                            <a
                                                href="{{ $notification->action_url }}"
                                                class="op-nc-action-link"
                                                wire:navigate
                                                wire:click="markRead('{{ $notification->id }}')"
                                            >
                                                View →
                                            </a>
                                        @endif

                                        @if (! $notification->read_at)
                                            <button
                                                type="button"
                                                wire:click="markRead('{{ $notification->id }}')"
                                                class="op-nc-mark-read"
                                                title="Mark as read"
                                                aria-label="Mark notification as read"
                                            >
                                                ✓
                                            </button>
                                        @endif

                                        <button
                                            type="button"
                                            wire:click="dismiss('{{ $notification->id }}')"
                                            class="op-nc-dismiss"
                                            title="Dismiss"
                                            aria-label="Dismiss notification"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endunless
                </div>
            @empty
                <div class="op-nc-empty">
                    <div class="op-nc-empty-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" class="w-16 h-16 opacity-30">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0M9 12l2 2 4-4" />
                        </svg>
                    </div>
                    <h3 class="op-nc-empty-heading">You're all caught up!</h3>
                    <p class="op-nc-empty-desc">No new notifications. System is running smoothly.</p>
                </div>
            @endforelse
        </div>

        {{-- Footer --}}
        @if (!empty($notifications))
            <div class="op-nc-footer">
                <button type="button" wire:click="exportCsv" class="op-nc-export-btn">
                    ↓ Export CSV
                </button>
                <button type="button" wire:click="exportJson" class="op-nc-export-btn">
                    ↓ Export JSON
                </button>
            </div>
        @endif
    </div>
</div>
