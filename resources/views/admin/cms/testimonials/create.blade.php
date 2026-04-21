@extends('layouts.admin')

@section('title', 'Create Testimonial')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.testimonials.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create Testimonial</h1>
                <p class="text-gray-600 mt-1">Add a new customer testimonial</p>
            </div>
        </div>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <form action="{{ route('admin.cms.testimonials.store') }}" method="POST">
                @csrf

                @if($errors->any())
                    <div class="p-6 border-b border-gray-100 bg-red-50">
                        <ul class="text-sm text-red-700 space-y-1">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="p-6 space-y-6">
                    {{-- Author Info --}}
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="author_name" class="block text-sm font-medium text-gray-700 mb-1">
                                Author Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="author_name" name="author_name"
                                   value="{{ old('author_name') }}"
                                   required maxlength="100"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="author_title" class="block text-sm font-medium text-gray-700 mb-1">Job Title</label>
                            <input type="text" id="author_title" name="author_title"
                                   value="{{ old('author_title') }}"
                                   maxlength="100"
                                   placeholder="e.g., Fleet Manager"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label for="author_company" class="block text-sm font-medium text-gray-700 mb-1">Company</label>
                            <input type="text" id="author_company" name="author_company"
                                   value="{{ old('author_company') }}"
                                   maxlength="100"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>

                    {{-- Rating --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Rating <span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-center gap-2" x-data="{ rating: {{ old('rating', 5) }} }">
                            @for($i = 1; $i <= 5; $i++)
                                <label class="cursor-pointer">
                                    <input type="radio" name="rating" value="{{ $i }}"
                                           x-model="rating"
                                           class="sr-only">
                                    <x-heroicon-s-star class="w-7 h-7 transition-colors"
                                                        :class="rating >= {{ $i }} ? 'text-amber-400' : 'text-gray-200'" />
                                </label>
                            @endfor
                            <span class="text-sm text-gray-500 ml-2" x-text="rating + ' / 5'"></span>
                        </div>
                    </div>

                    {{-- Content --}}
                    <div x-data="{ lang: 'en' }">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Testimonial Content <span class="text-red-500">*</span>
                        </label>
                        <div class="flex gap-1 mb-2">
                            @foreach(['en','de','lt','fr','es'] as $lang)
                                <button type="button"
                                        @click="lang = '{{ $lang }}'"
                                        :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                        class="px-3 py-1.5 text-xs font-semibold rounded transition-colors">
                                    {{ strtoupper($lang) }}
                                </button>
                            @endforeach
                        </div>
                        @foreach(['en','de','lt','fr','es'] as $lang)
                            <textarea name="content[{{ $lang }}]"
                                      x-show="lang === '{{ $lang }}'"
                                      rows="4"
                                      maxlength="1000"
                                      placeholder="Testimonial text ({{ strtoupper($lang) }})"
                                      {{ $lang === 'en' ? 'required' : '' }}
                                      class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('content.'.$lang) }}</textarea>
                        @endforeach
                    </div>

                    {{-- Checkboxes --}}
                    <div class="flex items-center gap-6">
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="is_approved" value="0">
                            <input type="checkbox" id="is_approved" name="is_approved" value="1"
                                   {{ old('is_approved') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="is_approved" class="text-sm text-gray-700">Approved (visible on site)</label>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="featured" value="0">
                            <input type="checkbox" id="featured" name="featured" value="1"
                                   {{ old('featured') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                            <label for="featured" class="text-sm text-gray-700">Featured</label>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <a href="{{ route('admin.cms.testimonials.index') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        Create Testimonial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
