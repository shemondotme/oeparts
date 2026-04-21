@extends('layouts.admin')

@section('title', 'Edit ' . ucfirst($group) . ' Settings')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 capitalize">Edit {{ str_replace('_', ' ', $group) }} Settings</h1>
                    <p class="text-slate-600 mt-2">Configure settings for this category. Changes are saved immediately.</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.settings.index') }}" 
                       class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                        Back to All Settings
                    </a>
                    @if(in_array($group, ['payment', 'mail', 'sms', 'api']))
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            <x-heroicon-o-lock-closed class="w-3 h-3 mr-1" />
                            Encrypted
                        </span>
                    @endif
                </div>
            </div>

            {{-- Breadcrumb --}}
            <nav class="flex items-center text-sm text-slate-500 mt-4">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-700">Dashboard</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <a href="{{ route('admin.settings.index') }}" class="hover:text-slate-700">Settings</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <span class="text-slate-900 font-medium capitalize">{{ str_replace('_', ' ', $group) }}</span>
            </nav>
        </div>

        {{-- Settings Form --}}
        <form method="POST" action="{{ route('admin.settings.update', $group) }}" class="space-y-8">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="space-y-6">
                        @foreach($settings as $setting)
                            <div class="pb-6 border-b border-slate-100 last:border-b-0 last:pb-0">
                                <div class="flex items-start justify-between mb-3">
                                    <div>
                                        <label for="setting_{{ $setting->key }}" class="block text-sm font-medium text-slate-900">
                                            {{ str_replace('_', ' ', $setting->key) }}
                                            @if($setting->type === 'encrypted')
                                                <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                    <x-heroicon-o-lock-closed class="w-3 h-3 mr-0.5" />
                                                    Encrypted
                                                </span>
                                            @endif
                                        </label>
                                        <p class="text-sm text-slate-500 mt-1">
                                            Type: <span class="font-mono text-xs">{{ $setting->type }}</span>
                                            @if($setting->description)
                                                • {{ $setting->description }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($setting->type === 'boolean')
                                            <span class="text-xs font-medium px-2 py-1 rounded {{ $setting->value ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-800' }}">
                                                {{ $setting->value ? 'Enabled' : 'Disabled' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Input Field Based on Type --}}
                                @if($setting->type === 'boolean')
                                    <div class="flex items-center">
                                        <input type="hidden" name="settings[{{ $setting->key }}]" value="0">
                                        <input type="checkbox" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="1"
                                               {{ $setting->value ? 'checked' : '' }}
                                               class="w-4 h-4 text-navy border-slate-300 rounded focus:ring-navy focus:ring-2">
                                        <label for="setting_{{ $setting->key }}" class="ml-3 text-sm text-slate-700">
                                            Enable this setting
                                        </label>
                                    </div>
                                @elseif($setting->type === 'json')
                                    <div>
                                        <textarea 
                                            id="setting_{{ $setting->key }}" 
                                            name="settings[{{ $setting->key }}]" 
                                            rows="4"
                                            class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                            placeholder="Enter JSON data...">{{ is_string($setting->value) ? $setting->value : json_encode($setting->value, JSON_PRETTY_PRINT) }}</textarea>
                                        <p class="mt-1 text-xs text-slate-500">Valid JSON format required</p>
                                    </div>
                                @elseif($setting->type === 'integer' || $setting->type === 'decimal')
                                    <div>
                                        <input type="number" 
                                               id="setting_{{ $setting->key }}" 
                                               name="settings[{{ $setting->key }}]" 
                                               value="{{ $setting->value }}"
                                               step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}"
                                               class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                                    </div>
                                @else
                                    <div>
                                        @if($setting->key === 'password' || $setting->type === 'encrypted')
                                            <input type="password" 
                                                   id="setting_{{ $setting->key }}" 
                                                   name="settings[{{ $setting->key }}]" 
                                                   value=""
                                                   autocomplete="new-password"
                                                   class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm"
                                                   placeholder="Leave blank to keep current value">
                                            <p class="mt-1 text-xs text-slate-500">Leave empty to keep current encrypted value</p>
                                        @else
                                            <input type="text" 
                                                   id="setting_{{ $setting->key }}" 
                                                   name="settings[{{ $setting->key }}]" 
                                                   value="{{ $setting->value }}"
                                                   class="block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                                        @endif
                                    </div>
                                @endif

                                {{-- Validation Error --}}
                                @error('settings.' . $setting->key)
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Form Actions --}}
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-sm text-slate-500">
                        <x-heroicon-o-information-circle class="w-4 h-4 inline mr-1" />
                        Settings are cached for performance. Changes may take a few seconds to reflect.
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" 
                                onclick="window.location.reload()"
                                class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                            Reset Changes
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors flex items-center">
                            <x-heroicon-o-check class="w-4 h-4 mr-2" />
                            Save All Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Danger Zone --}}
        @if(in_array($group, ['general', 'payment', 'mail']))
            <div class="mt-8 bg-white rounded-xl border border-red-200 overflow-hidden">
                <div class="px-6 py-4 bg-red-50 border-b border-red-200">
                    <h3 class="text-lg font-semibold text-red-900 flex items-center">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 mr-2" />
                        Danger Zone
                    </h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-medium text-slate-900">Reset to Defaults</h4>
                            <p class="text-sm text-slate-600 mt-1">Reset all settings in this group to their default values.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.settings.destroy', $group) }}" 
                              onsubmit="return confirm('Are you sure you want to reset all {{ $group }} settings to defaults? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors flex items-center">
                                <x-heroicon-o-arrow-path class="w-4 h-4 mr-2" />
                                Reset to Defaults
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- JSON Validation Script --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validate JSON fields before submission
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const jsonTextareas = document.querySelectorAll('textarea[name^="settings["]');
        let hasError = false;
        
        jsonTextareas.forEach(textarea => {
            try {
                if (textarea.value.trim()) {
                    JSON.parse(textarea.value);
                }
            } catch (err) {
                hasError = true;
                const errorDiv = document.createElement('p');
                errorDiv.className = 'mt-1 text-sm text-red-600';
                errorDiv.textContent = 'Invalid JSON format: ' + err.message;
                
                // Remove existing error
                const existingError = textarea.nextElementSibling;
                if (existingError && existingError.classList.contains('text-red-600')) {
                    existingError.remove();
                }
                
                textarea.parentNode.appendChild(errorDiv);
                textarea.classList.add('border-red-300', 'focus:ring-red-500', 'focus:border-red-500');
            }
        });
        
        if (hasError) {
            e.preventDefault();
            alert('Please fix JSON validation errors before saving.');
        }
    });
    
    // Clear error on textarea input
    document.querySelectorAll('textarea[name^="settings["]').forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.classList.remove('border-red-300', 'focus:ring-red-500', 'focus:border-red-500');
            const error = this.nextElementSibling;
            if (error && error.classList.contains('text-red-600')) {
                error.remove();
            }
        });
    });
});
</script>
@endpush
@endsection