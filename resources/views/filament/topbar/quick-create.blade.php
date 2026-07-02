{{--
  Topbar "+ New" quick-create dropdown (native Filament dropdown components).
  Each item is gated on an EXPLICIT permission string, never Resource::canCreate()
  — orders/customers create-access is tied to the 'edit' permission, not 'create'
  (see CLAUDE.md rule #27), so canCreate() would wrongly hide them.
--}}
@php
    $admin = auth('admin')->user();

    $candidates = [
        ['perm' => 'edit orders',          'label' => 'Order',        'icon' => 'heroicon-m-shopping-bag',    'resource' => \App\Filament\Resources\OrderResource::class],
        ['perm' => 'create products',      'label' => 'Product',      'icon' => 'heroicon-m-cube',            'resource' => \App\Filament\Resources\ProductResource::class],
        ['perm' => 'edit customers',       'label' => 'Customer',     'icon' => 'heroicon-m-user-plus',       'resource' => \App\Filament\Resources\CustomerResource::class],
        ['perm' => 'create manufacturers', 'label' => 'Manufacturer', 'icon' => 'heroicon-m-building-office', 'resource' => \App\Filament\Resources\ManufacturerResource::class],
        ['perm' => 'create coupons',       'label' => 'Coupon',       'icon' => 'heroicon-m-ticket',          'resource' => \App\Filament\Resources\CouponResource::class],
    ];

    $items = [];
    foreach ($candidates as $c) {
        if ($admin?->can($c['perm'])) {
            $items[] = [
                'label' => $c['label'],
                'icon' => $c['icon'],
                'url' => $c['resource']::getUrl('create'),
            ];
        }
    }
@endphp

@if (! empty($items))
    <x-filament::dropdown placement="bottom-end" teleport>
        <x-slot name="trigger">
            <x-filament::button icon="heroicon-m-plus" size="sm" color="primary" tag="button">
                New
            </x-filament::button>
        </x-slot>

        <x-filament::dropdown.header icon="heroicon-m-plus-circle">
            Create new
        </x-filament::dropdown.header>

        <x-filament::dropdown.list>
            @foreach ($items as $item)
                <x-filament::dropdown.list.item :href="$item['url']" :icon="$item['icon']" tag="a">
                    {{ $item['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
@endif
