@extends('layouts.admin')

@section('title', 'Create Blog Post')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.blog.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Blog Post</h1>
                <p class="text-gray-600 mt-1">Write a new article</p>
            </div>
        </div>
    </div>

    <form action="{{ route('admin.cms.blog.store') }}" method="POST">
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
                {{-- Title, Slug, Excerpt, Content --}}
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
                                       value="{{ old('title.'.$lang) }}"
                                       placeholder="Post title ({{ strtoupper($lang) }})"
                                       {{ $lang === 'en' ? 'required' : '' }}
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Excerpt <span class="text-red-500">*</span>
                            </label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <textarea name="excerpt[{{ $lang }}]"
                                          x-show="lang === '{{ $lang }}'"
                                          rows="3"
                                          maxlength="500"
                                          placeholder="Short summary ({{ strtoupper($lang) }})"
                                          {{ $lang === 'en' ? 'required' : '' }}
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('excerpt.'.$lang) }}</textarea>
                            @endforeach
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Content <span class="text-red-500">*</span>
                            </label>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <textarea name="content[{{ $lang }}]"
                                          x-show="lang === '{{ $lang }}'"
                                          rows="16"
                                          placeholder="Article content ({{ strtoupper($lang) }})"
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
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('meta_description.'.$lang) }}</textarea>
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
                                   required placeholder="post-url-slug"
                                   class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-amber-500 focus:border-amber-500">
                            @error('slug')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="published_at" class="block text-sm font-medium text-gray-700 mb-1">Publish Date</label>
                            <input type="datetime-local" id="published_at" name="published_at"
                                   value="{{ old('published_at') }}"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Category & Tags</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <select id="category_id" name="category_id" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">Select category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                                        {{ trans_field($category->name) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tags</label>
                            <div class="space-y-1 max-h-40 overflow-y-auto">
                                @foreach($tags as $tag)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                               {{ in_array($tag->id, old('tags', [])) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                                        <span class="text-sm text-gray-700">{{ $tag->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-4">Featured Image</h2>
                    <select name="featured_image_id" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">No image</option>
                        @foreach($media as $file)
                            <option value="{{ $file->id }}" {{ old('featured_image_id') == $file->id ? 'selected' : '' }}>
                                {{ $file->original_name ?? $file->file_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-3">
                    <a href="{{ route('admin.cms.blog.index') }}"
                       class="flex-1 text-center py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="flex-1 py-2.5 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        Create Post
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
