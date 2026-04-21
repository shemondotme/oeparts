@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between">
        {{-- Mobile pagination --}}
        <div class="flex justify-between flex-1 sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="relative inline-flex items-center px-4 py-2.5 text-sm font-semibold text-slate-400 bg-white border border-slate-200 cursor-default rounded-xl">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="relative inline-flex items-center px-4 py-2.5 text-sm font-semibold text-navy bg-white border border-slate-200 hover:bg-navy/5 hover:border-navy/30 rounded-xl transition-all duration-200">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="relative inline-flex items-center px-4 py-2.5 ml-3 text-sm font-semibold text-navy bg-white border border-slate-200 hover:bg-navy/5 hover:border-navy/30 rounded-xl transition-all duration-200">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="relative inline-flex items-center px-4 py-2.5 ml-3 text-sm font-semibold text-slate-400 bg-white border border-slate-200 cursor-default rounded-xl">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- Desktop pagination --}}
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-muted">
                    {!! __('Showing') !!}
                    @if ($paginator->firstItem())
                        <span class="font-bold text-navy">{{ $paginator->firstItem() }}</span>
                        {!! __('to') !!}
                        <span class="font-bold text-navy">{{ $paginator->lastItem() }}</span>
                    @else
                        {{ $paginator->count() }}
                    @endif
                    {!! __('of') !!}
                    <span class="font-bold text-navy">{{ $paginator->total() }}</span>
                    {!! __('results') !!}
                </p>
            </div>

            <div>
                <span class="relative z-0 inline-flex items-center gap-1.5 rounded-xl">
                    {{-- Previous Page Link --}}
                    @if ($paginator->onFirstPage())
                        <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                            <span class="relative inline-flex items-center px-3 py-2.5 text-sm font-medium text-slate-400 bg-white border border-slate-200 cursor-default rounded-xl" aria-hidden="true">
                                <x-heroicon-o-chevron-left class="w-5 h-5" />
                            </span>
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="relative inline-flex items-center px-3 py-2.5 text-sm font-medium text-navy bg-white border border-slate-200 hover:bg-navy/5 hover:border-navy/30 rounded-xl transition-all duration-200" aria-label="{{ __('pagination.previous') }}">
                            <x-heroicon-o-chevron-left class="w-5 h-5" />
                        </a>
                    @endif

                    {{-- Pagination Elements --}}
                    @foreach ($elements as $element)
                        {{-- "Three Dots" Separator --}}
                        @if (is_string($element))
                            <span aria-disabled="true">
                                <span class="relative inline-flex items-center px-2 py-2.5 text-sm font-medium text-slate-400 bg-white border border-slate-200 cursor-default">{{ $element }}</span>
                            </span>
                        @endif

                        {{-- Array Of Links --}}
                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                @if ($page == $paginator->currentPage())
                                    <span aria-current="page">
                                        <span class="relative inline-flex items-center px-4 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-navy to-blue-900 border border-navy cursor-default rounded-xl">
                                            {{ $page }}
                                        </span>
                                    </span>
                                @else
                                    <a href="{{ $url }}" class="relative inline-flex items-center px-4 py-2.5 text-sm font-semibold text-navy bg-white border border-slate-200 hover:bg-navy/5 hover:border-navy/30 rounded-xl transition-all duration-200" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                        {{ $page }}
                                    </a>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    {{-- Next Page Link --}}
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="relative inline-flex items-center px-3 py-2.5 text-sm font-medium text-navy bg-white border border-slate-200 hover:bg-navy/5 hover:border-navy/30 rounded-xl transition-all duration-200" aria-label="{{ __('pagination.next') }}">
                            <x-heroicon-o-chevron-right class="w-5 h-5" />
                        </a>
                    @else
                        <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                            <span class="relative inline-flex items-center px-3 py-2.5 text-sm font-medium text-slate-400 bg-white border border-slate-200 cursor-default rounded-xl" aria-hidden="true">
                                <x-heroicon-o-chevron-right class="w-5 h-5" />
                            </span>
                        </span>
                    @endif
                </span>
            </div>
        </div>
    </nav>
@endif
