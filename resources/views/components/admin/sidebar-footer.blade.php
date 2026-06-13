@props([])

@php
    $user = filament()->auth()->user();
    $roles = $user?->roles ?? collect();
    $primaryRole = $roles->first();
    $roleLabel = $primaryRole?->name ?? 'Admin';
    $roleBorderColor = match ($roleLabel) {
        'super_admin' => '#F59E0B',
        'admin' => '#3B82F6',
        'catalog_admin' => '#10B981',
        default => '#94A3B8',
    };
@endphp

<div
    x-data="{}"
    class="fi-sidebar-footer-custom"
>
    <div
        @class([
            'fi-sidebar-footer-user',
            'fi-sidebar-footer-user-collapsed' => filament()->isSidebarCollapsibleOnDesktop(),
        ])
    >
        <div class="fi-sidebar-footer-avatar">
            <x-filament-panels::avatar.user :user="$user" loading="lazy" />
            <span class="fi-sidebar-status-dot"></span>
        </div>

        <div
            x-show="$store.sidebar.isOpen"
            class="fi-sidebar-footer-info"
        >
            <span class="fi-sidebar-footer-name">{{ filament()->getUserName($user) }}</span>
            <span class="fi-sidebar-footer-role">
                <span
                    class="fi-sidebar-role-badge"
                    style="--role-color: {{ $roleBorderColor }}"
                >
                    {{ $roleLabel }}
                </span>
            </span>
        </div>
    </div>

    <div
        x-show="$store.sidebar.isOpen"
        class="fi-sidebar-footer-version"
    >
        <span class="fi-sidebar-footer-version-text">OeParts v1.0</span>
    </div>
</div>
