@extends('layouts.admin')

@section('title', 'Media Library')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Media Library</h1>
            <p class="text-gray-600 mt-1">Manage uploaded images and documents</p>
        </div>
        <a href="{{ route('admin.cms.media.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
            <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
            Upload Files
        </a>
    </div>

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <form method="GET" action="{{ route('admin.cms.media.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search Filename</label>
                <input type="text" id="search" name="search" value="{{ request('search') }}"
                       placeholder="filename..."
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                <select id="type" name="type" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">All Types</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>Images</option>
                    <option value="document" {{ request('type') === 'document' ? 'selected' : '' }}>Documents</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <a href="{{ route('admin.cms.media.index') }}"
                   class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Reset
                </a>
                <button type="submit"
                        class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                    Filter
                </button>
            </div>
        </form>
    </div>

    {{-- Media Grid --}}
    @if($media->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <x-heroicon-o-photo class="w-12 h-12 mx-auto text-gray-300 mb-3" />
            <p class="text-lg font-medium text-gray-900">No files found</p>
            <p class="text-gray-600 mt-1">Upload files to populate the media library.</p>
            <a href="{{ route('admin.cms.media.create') }}"
               class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                Upload Files
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach($media as $file)
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden group">
                    {{-- Preview --}}
                    <div class="aspect-square bg-gray-100 flex items-center justify-center relative overflow-hidden">
                        @if(str_starts_with($file->mime_type ?? '', 'image/') && ($file->file_url || $file->path))
                            <img src="{{ $file->file_url ?? Storage::url($file->path) }}"
                                 alt="{{ $file->alt_text }}"
                                 class="w-full h-full object-cover">
                        @else
                            <x-heroicon-o-document class="w-8 h-8 text-gray-400" />
                        @endif
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <a href="{{ route('admin.cms.media.show', $file) }}"
                               class="p-1.5 bg-white rounded text-gray-700 hover:bg-gray-100">
                                <x-heroicon-o-eye class="w-4 h-4" />
                            </a>
                            <a href="{{ route('admin.cms.media.edit', $file) }}"
                               class="p-1.5 bg-white rounded text-gray-700 hover:bg-gray-100">
                                <x-heroicon-o-pencil-square class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                    {{-- Info --}}
                    <div class="p-2">
                        <p class="text-xs text-gray-700 truncate font-medium">{{ $file->original_name ?? $file->file_name }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $file->type ?? ($file->mime_type ? (str_starts_with($file->mime_type, 'image/') ? 'image' : 'document') : '—') }}
                            @if($file->size) &bull; {{ number_format($file->size / 1024, 0) }}KB @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        @if($media->hasPages())
            <div class="mt-6">
                {{ $media->withQueryString()->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
