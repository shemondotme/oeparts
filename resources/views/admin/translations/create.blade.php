@extends('layouts.admin')

@section('title', 'Add New Translation String')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Add New Translation String</h1>
            <p class="text-muted mb-0">Create a new translation key across all languages</p>
        </div>
        <a href="{{ route('admin.translations.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back to Translations
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">String Details</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.translations.store') }}">
                        @csrf
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="group" class="form-label">Group *</label>
                                <input type="text" 
                                       class="form-control @error('group') is-invalid @enderror" 
                                       id="group" 
                                       name="group" 
                                       value="{{ old('group', request('group')) }}"
                                       required>
                                @error('group')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    e.g., "auth", "validation", "messages"
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="key" class="form-label">Key *</label>
                                <input type="text" 
                                       class="form-control @error('key') is-invalid @enderror" 
                                       id="key" 
                                       name="key" 
                                       value="{{ old('key') }}"
                                       required>
                                @error('key')
                                <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    e.g., "login.success", "validation.required"
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description (Optional)</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="2">{{ old('description') }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Optional description to help translators understand context
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Translations</h5>
                        
                        @foreach($languages as $lang)
                        <div class="mb-3">
                            <label for="translation_{{ $lang->code }}" class="form-label">
                                {{ $lang->name }} ({{ $lang->code }})
                                @if($lang->is_default)
                                <span class="badge bg-primary">Default</span>
                                @endif
                            </label>
                            <textarea class="form-control @error('translations.' . $lang->code) is-invalid @enderror" 
                                      id="translation_{{ $lang->code }}" 
                                      name="translations[{{ $lang->code }}]" 
                                      rows="2"
                                      {{ $lang->is_default ? 'required' : '' }}>{{ old('translations.' . $lang->code) }}</textarea>
                            @error('translations.' . $lang->code)
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        @endforeach
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="{{ route('admin.translations.index') }}" class="btn btn-secondary">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Translation String
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Group Suggestions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Existing Groups</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        @foreach($existingGroups as $existingGroup)
                        <a href="javascript:void(0)" 
                           class="list-group-item list-group-item-action group-suggestion"
                           data-group="{{ $existingGroup }}">
                            {{ $existingGroup }}
                            <span class="badge bg-secondary float-end">
                                {{ $groupCounts[$existingGroup] ?? 0 }} keys
                            </span>
                        </a>
                        @endforeach
                    </div>
                </div>
            </div>
            
            <!-- Quick Tips -->
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Use dot notation for keys: <code>group.subgroup.key</code></li>
                        <li>Keep groups consistent across your application</li>
                        <li>Provide clear descriptions for complex strings</li>
                        <li>Always provide English translation (default language)</li>
                        <li>Use placeholders for dynamic content: <code>:name</code>, <code>:count</code></li>
                        <li>Consider plural forms for different languages</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Group suggestion click
        document.querySelectorAll('.group-suggestion').forEach(item => {
            item.addEventListener('click', function() {
                document.getElementById('group').value = this.dataset.group;
            });
        });
        
        // Auto-fill other languages from English
        const englishTextarea = document.getElementById('translation_en');
        if (englishTextarea) {
            englishTextarea.addEventListener('blur', function() {
                const englishValue = this.value.trim();
                if (englishValue) {
                    // Ask user if they want to copy to other languages
                    if (confirm('Copy this translation to all other languages?')) {
                        document.querySelectorAll('[id^="translation_"]').forEach(textarea => {
                            if (textarea.id !== 'translation_en' && !textarea.value.trim()) {
                                textarea.value = englishValue;
                            }
                        });
                    }
                }
            });
        }
        
        // Auto-generate key from description
        const keyInput = document.getElementById('key');
        const descriptionInput = document.getElementById('description');
        
        descriptionInput.addEventListener('blur', function() {
            if (!keyInput.value.trim() && this.value.trim()) {
                // Generate a simple key from description
                const description = this.value.trim().toLowerCase();
                const key = description
                    .replace(/[^a-z0-9\s]/g, '')
                    .replace(/\s+/g, '_')
                    .substring(0, 50);
                
                if (key) {
                    keyInput.value = key;
                }
            }
        });
    });
</script>
@endpush