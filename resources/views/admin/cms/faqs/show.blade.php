@extends('layouts.admin')

@section('title', 'FAQ: ' . trans_field($faq->question))

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.faqs.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">FAQ Detail</h1>
                    @if($faq->is_active)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                    @endif
                </div>
                <p class="text-gray-500 text-sm mt-1">Category: {{ $faq->category ?? '—' }} &bull; Sort: {{ $faq->sort_order }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.faqs.edit', $faq) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </a>
            <form action="{{ route('admin.cms.faqs.destroy', $faq) }}" method="POST"
                  onsubmit="return confirm('Delete this FAQ?');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50">
                    <x-heroicon-o-trash class="w-4 h-4" />
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="max-w-3xl">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ lang: 'en' }">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900">Content</h2>
                <div class="flex gap-1">
                    @foreach(['en','de','lt','fr','es'] as $lang)
                        <button type="button"
                                @click="lang = '{{ $lang }}'"
                                :class="lang === '{{ $lang }}' ? 'bg-[#0B3A68] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                class="px-2.5 py-1 text-xs font-semibold rounded transition-colors">
                            {{ strtoupper($lang) }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="p-6">
                @foreach(['en','de','lt','fr','es'] as $lang)
                    <div x-show="lang === '{{ $lang }}'">
                        <div class="mb-4">
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Question</p>
                            <p class="text-base font-medium text-gray-900">
                                {{ $faq->question[$lang] ?? '' }}
                            </p>
                            @if(empty($faq->question[$lang]))
                                <p class="text-sm text-gray-400 italic">No question for {{ strtoupper($lang) }}</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Answer</p>
                            <div class="text-sm text-gray-700 whitespace-pre-wrap">{{ $faq->answer[$lang] ?? '' }}</div>
                            @if(empty($faq->answer[$lang]))
                                <p class="text-sm text-gray-400 italic">No answer for {{ strtoupper($lang) }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
