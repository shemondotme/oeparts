@extends('layouts.admin')

@section('title', 'Manage Languages')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Manage Languages</h1>
                <p class="text-slate-600 mt-2">Configure active languages and default locale.</p>
            </div>
            <a href="{{ route('admin.translations.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Translations
            </a>
        </div>

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-800 rounded-xl text-sm">
                {{ session('success') }}
            </div>
        @endif

        {{-- Languages Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h3 class="font-semibold text-slate-900">Active Languages</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 bg-slate-50">
                        <th class="px-6 py-3 text-left font-medium text-slate-600">Language</th>
                        <th class="px-6 py-3 text-left font-medium text-slate-600">Code</th>
                        <th class="px-6 py-3 text-left font-medium text-slate-600">Locale</th>
                        <th class="px-6 py-3 text-left font-medium text-slate-600">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-slate-600">Default</th>
                        <th class="px-6 py-3 text-right font-medium text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($languages as $language)
                        <tr class="hover:bg-slate-50 transition-colors" x-data="{ editing: false }">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">{{ $language->flag_emoji }}</span>
                                    <div>
                                        <div class="font-medium text-slate-900">{{ $language->name }}</div>
                                        <div class="text-xs text-slate-500">{{ $language->native_name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="font-mono text-slate-800 bg-slate-100 px-2 py-0.5 rounded text-xs">{{ $language->code }}</span>
                            </td>
                            <td class="px-6 py-4 text-slate-600">{{ $language->locale }}</td>
                            <td class="px-6 py-4">
                                @if($language->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($language->is_default)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-navy/10 text-navy">Default</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editing = !editing"
                                        class="text-sm text-navy hover:text-navy/80 font-medium">
                                    Edit
                                </button>
                            </td>
                        </tr>
                        {{-- Inline edit form --}}
                        <tr x-show="editing" x-cloak class="bg-slate-50">
                            <td colspan="6" class="px-6 py-4">
                                <form method="POST" action="{{ route('admin.translations.languages.update', $language->id) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                        <div>
                                            <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                                            <input type="text" name="name" value="{{ $language->name }}" required
                                                   class="w-full px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                                            @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-700 mb-1">Native Name</label>
                                            <input type="text" name="native_name" value="{{ $language->native_name }}"
                                                   class="w-full px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-700 mb-1">Locale</label>
                                            <input type="text" name="locale" value="{{ $language->locale }}"
                                                   class="w-full px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-slate-700 mb-1">Sort Order</label>
                                            <input type="number" name="sort_order" value="{{ $language->sort_order }}"
                                                   class="w-full px-3 py-1.5 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-6 mb-4">
                                        <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                            <input type="checkbox" name="is_active" value="1" {{ $language->is_active ? 'checked' : '' }}
                                                   class="w-4 h-4 text-navy border-slate-300 rounded">
                                            Active
                                        </label>
                                        @error('is_active') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                        @error('is_default') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="flex gap-3">
                                        <button type="submit"
                                                class="px-4 py-1.5 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                                            Save
                                        </button>
                                        <button type="button" @click="editing = false"
                                                class="px-4 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Add Language --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                <h3 class="font-semibold text-slate-900">Add New Language</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.translations.languages.add') }}" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="e.g. fr" maxlength="10"
                               class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                        @error('code') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                        <input type="text" name="name" required placeholder="e.g. French"
                               class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1">Native Name</label>
                        <input type="text" name="native_name" placeholder="e.g. Français"
                               class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-navy focus:border-navy">
                    </div>
                    <div class="flex items-end">
                        <button type="submit"
                                class="w-full px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                            <x-heroicon-o-plus class="w-4 h-4 inline mr-1" />
                            Add Language
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
