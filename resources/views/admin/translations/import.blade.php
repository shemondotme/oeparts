@extends('layouts.admin')

@section('title', 'Import Translations')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Import Translations</h1>
                <p class="text-slate-600 mt-2">Upload a JSON or CSV file to import translation strings.</p>
            </div>
            <a href="{{ route('admin.translations.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Translations
            </a>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.translations.import.process') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf

                <div>
                    <label for="file" class="block text-sm font-medium text-slate-900 mb-1">File *</label>
                    <input type="file" id="file" name="file" accept=".json,.csv" required
                           class="block w-full text-sm text-slate-700 border border-slate-300 rounded-lg px-3 py-2 cursor-pointer focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">
                    <p class="mt-1 text-xs text-slate-500">Accepted formats: JSON (<code>.json</code>), CSV (<code>.csv</code>)</p>
                    @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="language" class="block text-sm font-medium text-slate-900 mb-1">Target Language *</label>
                    <select id="language" name="language" required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">
                        @foreach($languages as $lang)
                            <option value="{{ $lang->code }}">{{ $lang->name }} ({{ $lang->code }})</option>
                        @endforeach
                    </select>
                    @error('language') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="group" class="block text-sm font-medium text-slate-900 mb-1">Group</label>
                    <input type="text" id="group" name="group" placeholder="e.g. general"
                           list="existing-groups"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">
                    <datalist id="existing-groups">
                        @foreach($groups as $g)
                            <option value="{{ $g }}">
                        @endforeach
                    </datalist>
                    <p class="mt-1 text-xs text-slate-500">Leave empty to use "general".</p>
                    @error('group') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 mr-2" />
                        Import
                    </button>
                </div>
            </form>
        </div>

        {{-- Format guide --}}
        <div class="mt-6 bg-slate-50 rounded-xl border border-slate-200 p-5">
            <h3 class="text-sm font-semibold text-slate-900 mb-3">File Format Guide</h3>
            <p class="text-sm text-slate-600 mb-2"><strong>JSON format:</strong></p>
            <pre class="bg-white border border-slate-200 rounded-lg p-3 text-xs font-mono overflow-x-auto mb-4">{"key_name": "Translation value", "another_key": "Another value"}</pre>
            <p class="text-sm text-slate-600 mb-2"><strong>CSV format:</strong></p>
            <pre class="bg-white border border-slate-200 rounded-lg p-3 text-xs font-mono overflow-x-auto">key_name,Translation value
another_key,Another value</pre>
        </div>
    </div>
</div>
@endsection
