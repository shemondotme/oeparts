@extends('layouts.admin')

@section('title', 'Edit Section: ' . trans_field($section->title))

@push('styles')
<style>
    /* Simple tab styles for multilingual inputs */
    .lang-tabs { display: flex; border-bottom: 1px solid #e5e7eb; margin-bottom: 1rem; }
    .lang-tab { padding: 0.5rem 1rem; cursor: pointer; border: 1px solid transparent; border-bottom: none; border-radius: 0.375rem 0.375rem 0 0; background: #f9fafb; font-size: 0.875rem; font-weight: 500; color: #6b7280; }
    .lang-tab.active { background: white; border-color: #e5e7eb; border-bottom-color: white; color: #0B3A68; margin-bottom: -1px; z-index: 10; }
    .lang-pane { display: none; }
    .lang-pane.active { display: block; }
</style>
@endpush

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Edit Section</h1>
            <p class="text-gray-600 mt-1">Update content for <span class="font-mono text-sm bg-gray-100 px-2 py-1 rounded">{{ $section->type }}</span></p>
        </div>
        <a href="{{ route('admin.cms.sections.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← Back to list</a>
    </div>

    @if ($errors->any())
        <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
            <ul class="list-disc list-inside text-sm">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.cms.sections.update', $section) }}" method="POST" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left Column: Main Settings --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- General Info --}}
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">General Settings</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section Type</label>
                            <input type="text" name="type" value="{{ old('type', $section->type) }}" readonly
                                   class="w-full rounded-lg border-gray-300 bg-gray-50 text-gray-500 text-sm cursor-not-allowed">
                            <p class="text-xs text-gray-500 mt-1">Type cannot be changed after creation.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                            <select name="location" class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                @foreach(\App\Enums\SectionLocation::cases() as $loc)
                                    <option value="{{ $loc->value }}" {{ old('location', $section->location->value) === $loc->value ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $loc->value)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ old('sort_order', $section->sort_order) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                        </div>

                        <div class="flex items-center">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                   {{ old('is_active', $section->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                    </div>
                </div>

                {{-- Help Text --}}
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                    <h4 class="text-sm font-bold text-blue-900 mb-2">Tip</h4>
                    <p class="text-xs text-blue-800">
                        Changes to sections are reflected on the frontend immediately.
                        Ensure all language fields are filled for the best user experience.
                    </p>
                </div>
            </div>

            {{-- Right Column: Multilingual Content --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Title Field --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Title (Internal/Admin)</label>
                    <div x-data="{ lang: 'en' }" class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex bg-gray-50 border-b border-gray-200">
                            @foreach(['en' => 'English', 'de' => 'German', 'lt' => 'Lithuanian', 'fr' => 'French', 'es' => 'Spanish'] as $code => $name)
                                <button type="button" @click="lang = '{{ $code }}'"
                                        :class="{ 'bg-white text-[#0B3A68] border-t-2 border-[#0B3A68]': lang === '{{ $code }}', 'text-gray-500 hover:text-gray-700': lang !== '{{ $code }}' }"
                                        class="px-4 py-2 text-xs font-medium transition-colors">
                                    {{ $name }}
                                </button>
                            @endforeach
                        </div>
                        <div class="p-4 bg-white">
                            @foreach(['en', 'de', 'lt', 'fr', 'es'] as $code)
                                <div x-show="lang === '{{ $code }}'" class="space-y-2">
                                    <input type="text" name="title[{{ $code }}]"
                                           value="{{ old('title.' . $code, $section->title[$code] ?? '') }}"
                                           placeholder="Title in {{ $code }}"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Dynamic Content Fields based on Type --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Content</label>
                    <div x-data="{ lang: 'en' }" class="border border-gray-200 rounded-lg overflow-hidden">
                        <div class="flex bg-gray-50 border-b border-gray-200">
                            @foreach(['en' => 'English', 'de' => 'German', 'lt' => 'Lithuanian', 'fr' => 'French', 'es' => 'Spanish'] as $code => $name)
                                <button type="button" @click="lang = '{{ $code }}'"
                                        :class="{ 'bg-white text-[#0B3A68] border-t-2 border-[#0B3A68]': lang === '{{ $code }}', 'text-gray-500 hover:text-gray-700': lang !== '{{ $code }}' }"
                                        class="px-4 py-2 text-xs font-medium transition-colors">
                                    {{ $name }}
                                </button>
                            @endforeach
                        </div>

                        <div class="p-4 bg-white space-y-4">
                            @foreach(['en', 'de', 'lt', 'fr', 'es'] as $code)
                                <div x-show="lang === '{{ $code }}'">

                                    {{-- Standard Fields for most sections --}}
                                    @php
                                        $content = old('content.' . $code, $section->content[$code] ?? []);
                                        // If it's a flat array of strings, use standard inputs.
                                        // If it has nested arrays (like 'items'), handle differently.
                                        $isComplex = is_array($content) && count(array_filter($content, 'is_array')) > 0;
                                    @endphp

                                    @if(!$isComplex)
                                        {{-- Simple Key-Value Inputs --}}
                                        @foreach(['headline', 'subheadline', 'eyebrow', 'button_text', 'placeholder', 'cta_text'] as $key)
                                            <div>
                                                <label class="block text-xs font-medium text-gray-500 uppercase mb-1">{{ str_replace('_', ' ', $key) }}</label>
                                                @if($key === 'subheadline' || $key === 'description')
                                                    <textarea name="content[{{ $code }}][{{ $key }}]" rows="2"
                                                              class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">{{ $content[$key] ?? '' }}</textarea>
                                                @else
                                                    <input type="text" name="content[{{ $code }}][{{ $key }}]"
                                                           value="{{ $content[$key] ?? '' }}"
                                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                                @endif
                                            </div>
                                        @endforeach

                                        {{-- URL Field if needed --}}
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">CTA URL</label>
                                            <input type="text" name="content[{{ $code }}][cta_url]"
                                                   value="{{ $content['cta_url'] ?? '' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                        </div>
                                    @else
                                        <div class="p-4 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                                            Complex content structure detected. Please use the JSON editor or specific section controls if available.
                                            <textarea name="content[{{ $code }}]" rows="10" class="mt-2 w-full font-mono text-xs">{{ json_encode($content, JSON_PRETTY_PRINT) }}</textarea>
                                        </div>
                                    @endif

                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-end gap-4">
            <a href="{{ route('admin.cms.sections.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900 shadow-sm">Save Changes</button>
        </div>
    </form>
</div>
@endsection
