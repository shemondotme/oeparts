@props([])

@php
    $admin = auth('admin')->user();

    if (! $admin) {
        return;
    }

    $items = [];

    // Orders — gate on 'edit orders' (no 'create orders' permission exists; edit implies access)
    if ($admin->hasPermissionTo('edit orders') || $admin->hasRole('super_admin')) {
        $items[] = [
            'label' => 'Order',
            'url'   => '/admin/filament/orders/create',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />',
        ];
    }

    // Products — 'create products' permission exists in RolesSeeder
    if ($admin->hasPermissionTo('create products') || $admin->hasRole('super_admin')) {
        $items[] = [
            'label' => 'Product',
            'url'   => '/admin/products/create',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />',
        ];
    }

    // Customers — 'edit customers' as proxy (no 'create customers' perm exists)
    if ($admin->hasPermissionTo('edit customers') || $admin->hasRole('super_admin')) {
        $items[] = [
            'label' => 'Customer',
            'url'   => '/admin/customers/create',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />',
        ];
    }

    // Coupons — 'create coupons' permission exists in RolesSeeder
    if ($admin->hasPermissionTo('create coupons') || $admin->hasRole('super_admin')) {
        $items[] = [
            'label' => 'Coupon',
            'url'   => '/admin/coupons/create',
            'icon'  => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />',
        ];
    }
@endphp

@if (count($items) > 0)
    <div
        x-data="{ open: false }"
        x-on:keydown.escape.window="open = false"
        class="op-quick-create relative"
    >
        <button
            type="button"
            x-on:click="open = !open"
            x-bind:aria-expanded="open"
            aria-haspopup="true"
            class="op-quick-create-btn"
            title="Quick create"
        >
            <svg class="op-quick-create-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span class="op-quick-create-label">New</span>
            <svg class="op-quick-create-caret" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        {{-- Dropdown panel --}}
        <div
            x-show="open"
            x-on:click.outside="open = false"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
            class="op-quick-create-dropdown"
            role="menu"
            aria-label="Quick create options"
            style="display: none;"
        >
            <div class="op-quick-create-dropdown-header">
                Create new
            </div>

            @foreach ($items as $item)
                <a
                    href="{{ $item['url'] }}"
                    x-on:click="open = false"
                    class="op-quick-create-item"
                    role="menuitem"
                >
                    <span class="op-quick-create-item-icon">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="w-4 h-4">
                            {!! $item['icon'] !!}
                        </svg>
                    </span>
                    <span class="op-quick-create-item-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
