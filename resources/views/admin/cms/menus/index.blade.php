@extends('layouts.admin')

@section('title', 'Menus')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Navigation Menus</h1>
            <p class="text-gray-600 mt-1">Manage header and footer navigation menus</p>
        </div>
        <a href="{{ route('admin.cms.menus.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-plus class="w-4 h-4" />
            Create Menu
        </a>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Menus List --}}
    @forelse($menus as $menu)
        <div class="bg-white rounded-xl border border-gray-200 mb-4 overflow-hidden">
            <div class="flex items-center justify-between p-6 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-gray-100 rounded-lg">
                        <x-heroicon-o-bars-3 class="w-5 h-5 text-gray-500" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900">{{ $menu->name }}</h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                @if($menu->location === 'header') bg-blue-100 text-blue-800
                                @else bg-purple-100 text-purple-800
                                @endif">
                                {{ ucfirst($menu->location) }}
                            </span>
                            @if(!$menu->is_active)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                    Inactive
                                </span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-500 mt-0.5">{{ $menu->items->count() }} items</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.cms.menus.show', $menu) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <x-heroicon-o-eye class="w-4 h-4" />
                        View
                    </a>
                    <a href="{{ route('admin.cms.menus.edit', $menu) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <x-heroicon-o-pencil-square class="w-4 h-4" />
                        Edit
                    </a>
                    <form action="{{ route('admin.cms.menus.destroy', $menu) }}" method="POST"
                          onsubmit="return confirm('Delete this menu and all its items?');" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                            <x-heroicon-o-trash class="w-4 h-4" />
                        </button>
                    </form>
                </div>
            </div>
            @if($menu->items->count() > 0)
                <div class="px-6 py-3 bg-gray-50">
                    <div class="flex flex-wrap gap-2">
                        @foreach($menu->items->whereNull('parent_id')->take(8) as $item)
                            <span class="inline-flex items-center px-2 py-1 text-xs text-gray-600 bg-white border border-gray-200 rounded">
                                {{ trans_field($item->label) }}
                                @if($item->target === '_blank')
                                    <x-heroicon-o-arrow-top-right-on-square class="w-3 h-3 ml-1 text-gray-400" />
                                @endif
                            </span>
                        @endforeach
                        @if($menu->items->whereNull('parent_id')->count() > 8)
                            <span class="inline-flex items-center px-2 py-1 text-xs text-gray-400">
                                +{{ $menu->items->whereNull('parent_id')->count() - 8 }} more
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <x-heroicon-o-bars-3 class="w-12 h-12 mx-auto text-gray-300 mb-3" />
            <p class="text-lg font-medium text-gray-900">No menus yet</p>
            <p class="text-gray-600 mt-1">Create your first navigation menu to get started.</p>
            <a href="{{ route('admin.cms.menus.create') }}"
               class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-plus class="w-4 h-4" />
                Create Menu
            </a>
        </div>
    @endforelse
</div>
@endsection
