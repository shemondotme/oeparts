@extends('layouts.admin')

@section('title', 'Edit Translation')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Edit Translation</h1>
                <p class="text-slate-600 mt-2 font-mono text-sm">{{ $string->group }}.{{ $string->key }}</p>
            </div>
            <a href="{{ route('admin.translations.group', $string->group) }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back to Group
            </a>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.translations.update', $string->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-6">
                    <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                        <div>
                            <span class="font-medium text-slate-600">Group:</span>
                            <span class="ml-2 font-mono text-slate-900">{{ $string->group }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-slate-600">Key:</span>
                            <span class="ml-2 font-mono text-slate-900">{{ $string->key }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-slate-600">Language:</span>
                            <span class="ml-2 text-slate-900">{{ $string->lang_code }}</span>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <label for="value" class="block text-sm font-medium text-slate-900 mb-2">Translation Value *</label>
                    <textarea id="value"
                              name="value"
                              rows="4"
                              required
                              class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy">{{ old('value', $string->value) }}</textarea>
                    @error('value') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Other language versions for reference --}}
                @if($translations->count() > 1)
                    <div class="mb-6 p-4 bg-slate-50 rounded-lg border border-slate-200">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Other Languages (Reference)</p>
                        <dl class="space-y-2">
                            @foreach($translations as $lang => $t)
                                @if($lang !== $string->lang_code)
                                    <div class="flex gap-3 text-sm">
                                        <dt class="font-mono text-xs text-slate-500 w-8 pt-0.5">{{ $lang }}</dt>
                                        <dd class="text-slate-700">{{ $t->value ?: '—' }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    </div>
                @endif

                <div class="flex gap-3">
                    <button type="submit"
                            class="px-5 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-check class="w-4 h-4 inline mr-1" />
                        Save
                    </button>
                    <a href="{{ route('admin.translations.group', $string->group) }}"
                       class="px-5 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
