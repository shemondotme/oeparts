@extends('layouts.admin')

@section('title', 'Create Section')

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
            <h1 class="text-2xl font-bold text-gray-900">Create New Section</h1>
            <p class="text-gray-600 mt-1">Add a new content block to your homepage or landing pages.</p>
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

    <form action="{{ route('admin.cms.sections.store') }}" method="POST" class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Left Column: Main Settings --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- General Info --}}
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">General Settings</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Section Type <span class="text-red-500">*</span></label>
                            <select name="type" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                <option value="">Select a type...</option>
                                <option value="hero" {{ old('type') === 'hero' ? 'selected' : '' }}>Hero (Search)</option>
                                <option value="trust_bar" {{ old('type') === 'trust_bar' ? 'selected' : '' }}>Trust Bar</option>
                                <option value="how_it_works" {{ old('type') === 'how_it_works' ? 'selected' : '' }}>How It Works</option>
                                <option value="stats_counter" {{ old('type') === 'stats_counter' ? 'selected' : '' }}>Stats Counter</option>
                                <option value="featured_brands" {{ old('type') === 'featured_brands' ? 'selected' : '' }}>Featured Brands</option>
                                <option value="popular_searches" {{ old('type') === 'popular_searches' ? 'selected' : '' }}>Popular Searches</option>
                                <option value="testimonials" {{ old('type') === 'testimonials' ? 'selected' : '' }}>Testimonials</option>
                                <option value="faqs" {{ old('type') === 'faqs' ? 'selected' : '' }}>FAQs</option>
                                <option value="newsletter" {{ old('type') === 'newsletter' ? 'selected' : '' }}>Newsletter</option>
                                <option value="blog_preview" {{ old('type') === 'blog_preview' ? 'selected' : '' }}>Blog Preview</option>
                                <option value="part_inquiry" {{ old('type') === 'part_inquiry' ? 'selected' : '' }}>Part Inquiry</option>
                                <option value="contact_cta" {{ old('type') === 'contact_cta' ? 'selected' : '' }}>Contact CTA</option>
                                <option value="banner" {{ old('type') === 'banner' ? 'selected' : '' }}>Promo Banner</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Location <span class="text-red-500">*</span></label>
                            <select name="location" required class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                @foreach(\App\Enums\SectionLocation::cases() as $loc)
                                    <option value="{{ $loc->value }}" {{ old('location', 'homepage') === $loc->value ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $loc->value)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                            <p class="text-xs text-gray-500 mt-1">Lower numbers appear first.</p>
                        </div>

                        <div class="flex items-center">
                            <input id="is_active" name="is_active" type="checkbox" value="1"
                                   {{ old('is_active', 1) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Multilingual Content --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Title Field --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Title (Internal/Admin) <span class="text-red-500">*</span></label>
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
                                           value="{{ old('title.' . $code) }}"
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
                                        $content = old('content.' . $code, []);
                                    @endphp

                                    {{-- Simple Key-Value Inputs --}}
                                    <div class="grid grid-cols-1 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Headline</label>
                                            <input type="text" name="content[{{ $code }}][headline]"
                                                   value="{{ $content['headline'] ?? '' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Subheadline</label>
                                            <textarea name="content[{{ $code }}][subheadline]" rows="2"
                                                      class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">{{ $content['subheadline'] ?? '' }}</textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Eyebrow / Label</label>
                                            <input type="text" name="content[{{ $code }}][eyebrow]"
                                                   value="{{ $content['eyebrow'] ?? '' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-500 uppercase mb-1">Button Text</label>
                                            <input type="text" name="content[{{ $code }}][button_text]"
                                                   value="{{ $content['button_text'] ?? '' }}"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-[#0B3A68] focus:ring-[#0B3A68]">
                                        </div>
                                    </div>

                                </div>
                            @endforeach
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Note: For complex sections like "How it Works" or "Trust Bar", you may need to edit the JSON directly or use the Seeder for initial setup.</p>
                </div>

            </div>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 flex items-center justify-end gap-4">
            <a href="{{ route('admin.cms.sections.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900 shadow-sm">Create Section</button>
        </div>
    </form>
</div>
@endsection
