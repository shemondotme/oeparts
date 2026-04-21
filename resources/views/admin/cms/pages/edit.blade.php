@extends('layouts.admin')

@section('title', 'Edit Page: ' . trans_field($page->title))

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.pages.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Page</h1>
                <p class="text-gray-600 mt-1 font-mono text-sm">/{{ $page->slug }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <form action="{{ route('admin.cms.pages.update', $page) }}" method="POST">
        @csrf
        @method('PUT')

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
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Title <span class="text-red-500">*</span>
                            </label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <input type="text"
                                       name="title[{{ $lang }}]"
                                       x-show="lang === '{{ $lang }}'"
                                       value="{{ old('title.'.$lang, $page->title[$lang] ?? '') }}"
                                       placeholder="Page title ({{ strtoupper($lang) }})"
                                       {{ $lang === 'en' ? 'required' : '' }}
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                        </div>

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
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('content.'.$lang, $page->content[$lang] ?? '') }}</textarea>
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
                                       value="{{ old('meta_title.'.$lang, $page->meta_title[$lang] ?? '') }}"
                                       maxlength="255"
                                       placeholder="SEO title ({{ strtoupper($lang) }})"
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
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('meta_description.'.$lang, $page->meta_description[$lang] ?? '') }}</textarea>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Publish</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status" name="status" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}"
                                        {{ old('status', $page->status->value) === $status->value ? 'selected' : '' }}>
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
                                   value="{{ old('slug', $page->slug) }}"
                                   required
                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-amber-500 focus:border-amber-500">
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Settings</h2>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_homepage" value="0">
                            <input type="checkbox" id="is_homepage" name="is_homepage" value="1"
                                   {{ old('is_homepage', $page->is_homepage) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_homepage" class="text-sm text-gray-700">Set as homepage</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_header" value="0">
                            <input type="checkbox" id="is_header" name="is_header" value="1"
                                   {{ old('is_header', $page->is_header) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_header" class="text-sm text-gray-700">Show in header nav</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_footer" value="0">
                            <input type="checkbox" id="is_footer" name="is_footer" value="1"
                                   {{ old('is_footer', $page->is_footer) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_footer" class="text-sm text-gray-700">Show in footer</label>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Featured Image</h2>
                    <select name="featured_image_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">No image</option>
                        @foreach($media as $file)
                            <option value="{{ $file->id }}"
                                {{ old('featured_image_id', $page->featured_image_id) == $file->id ? 'selected' : '' }}>
                                {{ $file->original_name ?? $file->file_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('admin.cms.pages.index') }}"
                       class="flex-1 text-center py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="flex-1 py-2.5 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        Save Changes
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
