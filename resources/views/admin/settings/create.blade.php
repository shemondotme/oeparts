@extends('layouts.admin')

@section('title', 'Create New Setting')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-2xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Create New Setting</h1>
                    <p class="text-slate-600 mt-2">Add a custom setting to the system.</p>
                </div>
                <a href="{{ route('admin.settings.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Back to Settings
                </a>
            </div>

            {{-- Breadcrumb --}}
            <nav class="flex items-center text-sm text-slate-500 mt-4">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-700">Dashboard</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <a href="{{ route('admin.settings.index') }}" class="hover:text-slate-700">Settings</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <span class="text-slate-900 font-medium">Create New</span>
            </nav>
        </div>

        {{-- Create Form --}}
        <form method="POST" action="{{ route('admin.settings.store') }}" class="space-y-6">
            @csrf

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="space-y-6">
                        {{-- Group --}}
                        <div>
                            <label for="group" class="block text-sm font-medium text-slate-900 mb-2">
                                Group <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <select id="group" name="group" 
                                            class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                            required>
                                        <option value="">Select a group</option>
                                        <option value="general">General</option>
                                        <option value="contact">Contact</option>
                                        <option value="announcement">Announcement</option>
                                        <option value="appearance">Appearance</option>
                                        <option value="seo">SEO</option>
                                        <option value="search">Search</option>
                                        <option value="cart">Cart</option>
                                        <option value="checkout">Checkout</option>
                                        <option value="payment">Payment</option>
                                        <option value="mail">Mail</option>
                                        <option value="sms">SMS</option>
                                        <option value="api">API</option>
                                        <option value="security">Security</option>
                                        <option value="performance">Performance</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="custom">Custom</option>
                                    </select>
                                    <p class="mt-1 text-sm text-slate-500">Category for this setting</p>
                                </div>
                                <div>
                                    <input type="text" 
                                           id="custom_group" 
                                           name="custom_group" 
                                           placeholder="Or enter custom group name"
                                           class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                                    <p class="mt-1 text-sm text-slate-500">Lowercase, underscores allowed</p>
                                </div>
                            </div>
                            @error('group')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Key --}}
                        <div>
                            <label for="key" class="block text-sm font-medium text-slate-900 mb-2">
                                Key <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="key" 
                                   name="key" 
                                   value="{{ old('key') }}"
                                   class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                   placeholder="e.g., api_timeout, max_upload_size"
                                   required>
                            <p class="mt-1 text-sm text-slate-500">Unique identifier (lowercase, underscores)</p>
                            @error('key')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Type --}}
                        <div>
                            <label for="type" class="block text-sm font-medium text-slate-900 mb-2">
                                Data Type <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-3 gap-4">
                                @foreach(['string' => 'Text', 'boolean' => 'Yes/No', 'integer' => 'Number', 'decimal' => 'Decimal', 'json' => 'JSON', 'encrypted' => 'Encrypted'] as $typeValue => $typeLabel)
                                    <div class="relative">
                                        <input type="radio" 
                                               id="type_{{ $typeValue }}" 
                                               name="type" 
                                               value="{{ $typeValue }}"
                                               {{ old('type') === $typeValue ? 'checked' : ($typeValue === 'string' ? 'checked' : '') }}
                                               class="sr-only peer">
                                        <label for="type_{{ $typeValue }}" 
                                               class="block p-4 border border-slate-300 rounded-lg cursor-pointer text-center hover:bg-slate-50 peer-checked:border-navy peer-checked:bg-navy/5 transition-colors">
                                            <div class="font-medium text-slate-900">{{ $typeLabel }}</div>
                                            <div class="text-xs text-slate-500 mt-1">{{ $typeValue }}</div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Value --}}
                        <div>
                            <label for="value" class="block text-sm font-medium text-slate-900 mb-2">
                                Default Value
                            </label>
                            <div id="value_input_container">
                                <input type="text" 
                                       id="value" 
                                       name="value" 
                                       value="{{ old('value') }}"
                                       class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                       placeholder="Enter default value">
                            </div>
                            <p class="mt-1 text-sm text-slate-500">Initial value for this setting</p>
                            @error('value')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <label for="description" class="block text-sm font-medium text-slate-900 mb-2">
                                Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="3"
                                      class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                      placeholder="Optional description of what this setting controls">{{ old('description') }}</textarea>
                            <p class="mt-1 text-sm text-slate-500">Help text for administrators</p>
                        </div>
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-sm text-slate-500">
                        <x-heroicon-o-information-circle class="w-4 h-4 inline mr-1" />
                        New settings will be available immediately after creation.
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" 
                                onclick="window.location.href='{{ route('admin.settings.index') }}'"
                                class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors flex items-center">
                            <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                            Create Setting
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Help Section --}}
        <div class="mt-8 bg-slate-50 rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-900 mb-4 flex items-center">
                <x-heroicon-o-question-mark-circle class="w-5 h-5 mr-2" />
                About Setting Types
            </h3>
            <div class="grid grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">String</h4>
                    <p class="text-sm text-slate-600">Plain text values like site name, URLs, email addresses.</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Boolean</h4>
                    <p class="text-sm text-slate-600">True/false values for toggles (enabled/disabled).</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Integer/Decimal</h4>
                    <p class="text-sm text-slate-600">Numeric values for quantities, limits, thresholds.</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">JSON</h4>
                    <p class="text-sm text-slate-600">Structured data like arrays, objects, multilingual text.</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Encrypted</h4>
                    <p class="text-sm text-slate-600">Sensitive data like API keys, passwords (stored encrypted).</p>
                </div>
                <div>
                    <h4 class="font-medium text-slate-900 mb-2">Best Practices</h4>
                    <p class="text-sm text-slate-600">Use descriptive keys, group related settings, add helpful descriptions.</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Dynamic Value Input Script --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const valueContainer = document.getElementById('value_input_container');
    
    function updateValueInput() {
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        const currentValue = document.getElementById('value')?.value || '';
        
        let html = '';
        
        switch(selectedType) {
            case 'boolean':
                html = `
                    <div class="flex items-center">
                        <input type="hidden" name="value" value="0">
                        <input type="checkbox" 
                               id="value" 
                               name="value" 
                               value="1"
                               ${currentValue === '1' ? 'checked' : ''}
                               class="w-4 h-4 text-navy border-slate-300 rounded focus:ring-navy focus:ring-2">
                        <label for="value" class="ml-3 text-sm text-slate-700">
                            Enabled by default
                        </label>
                    </div>
                `;
                break;
                
            case 'json':
                html = `
                    <textarea 
                        id="value" 
                        name="value" 
                        rows="4"
                        class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                        placeholder='{"en": "Text in English", "de": "Text auf Deutsch"}'>${currentValue}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Enter valid JSON (for multilingual use {"en": "...", "de": "..."})</p>
                `;
                break;
                
            case 'integer':
                html = `
                    <input type="number" 
                           id="value" 
                           name="value" 
                           value="${currentValue}"
                           step="1"
                           class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                           placeholder="Enter a whole number">
                `;
                break;
                
            case 'decimal':
                html = `
                    <input type="number" 
                           id="value" 
                           name="value" 
                           value="${currentValue}"
                           step="0.01"
                           class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                           placeholder="Enter a decimal number">
                `;
                break;
                
            case 'encrypted':
                html = `
                    <input type="password" 
                           id="value" 
                           name="value" 
                           value=""
                           class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                           placeholder="Enter sensitive value (will be encrypted)">
                    <p class="mt-1 text-xs text-slate-500">This value will be encrypted in the database</p>
                `;
                break;
                
            default: // string
                html = `
                    <input type="text" 
                           id="value" 
                           name="value" 
                           value="${currentValue}"
                           class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                           placeholder="Enter text value">
                `;
        }
        
        valueContainer.innerHTML = html;
    }
    
    // Initial setup
    updateValueInput();
    
    // Update on type change
    typeRadios.forEach(radio => {
        radio.addEventListener('change', updateValueInput);
    });
    
    // Group selection logic
    const groupSelect = document.getElementById('group');
    const customGroupInput = document.getElementById('custom_group');
    
    groupSelect.addEventListener('change', function() {
        if (this.value === 'custom') {
            customGroupInput.disabled = false;
            customGroupInput.focus();
        } else {
            customGroupInput.disabled = true;
            customGroupInput.value = '';
        }
    });
    
    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // If custom group is selected but empty
        if (groupSelect.value === 'custom' && !customGroupInput.value.trim()) {
            e.preventDefault();
            alert('Please enter a custom group name.');
            customGroupInput.focus();
            return;
        }
        
        // Validate JSON if type is json
        const selectedType = document.querySelector('input[name="type"]:checked').value;
        if (selectedType === 'json') {
            const valueInput = document.getElementById('value');
            if (valueInput && valueInput.value.trim()) {
                try {
                    JSON.parse(valueInput.value);
                } catch (err) {
                    e.preventDefault();
                    alert('Invalid JSON format. Please check your JSON syntax.');
                    valueInput.focus();
                    return;
                }
            }
        }
    });
});
</script>
@endpush
@endsection