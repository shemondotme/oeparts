@extends('layouts.admin')

@section('title', 'Edit Translations: ' . $group)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">Edit Translations: {{ $group }}</h1>
            <p class="text-muted mb-0">Manage translations for all languages in this group</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.translations.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Back to Groups
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                <i class="fas fa-edit"></i> Bulk Update
            </button>
            <a href="{{ route('admin.translations.create') }}?group={{ $group }}" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Key
            </a>
        </div>
    </div>

    <!-- Language Tabs -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <ul class="nav nav-tabs card-header-tabs" id="languageTabs" role="tablist">
                @foreach($languages as $index => $lang)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $index === 0 ? 'active' : '' }}" 
                            id="tab-{{ $lang->code }}" 
                            data-bs-toggle="tab" 
                            data-bs-target="#content-{{ $lang->code }}" 
                            type="button" 
                            role="tab">
                        {{ $lang->name }} ({{ $lang->code }})
                        @if($lang->is_default)
                        <span class="badge bg-primary">Default</span>
                        @endif
                    </button>
                </li>
                @endforeach
            </ul>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.translations.bulkUpdate', $group) }}">
                @csrf
                @method('PUT')
                
                <div class="tab-content" id="languageTabsContent">
                    @foreach($languages as $index => $lang)
                    <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" 
                         id="content-{{ $lang->code }}" 
                         role="tabpanel">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th width="30%">Key</th>
                                        <th width="60%">Translation</th>
                                        <th width="10%">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($translations[$lang->code] as $translation)
                                    <tr>
                                        <td>
                                            <strong>{{ $translation->key }}</strong>
                                            @if($translation->description)
                                            <br><small class="text-muted">{{ $translation->description }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <input type="hidden" 
                                                   name="translations[{{ $lang->code }}][{{ $translation->key }}][id]" 
                                                   value="{{ $translation->id }}">
                                            <textarea 
                                                name="translations[{{ $lang->code }}][{{ $translation->key }}][value]" 
                                                class="form-control form-control-sm" 
                                                rows="2"
                                                placeholder="Enter translation...">{{ old('translations.' . $lang->code . '.' . $translation->key . '.value', $translation->value) }}</textarea>
                                            @error('translations.' . $lang->code . '.' . $translation->key . '.value')
                                            <div class="text-danger small">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center">
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger delete-translation"
                                                    data-id="{{ $translation->id }}"
                                                    data-key="{{ $translation->key }}"
                                                    data-lang="{{ $lang->code }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save {{ $lang->name }} Translations
                            </button>
                            <button type="button" class="btn btn-outline-secondary copy-from-default"
                                    data-source="en"
                                    data-target="{{ $lang->code }}"
                                    {{ $lang->code === 'en' ? 'disabled' : '' }}>
                                <i class="fas fa-copy"></i> Copy from English
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row">
        <div class="col-lg-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Keys</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $totalKeys }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-key fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Languages</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $languages->count() }}</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-language fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Completion Rate</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $completionRate }}%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1" aria-labelledby="bulkUpdateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkUpdateModalLabel">Bulk Update Translations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.translations.bulkUpdate', $group) }}">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="language" class="form-label">Language</label>
                        <select class="form-select" id="language" name="language" required>
                            @foreach($languages as $lang)
                            <option value="{{ $lang->code }}">{{ $lang->name }} ({{ $lang->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="bulkContent" class="form-label">JSON Content</label>
                        <textarea class="form-control" id="bulkContent" name="bulk_content" rows="10" 
                                  placeholder='{"key1": "translation1", "key2": "translation2"}'></textarea>
                        <div class="form-text">
                            Enter JSON object with key-value pairs. Keys must exist in this group.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Bulk Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete translation for key "<span id="deleteKey"></span>" in language "<span id="deleteLang"></span>"?
                <form id="deleteForm" method="POST" style="display: none;">
                    @csrf
                    @method('DELETE')
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Copy from default language
        document.querySelectorAll('.copy-from-default').forEach(button => {
            button.addEventListener('click', function() {
                const sourceLang = this.dataset.source;
                const targetLang = this.dataset.target;
                
                // Find all source textareas
                const sourceTextareas = document.querySelectorAll(`#content-${sourceLang} textarea`);
                const targetTextareas = document.querySelectorAll(`#content-${targetLang} textarea`);
                
                if (sourceTextareas.length === targetTextareas.length) {
                    sourceTextareas.forEach((source, index) => {
                        targetTextareas[index].value = source.value;
                    });
                    
                    // Show success message
                    alert('Copied translations from English to ' + targetLang.toUpperCase());
                }
            });
        });
        
        // Delete translation
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        let deleteForm = document.getElementById('deleteForm');
        let deleteKeySpan = document.getElementById('deleteKey');
        let deleteLangSpan = document.getElementById('deleteLang');
        let confirmDeleteBtn = document.getElementById('confirmDelete');
        
        document.querySelectorAll('.delete-translation').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const key = this.dataset.key;
                const lang = this.dataset.lang;
                
                deleteKeySpan.textContent = key;
                deleteLangSpan.textContent = lang;
                deleteForm.action = `/admin/translations/${id}`;
                
                deleteModal.show();
            });
        });
        
        confirmDeleteBtn.addEventListener('click', function() {
            deleteForm.submit();
        });
        
        // Auto-save on tab change
        const languageTabs = document.getElementById('languageTabs');
        if (languageTabs) {
            languageTabs.addEventListener('shown.bs.tab', function(event) {
                // Optional: Auto-save current tab's changes
                console.log('Tab changed to:', event.target.id);
            });
        }
    });
</script>
@endpush