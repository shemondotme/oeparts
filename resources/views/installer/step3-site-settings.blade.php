@extends('layouts.installer')

@section('title', 'Step 3: Site Settings')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">Site Settings</h1>
    <p class="text-muted mb-6">Configure your website's basic information.</p>

    <form method="POST" action="{{ route('installer.process-site-settings') }}">
        @csrf

        <div class="mb-6">
            <label for="site_name" class="block text-sm font-medium text-slate-700 mb-1">
                Site Name
            </label>
            <input type="text" id="site_name" name="site_name" value="{{ old('site_name', 'OeParts') }}"
                class="form-input w-full @error('site_name') border-red-300 @enderror"
                placeholder="OeParts" required>
            @error('site_name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">The name of your website</p>
        </div>

        <div class="mb-6">
            <label for="site_url" class="block text-sm font-medium text-slate-700 mb-1">
                Site URL
            </label>
            <input type="url" id="site_url" name="site_url" value="{{ old('site_url', url('/')) }}"
                class="form-input w-full @error('site_url') border-red-300 @enderror"
                placeholder="https://example.com" required>
            @error('site_url')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">Full URL including protocol (http:// or https://)</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <label for="default_locale" class="block text-sm font-medium text-slate-700 mb-1">
                    Default Language
                </label>
                <select id="default_locale" name="default_locale"
                    class="form-select w-full @error('default_locale') border-red-300 @enderror" required>
                    <option value="en" {{ old('default_locale', 'en') == 'en' ? 'selected' : '' }}>English</option>
                    <option value="de" {{ old('default_locale') == 'de' ? 'selected' : '' }}>German</option>
                    <option value="lt" {{ old('default_locale') == 'lt' ? 'selected' : '' }}>Lithuanian</option>
                    <option value="fr" {{ old('default_locale') == 'fr' ? 'selected' : '' }}>French</option>
                    <option value="es" {{ old('default_locale') == 'es' ? 'selected' : '' }}>Spanish</option>
                </select>
                @error('default_locale')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Primary language for the site</p>
            </div>

            <div>
                <label for="timezone" class="block text-sm font-medium text-slate-700 mb-1">
                    Timezone
                </label>
                <select id="timezone" name="timezone"
                    class="form-select w-full @error('timezone') border-red-300 @enderror" required>
                    @foreach(timezone_identifiers_list() as $tz)
                    <option value="{{ $tz }}" {{ old('timezone', 'UTC') == $tz ? 'selected' : '' }}>{{ $tz }}</option>
                    @endforeach
                </select>
                @error('timezone')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Server timezone for date/time display</p>
            </div>
        </div>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> These settings can be changed later in the admin panel.
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-slate-200">
            <a href="{{ route('installer.database') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Continue
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // Simple timezone search
    document.addEventListener('DOMContentLoaded', function() {
        const timezoneSelect = document.getElementById('timezone');
        const timezones = Array.from(timezoneSelect.options).map(opt => ({
            value: opt.value,
            text: opt.text
        }));

        // Create search input
        const searchDiv = document.createElement('div');
        searchDiv.className = 'mb-2';
        searchDiv.innerHTML = `
            <input type="text" id="timezone-search" 
                   class="form-input w-full text-sm" 
                   placeholder="Search timezone...">
        `;
        timezoneSelect.parentNode.insertBefore(searchDiv, timezoneSelect);

        const searchInput = document.getElementById('timezone-search');
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            timezoneSelect.innerHTML = '';
            
            const filtered = timezones.filter(tz => 
                tz.text.toLowerCase().includes(query) || 
                tz.value.toLowerCase().includes(query)
            );
            
            filtered.forEach(tz => {
                const option = document.createElement('option');
                option.value = tz.value;
                option.textContent = tz.text;
                timezoneSelect.appendChild(option);
            });
            
            // Restore selected value if still in filtered list
            if (filtered.some(tz => tz.value === '{{ old('timezone', 'UTC') }}')) {
                timezoneSelect.value = '{{ old('timezone', 'UTC') }}';
            }
        });
    });
</script>
@endpush
@endsection