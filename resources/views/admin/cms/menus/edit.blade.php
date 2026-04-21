@extends('layouts.admin')

@section('title', 'Edit Menu: ' . $menu->name)

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.menus.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit: {{ $menu->name }}</h1>
                <p class="text-gray-600 mt-1">Update menu settings and manage items</p>
            </div>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left: Menu Settings --}}
        <div class="space-y-6">
            {{-- Menu Settings --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Menu Settings</h2>
                </div>
                <form action="{{ route('admin.cms.menus.update', $menu) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                   value="{{ old('name', $menu->name) }}"
                                   required maxlength="100"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">
                                Location <span class="text-red-500">*</span>
                            </label>
                            <select id="location" name="location" required
                                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                                @foreach($locations as $location)
                                    <option value="{{ $location->value }}"
                                        {{ old('location', $menu->location) === $location->value ? 'selected' : '' }}>
                                        {{ ucfirst($location->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="is_active" name="is_active" value="1"
                                   {{ old('is_active', $menu->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_active" class="text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
                        <button type="submit"
                                class="w-full py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>

            {{-- Add Menu Item --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ itemType: '{{ old('type', 'url') }}' }">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Add Menu Item</h2>
                </div>
                <form action="{{ route('admin.cms.menus.items.store', $menu) }}" method="POST">
                    @csrf
                    <div class="p-6 space-y-4">
                        {{-- Multilang Label Tabs --}}
                        <div x-data="{ lang: 'en' }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Label <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-1 mb-2">
                                @foreach(['en','de','lt','fr','es'] as $lang)
                                    <button type="button"
                                            @click="lang = '{{ $lang }}'"
                                            :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="px-2.5 py-1 text-xs font-medium rounded transition-colors">
                                        {{ strtoupper($lang) }}
                                    </button>
                                @endforeach
                            </div>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <input type="text"
                                       name="label[{{ $lang }}]"
                                       x-show="lang === '{{ $lang }}'"
                                       value="{{ old('label.'.$lang) }}"
                                       placeholder="Label ({{ strtoupper($lang) }})"
                                       {{ $lang === 'en' ? 'required' : '' }}
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                        </div>

                        {{-- Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" x-model="itemType"
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="url">External URL</option>
                                <option value="page">Page</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>

                        {{-- Page select --}}
                        <div x-show="itemType === 'page'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Page</label>
                            <select name="page_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Select page</option>
                                @foreach($pages as $page)
                                    <option value="{{ $page->id }}" {{ old('page_id') == $page->id ? 'selected' : '' }}>
                                        {{ trans_field($page->title) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- URL --}}
                        <div x-show="itemType !== 'page'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">URL</label>
                            <input type="text" name="url"
                                   value="{{ old('url') }}"
                                   placeholder="/en/about or https://..."
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>

                        {{-- Target --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Target</label>
                            <select name="target" class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach($targets as $target)
                                    <option value="{{ $target->value }}" {{ old('target', '_self') === $target->value ? 'selected' : '' }}>
                                        {{ $target->value === '_blank' ? 'New tab (_blank)' : 'Same tab (_self)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Parent --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Parent Item</label>
                            <select name="parent_id" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">None (top level)</option>
                                @foreach($menu->items->whereNull('parent_id')->sortBy('sort_order') as $item)
                                    <option value="{{ $item->id }}" {{ old('parent_id') == $item->id ? 'selected' : '' }}>
                                        {{ trans_field($item->label) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Sort Order --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
                        <button type="submit"
                                class="w-full py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: Items List --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Menu Items</h2>
                    <span class="text-sm text-gray-500">{{ $menu->items->count() }} items</span>
                </div>

                @php $rootItems = $menu->items->whereNull('parent_id')->sortBy('sort_order'); @endphp

                @if($rootItems->isEmpty())
                    <div class="p-12 text-center text-gray-500">
                        <x-heroicon-o-list-bullet class="w-10 h-10 mx-auto text-gray-300 mb-2" />
                        <p class="text-sm">No items yet. Add an item using the form on the left.</p>
                    </div>
                @else
                    <div class="divide-y divide-gray-100">
                        @foreach($rootItems as $item)
                            <div class="px-6 py-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <x-heroicon-o-bars-2 class="w-4 h-4 text-gray-300 shrink-0" />
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-gray-900">{{ trans_field($item->label) }}</div>
                                            <div class="text-xs text-gray-400 truncate">
                                                {{ $item->url ?? ($item->page ? trans_field($item->page->title ?? []) : 'Page #' . $item->page_id) }}
                                                @if($item->target === '_blank')
                                                    <span class="ml-1">(new tab)</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded">order: {{ $item->sort_order }}</span>
                                        <form action="{{ route('admin.cms.menus.items.destroy', [$menu, $item]) }}" method="POST"
                                              onsubmit="return confirm('Remove this menu item?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:text-red-600">
                                                <x-heroicon-o-trash class="w-4 h-4" />
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                {{-- Children --}}
                                @php $children = $menu->items->where('parent_id', $item->id)->sortBy('sort_order'); @endphp
                                @if($children->isNotEmpty())
                                    <div class="mt-2 ml-7 space-y-1">
                                        @foreach($children as $child)
                                            <div class="flex items-center justify-between gap-3 pl-4 border-l-2 border-gray-100">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <x-heroicon-o-arrow-turn-down-right class="w-3.5 h-3.5 text-gray-300 shrink-0" />
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-medium text-gray-700">{{ trans_field($child->label) }}</div>
                                                        <div class="text-xs text-gray-400 truncate">{{ $child->url }}</div>
                                                    </div>
                                                </div>
                                                <form action="{{ route('admin.cms.menus.items.destroy', [$menu, $child]) }}" method="POST"
                                                      onsubmit="return confirm('Remove this menu item?');" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-300 hover:text-red-500">
                                                        <x-heroicon-o-trash class="w-3.5 h-3.5" />
                                                    </button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
