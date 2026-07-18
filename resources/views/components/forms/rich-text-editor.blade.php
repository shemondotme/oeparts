@props(['name' => 'content', 'value' => null, 'label' => 'Content'])

<div class="space-y-2">
    <label for="{{ $name }}" class="block text-sm font-medium text-slate-900">
        {{ $label }}
    </label>
    
    {{-- Hidden textarea for form submission --}}
    <textarea 
        id="{{ $name }}" 
        name="{{ $name }}"
        class="hidden"
        x-data="richTextEditor()"
        x-model="editorContent"
    >@if(is_array($value)){{ json_encode($value, JSON_UNESCAPED_UNICODE) }}@else{{ $value }}@endif</textarea>

    {{-- TinyMCE Editor Container --}}
    <div class="bg-white border border-slate-300 rounded-lg overflow-hidden shadow-sm">
        <div id="tinymce-{{ $name }}" class="prose-editor" style="min-height: 400px;">
            @if(is_array($value))
                {!! clean($value['en'] ?? '') !!}
            @else
                {!! clean($value ?? '') !!}
            @endif
        </div>
    </div>

    {{-- Error Message --}}
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

@push('scripts')
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    tinymce.init({
        selector: '#tinymce-{{ $name }}',
        plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
        toolbar: 'undo redo | formatselect | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | link image media',
        images_upload_url: '{{ route("admin.editor.upload-image") }}',
        images_upload_base_path: '{{ asset("storage/editor") }}',
        automatic_uploads: true,
        file_picker_types: 'image',
        height: 400,
        menubar: false,
        branding: false,
        promotion: false,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false,
        setup: function(editor) {
            editor.on('change', function() {
                document.getElementById('{{ $name }}').value = editor.getContent();
            });
            
            // Set initial content
            editor.setContent(document.getElementById('{{ $name }}').value);
        },
        content_css: [
            '{{ asset("css/app.css") }}',
        ],
        skin: 'oxide',
        icons: 'material',
    });
});
</script>
@endpush

@push('styles')
<style>
.prose-editor {
    font-family: "Inter", sans-serif;
    font-size: 14px;
    line-height: 1.6;
}

.tox-tinymce {
    border: none !important;
    box-shadow: none !important;
}

.tox-statusbar {
    background-color: #f1f5f9 !important;
    border-top: 1px solid #e2e8f0 !important;
}

.tox .tox-mbtn:hover:not(:disabled) {
    background-color: #e2e8f0 !important;
}

.tox .tox-mbtn.tox-mbtn--active {
    background-color: #09090b !important;
    color: white !important;
}
</style>
@endpush
