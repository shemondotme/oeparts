@extends('layouts.admin')

@section('title', 'Create Page')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.pages.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Page</h1>
                <p class="text-gray-600 mt-1">Add a new CMS page</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.cms.pages.store') }}" method="POST">
        @csrf

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <ul class="text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Multilang Title & Content --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ lang: 'en' }">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Content</h2>
                    </div>
                    <div class="p-6 space-y-6">
                        {{-- Language Tabs --}}
                        <div class="flex gap-1 border-b border-gray-100 pb-3">
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <button type="button"
                                        @click="lang = '{{ $lang }}'"
                                        :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                                    {{ strtoupper($lang) }}
                                </button>
                            @endforeach
                        </div>

                        {{-- Title per language --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Title <span class="text-red-500">*</span>
                            </label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <input type="text"
                                       name="title[{{ $lang }}]"
                                       x-show="lang === '{{ $lang }}'"
                                       value="{{ old('title.'.$lang) }}"
                                       placeholder="Page title ({{ strtoupper($lang) }})"
                                       {{ $lang === 'en' ? 'required' : '' }}
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                            @error('title.en')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Content per language --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Content <span class="text-red-500">*</span>
                            </label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <textarea name="content[{{ $lang }}]"
                                          x-show="lang === '{{ $lang }}'"
                                          rows="12"
                                          placeholder="Page content ({{ strtoupper($lang) }})"
                                          {{ $lang === 'en' ? 'required' : '' }}
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('content.'.$lang) }}</textarea>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- SEO --}}
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ lang: 'en' }">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">SEO</h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="flex gap-1 border-b border-gray-100 pb-3">
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <button type="button"
                                        @click="lang = '{{ $lang }}'"
                                        :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                                    {{ strtoupper($lang) }}
                                </button>
                            @endforeach
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Meta Title</label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <input type="text"
                                       name="meta_title[{{ $lang }}]"
                                       x-show="lang === '{{ $lang }}'"
                                       value="{{ old('meta_title.'.$lang) }}"
                                       placeholder="SEO title ({{ strtoupper($lang) }})"
                                       maxlength="255"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Meta Description</label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <textarea name="meta_description[{{ $lang }}]"
                                          x-show="lang === '{{ $lang }}'"
                                          rows="3"
                                          maxlength="500"
                                          placeholder="SEO description ({{ strtoupper($lang) }})"
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('meta_description.'.$lang) }}</textarea>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Publish --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Publish</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" {{ old('status', 'draft') === $status->value ? 'selected' : '' }}>
                                        {{ ucfirst($status->value) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="slug" class="block text-sm font-medium text-gray-700 mb-1">
                                Slug <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="slug" name="slug"
                                   value="{{ old('slug') }}"
                                   required placeholder="page-url-slug"
                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-amber-500 focus:border-amber-500">
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Settings --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Settings</h2>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_homepage" value="0">
                            <input type="checkbox" id="is_homepage" name="is_homepage" value="1"
                                   {{ old('is_homepage') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_homepage" class="text-sm text-gray-700">Set as homepage</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_header" value="0">
                            <input type="checkbox" id="is_header" name="is_header" value="1"
                                   {{ old('is_header') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_header" class="text-sm text-gray-700">Show in header nav</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_footer" value="0">
                            <input type="checkbox" id="is_footer" name="is_footer" value="1"
                                   {{ old('is_footer') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_footer" class="text-sm text-gray-700">Show in footer</label>
                        </div>
                    </div>
                </div>

                {{-- Featured Image --}}
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Featured Image</h2>
                    <div>
                        <select name="featured_image_id" class="w-full rounded-lg border-gray-300 text-sm">
                            <option value="">No image</option>
                            @foreach($media as $file)
                                <option value="{{ $file->id }}" {{ old('featured_image_id') == $file->id ? 'selected' : '' }}>
                                    {{ $file->original_name ?? $file->file_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('admin.cms.pages.index') }}"
                       class="flex-1 text-center py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="flex-1 py-2.5 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        Create Page
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
