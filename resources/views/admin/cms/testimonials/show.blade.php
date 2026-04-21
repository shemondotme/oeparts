@extends('layouts.admin')

@section('title', 'Testimonial: ' . $testimonial->author_name)

@section('content')
<div class="px-6 py-8">
    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.testimonials.index') }}" class="text-gray-500 hover:text-gray-700">
                <x-heroicon-o-arrow-left class="w-5 h-5" />
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ $testimonial->author_name }}</h1>
                    @if($testimonial->is_approved)
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Approved</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                    @endif
                    @if($testimonial->featured)
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                            <x-heroicon-s-star class="w-3 h-3" /> Featured
                        </span>
                    @endif
                </div>
                @if($testimonial->author_title || $testimonial->author_company)
                    <p class="text-gray-500 text-sm mt-1">
                        {{ $testimonial->author_title }}
                        @if($testimonial->author_title && $testimonial->author_company) at @endif
                        {{ $testimonial->author_company }}
                    </p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.testimonials.edit', $testimonial) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-[#0B3A68] text-white rounded-lg text-sm font-medium hover:bg-blue-900">
                <x-heroicon-o-pencil-square class="w-4 h-4" />
                Edit
            </a>
            <form action="{{ route('admin.cms.testimonials.destroy', $testimonial) }}" method="POST"
                  onsubmit="return confirm('Delete this testimonial?');" class="inline">
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

    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            {{-- Content Preview --}}
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" x-data="{ lang: 'en' }">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-gray-900">Testimonial Text</h2>
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
                            @if(!empty($testimonial->content[$lang]))
                                <blockquote class="text-gray-700 italic text-base leading-relaxed border-l-4 border-amber-400 pl-4">
                                    "{{ $testimonial->content[$lang] }}"
                                </blockquote>
                            @else
                                <p class="text-sm text-gray-400 italic">No content for {{ strtoupper($lang) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Details</h2>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Rating</dt>
                        <dd class="mt-1 flex items-center gap-1">
                            @for($i = 1; $i <= 5; $i++)
                                @if($i <= $testimonial->rating)
                                    <x-heroicon-s-star class="w-4 h-4 text-amber-400" />
                                @else
                                    <x-heroicon-o-star class="w-4 h-4 text-gray-300" />
                                @endif
                            @endfor
                            <span class="text-sm text-gray-600 ml-1">{{ $testimonial->rating }}/5</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                        <dd class="mt-1 flex gap-2">
                            <form action="{{ route('admin.cms.testimonials.toggle-approval', $testimonial) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs text-[#0B3A68] hover:underline">
                                    {{ $testimonial->is_approved ? 'Revoke approval' : 'Approve' }}
                                </button>
                            </form>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Featured</dt>
                        <dd class="mt-1">
                            <form action="{{ route('admin.cms.testimonials.toggle-featured', $testimonial) }}" method="POST">
                                @csrf
                                <button type="submit" class="text-xs text-[#0B3A68] hover:underline">
                                    {{ $testimonial->featured ? 'Remove from featured' : 'Mark as featured' }}
                                </button>
                            </form>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase">Added</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $testimonial->created_at->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
