@extends('layouts.admin')

@section('title', 'Scan for Translation Strings')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-5xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Scan for Translation Strings</h1>
                <p class="text-slate-600 mt-2">Scanned {{ $count }} unique translation keys from Blade templates.</p>
            </div>
            <a href="{{ route('admin.translations.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Translations
            </a>
        </div>

        @if($count === 0)
            <div class="bg-white rounded-xl border border-slate-200 p-10 text-center">
                <x-heroicon-o-magnifying-glass class="w-12 h-12 text-slate-400 mx-auto mb-4" />
                <h3 class="text-lg font-semibold text-slate-900">No translation keys found</h3>
                <p class="text-slate-500 mt-2">No <code>__()</code> calls were detected in Blade templates.</p>
            </div>
        @else
            <form method="POST" action="{{ route('admin.translations.scan.process') }}">
                @csrf

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                        <h3 class="font-semibold text-slate-900">Found Keys ({{ $count }})</h3>
                        <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                            <input type="checkbox" id="select-all" class="w-4 h-4 text-navy border-slate-300 rounded">
                            Select All
                        </label>
                    </div>
                    <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                        @foreach($strings as $key => $value)
                            <div class="px-6 py-3 flex items-center gap-3">
                                <input type="checkbox"
                                       name="strings[]"
                                       value="{{ $key }}"
                                       class="w-4 h-4 text-navy border-slate-300 rounded string-checkbox"
                                       checked>
                                <span class="font-mono text-sm text-slate-800">{{ $key }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <div class="mb-4">
                        <label for="group" class="block text-sm font-medium text-slate-900 mb-1">Add to Group</label>
                        <input type="text"
                               id="group"
                               name="group"
                               required
                               placeholder="e.g. general"
                               class="w-full max-w-sm px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">
                        @error('group')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                        Add Selected Keys
                    </button>
                </div>
            </form>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.getElementById('select-all')?.addEventListener('change', function () {
    document.querySelectorAll('.string-checkbox').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
