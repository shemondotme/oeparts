@extends('layouts.admin')

@section('title', 'Edit Media: ' . ($media->original_name ?? $media->file_name))

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.media.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Edit Media</h1>
                <p class="text-gray-600 mt-1 truncate max-w-sm">{{ $media->original_name ?? $media->file_name }}</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Preview --}}
        <div>
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                @if(str_starts_with($media->mime_type ?? '', 'image/') && ($media->file_url || $media->path))
                    <img src="{{ $media->file_url ?? Storage::url($media->path) }}"
                         alt="{{ $media->alt_text }}"
                         class="w-full rounded-lg object-cover max-h-64">
                @else
                    <div class="flex flex-col items-center justify-center h-32 bg-gray-50 rounded-lg">
                        <x-heroicon-o-document class="w-10 h-10 text-gray-300" />
                        <p class="text-xs text-gray-500 mt-2">{{ $media->mime_type }}</p>
                    </div>
                @endif
                <p class="mt-3 text-xs text-gray-500 text-center break-all">
                    {{ $media->original_name ?? $media->file_name }}
                    @if($media->size)
                        <span class="text-gray-400">({{ number_format($media->size / 1024, 0) }} KB)</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- Edit Form --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <form action="{{ route('admin.cms.media.update', $media) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @if($errors->any())
                        <div class="p-6 border-b border-gray-100 bg-red-50">
                            <ul class="text-sm text-red-700 space-y-1">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="p-6 space-y-6">
                        {{-- Alt Text per language --}}
                        <div x-data="{ lang: 'en' }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alt Text</label>
                            <div class="flex gap-1 mb-2">
                                @foreach(['en','de','lt','fr','es'] as $lang)
                                    <button type="button"
                                            @click="lang = '{{ $lang }}'"
                                            :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                                        {{ strtoupper($lang) }}
                                    </button>
                                @endforeach
                            </div>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <input type="text"
                                       name="alt_text[{{ $lang }}]"
                                       x-show="lang === '{{ $lang }}'"
                                       value="{{ old('alt_text.'.$lang, is_array($media->alt_text) ? ($media->alt_text[$lang] ?? '') : ($lang === 'en' ? ($media->alt_text ?? '') : '')) }}"
                                       placeholder="Alt text for accessibility ({{ strtoupper($lang) }})"
                                       maxlength="255"
                                       class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @endforeach
                        </div>

                        {{-- Caption per language --}}
                        <div x-data="{ lang: 'en' }">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Caption</label>
                            <div class="flex gap-1 mb-2">
                                @foreach(['en','de','lt','fr','es'] as $lang)
                                    <button type="button"
                                            @click="lang = '{{ $lang }}'"
                                            :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                            class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                                        {{ strtoupper($lang) }}
                                    </button>
                                @endforeach
                            </div>
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <textarea name="caption[{{ $lang }}]"
                                          x-show="lang === '{{ $lang }}'"
                                          rows="2"
                                          maxlength="500"
                                          placeholder="Caption ({{ strtoupper($lang) }})"
                                          class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('caption.'.$lang, is_array($media->caption) ? ($media->caption[$lang] ?? '') : '') }}</textarea>
                            @endforeach
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                        <a href="{{ route('admin.cms.media.index') }}"
                           class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
