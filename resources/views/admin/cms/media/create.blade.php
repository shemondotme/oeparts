@extends('layouts.admin')

@section('title', 'Upload Media')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.media.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Upload Media</h1>
                <p class="text-gray-600 mt-1">Upload images or documents (max 10 MB each)</p>
            </div>
        </div>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <form action="{{ route('admin.cms.media.store') }}" method="POST" enctype="multipart/form-data"
                  x-data="uploadForm()">
                @csrf

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
                    {{-- Drop Zone --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Files <span class="text-red-500">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-amber-400 transition-colors cursor-pointer"
                             @click="$refs.fileInput.click()"
                             @dragover.prevent
                             @drop.prevent="handleDrop($event)">
                            <x-heroicon-o-arrow-up-tray class="w-10 h-10 mx-auto text-gray-400 mb-3" />
                            <p class="text-sm font-medium text-gray-700">Drag & drop files here, or click to browse</p>
                            <p class="text-xs text-gray-500 mt-1">Images and documents, max 10 MB each</p>
                            <input type="file" name="files[]" multiple
                                   x-ref="fileInput"
                                   @change="handleFiles($event)"
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                                   class="hidden">
                        </div>

                        {{-- Selected files preview --}}
                        <div x-show="files.length > 0" class="mt-3 space-y-2">
                            <template x-for="(file, i) in files" :key="i">
                                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                    <x-heroicon-o-document class="w-5 h-5 text-gray-400 shrink-0" />
                                    <span class="text-sm text-gray-700 flex-1 truncate" x-text="file.name"></span>
                                    <span class="text-xs text-gray-400" x-text="formatSize(file.size)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Optional alt text --}}
                    <div>
                        <label for="alt_text" class="block text-sm font-medium text-gray-700 mb-1">
                            Alt Text <span class="text-gray-400 font-normal">(optional, for first file)</span>
                        </label>
                        <input type="text" id="alt_text" name="alt_text[0]"
                               value="{{ old('alt_text.0') }}"
                               placeholder="Describe the image for accessibility"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    {{-- Optional caption --}}
                    <div>
                        <label for="caption" class="block text-sm font-medium text-gray-700 mb-1">
                            Caption <span class="text-gray-400 font-normal">(optional, for first file)</span>
                        </label>
                        <input type="text" id="caption" name="caption[0]"
                               value="{{ old('caption.0') }}"
                               placeholder="Image caption or description"
                               class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <a href="{{ route('admin.cms.media.index') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            :disabled="files.length === 0"
                            :class="files.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function uploadForm() {
    return {
        files: [],
        handleFiles(e) {
            this.files = Array.from(e.target.files);
        },
        handleDrop(e) {
            this.files = Array.from(e.dataTransfer.files);
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f));
            this.$refs.fileInput.files = dt.files;
        },
        formatSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }
    }
}
</script>
@endpush
