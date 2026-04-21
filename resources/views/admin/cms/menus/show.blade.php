@extends('layouts.admin')

@section('title', $menu->name)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.menus.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $menu->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($menu->location === 'header') bg-blue-100 text-blue-800
                        @else bg-purple-100 text-purple-800
                        @endif">
                        {{ ucfirst($menu->location) }}
                    </span>
                    @if(!$menu->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            Inactive
                        </span>
                    @endif
                </div>
                <p class="text-gray-600 mt-1">{{ $menu->items->count() }} menu items</p>
            </div>
        </div>
        <a href="{{ route('admin.cms.menus.edit', $menu) }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-pencil-square class="w-4 h-4" />
            Edit Menu
        </a>
    </div>

    {{-- Items Tree --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Menu Items</h2>
        @php
            $rootItems = $menu->items->whereNull('parent_id')->sortBy('sort_order');
        @endphp
        @if($rootItems->isEmpty())
            <div class="text-center py-8 text-gray-500">
                <x-heroicon-o-list-bullet class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                <p>No items in this menu yet.</p>
                <a href="{{ route('admin.cms.menus.edit', $menu) }}"
                   class="mt-3 inline-flex items-center gap-1 text-sm text-[#0B3A68] hover:underline">
                    <x-heroicon-o-plus class="w-4 h-4" />
                    Add items
                </a>
            </div>
        @else
            <div class="space-y-1">
                @foreach($rootItems as $item)
                    <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 rounded-lg">
                        <x-heroicon-o-bars-2 class="w-4 h-4 text-gray-400 shrink-0" />
                        <div class="flex-1 min-w-0">
                            <span class="text-sm font-medium text-gray-900">{{ trans_field($item->label) }}</span>
                            @if($item->url)
                                <span class="ml-2 text-xs text-gray-400 truncate">{{ $item->url }}</span>
                            @endif
                        </div>
                        @if($item->target === '_blank')
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-400" />
                        @endif
                        <span class="text-xs text-gray-400">{{ ucfirst($item->type) }}</span>
                    </div>
                    {{-- Children --}}
                    @foreach($menu->items->where('parent_id', $item->id)->sortBy('sort_order') as $child)
                        <div class="flex items-center gap-3 px-4 py-2.5 ml-8 bg-gray-50/50 border-l-2 border-gray-200 rounded-r-lg">
                            <x-heroicon-o-arrow-turn-down-right class="w-4 h-4 text-gray-300 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <span class="text-sm text-gray-700">{{ trans_field($child->label) }}</span>
                                @if($child->url)
                                    <span class="ml-2 text-xs text-gray-400 truncate">{{ $child->url }}</span>
                                @endif
                            </div>
                            @if($child->target === '_blank')
                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 text-gray-400" />
                            @endif
                        </div>
                    @endforeach
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
