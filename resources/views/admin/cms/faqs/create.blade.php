@extends('layouts.admin')

@section('title', 'Create FAQ')

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.faqs.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Create FAQ</h1>
                <p class="text-gray-600 mt-1">Add a new frequently asked question</p>
            </div>
        </div>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <form action="{{ route('admin.cms.faqs.store') }}" method="POST">
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
                    {{-- Category & Sort --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700 mb-1">
                                Category <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="category" name="category"
                                   value="{{ old('category') }}"
                                   required maxlength="100"
                                   list="category-list"
                                   placeholder="e.g., Shipping, Returns"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <datalist id="category-list">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">
                                @endforeach
                            </datalist>
                        </div>
                        <div>
                            <label for="sort_order" class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                            <input type="number" id="sort_order" name="sort_order"
                                   value="{{ old('sort_order', 0) }}" min="0"
                                   class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>

                    {{-- Active --}}
                    <div class="flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0B3A68] focus:ring-[#0B3A68]">
                        <label for="is_active" class="text-sm text-gray-700">FAQ is active (visible on site)</label>
                    </div>

                    {{-- Question --}}
                    <div x-data="{ lang: 'en' }">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Question <span class="text-red-500">*</span>
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
                            <input type="text"
                                   name="question[{{ $lang }}]"
                                   x-show="lang === '{{ $lang }}'"
                                   value="{{ old('question.'.$lang) }}"
                                   placeholder="Question ({{ strtoupper($lang) }})"
                                   {{ $lang === 'en' ? 'required' : '' }}
                                   maxlength="255"
                                   class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">
                        @endforeach
                    </div>

                    {{-- Answer --}}
                    <div x-data="{ lang: 'en' }">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Answer <span class="text-red-500">*</span>
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
                            <textarea name="answer[{{ $lang }}]"
                                      x-show="lang === '{{ $lang }}'"
                                      rows="5"
                                      placeholder="Answer ({{ strtoupper($lang) }})"
                                      {{ $lang === 'en' ? 'required' : '' }}
                                      class="w-full rounded-lg border-gray-300 text-sm focus:ring-amber-500 focus:border-amber-500">{{ old('answer.'.$lang) }}</textarea>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end gap-3">
                    <a href="{{ route('admin.cms.faqs.index') }}"
                       class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                        Create FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
