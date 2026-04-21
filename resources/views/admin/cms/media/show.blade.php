@extends('layouts.admin')

@section('title', $media->original_name ?? $media->file_name)

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.media.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $media->original_name ?? $media->file_name }}</h1>
                <p class="text-gray-500 text-sm mt-1">{{ $media->mime_type }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.media.edit', $media) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </a>
            <form action="{{ route('admin.cms.media.destroy', $media) }}" method="POST"
                  onsubmit="return confirm('Delete this file permanently?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Preview --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                @if(str_starts_with($media->mime_type ?? '', 'image/') && ($media->file_url || $media->path))
                    <div class="flex items-center justify-center bg-gray-50 rounded-lg overflow-hidden max-h-96">
                        <img src="{{ $media->file_url ?? Storage::url($media->path) }}"
                             alt="{{ $media->alt_text }}"
                             class="max-w-full max-h-96 object-contain">
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-64 bg-gray-50 rounded-lg">
                        <x-heroicon-o-document class="w-16 h-16 text-gray-300 mb-3" />
                        <p class="text-sm text-gray-500">{{ $media->mime_type }}</p>
                        @if($media->file_url || $media->path)
                            <a href="{{ $media->file_url ?? Storage::url($media->path) }}"
                               target="_blank"
                               class="mt-3 inline-flex items-center gap-1 text-sm text-[#0B3A68] hover:underline">
                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                Download
                            </a>
                        @endif
                    </div>
                @endif

                @if($media->alt_text)
                    <p class="mt-3 text-sm text-gray-500 italic text-center">{{ $media->alt_text }}</p>
                @endif
            </div>
        </div>

        {{-- Metadata --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">File Details</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Original Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 break-all">{{ $media->original_name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Stored As</dt>
                        <dd class="mt-1 text-sm font-mono text-gray-900 break-all">{{ $media->file_name ?? $media->filename }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Type</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $media->mime_type }}</dd>
                    </div>
                    @if($media->size)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Size</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ number_format($media->size / 1024, 0) }} KB</dd>
                        </div>
                    @endif
                    @if($media->alt_text)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Alt Text</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $media->alt_text }}</dd>
                        </div>
                    @endif
                    @if($media->uploaded_by && $media->uploader)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Uploaded By</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $media->uploader->name }}</dd>
                        </div>
                    @endif
                    @if($media->created_at)
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Uploaded</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $media->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if($media->file_url || $media->path)
                <div class="bg-white rounded-xl border border-gray-200 p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-3">URL</h2>
                    <div class="flex items-center gap-2">
                        <input type="text"
                               value="{{ $media->file_url ?? Storage::url($media->path) }}"
                               readonly
                               onclick="this.select()"
                               class="w-full rounded-lg border-gray-300 text-xs font-mono bg-gray-50 focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
