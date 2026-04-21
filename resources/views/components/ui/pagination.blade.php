@if ($paginator->hasPages())
    {{-- ══════════════════════════════════════════════════════════════
         INDUSTRIAL BLUEPRINT — Pagination Ledger
         ══════════════════════════════════════════════════════════════ --}}
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}"
         class="border border-ink bg-paper">

        {{-- ─── Mobile ─── --}}
        <div class="flex items-center sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-r border-rule
                             font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-ink-muted/50 bg-ivory-alt cursor-not-allowed">
                    <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   class="flex-1 flex items-center justify-center gap-2 px-4 py-3 border-r border-rule
                          font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-ink
                          hover:bg-ink hover:text-ivory transition-colors">
                    <x-heroicon-s-arrow-long-left class="w-4 h-4" />
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            <span class="px-4 py-3 font-mono text-[11px] font-bold tabular-nums tracking-[0.18em] uppercase text-ink border-r border-rule bg-ivory-alt">
                {{ $paginator->currentPage() }} <span class="text-ink-muted">/ {{ $paginator->lastPage() }}</span>
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   class="flex-1 flex items-center justify-center gap-2 px-4 py-3
                          font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-ink
                          hover:bg-ink hover:text-ivory transition-colors">
                    {!! __('pagination.next') !!}
                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                </a>
            @else
                <span class="flex-1 flex items-center justify-center gap-2 px-4 py-3
                             font-mono text-[11px] font-bold uppercase tracking-[0.2em] text-ink-muted/50 bg-ivory-alt cursor-not-allowed">
                    {!! __('pagination.next') !!}
                    <x-heroicon-s-arrow-long-right class="w-4 h-4" />
                </span>
            @endif
        </div>

        {{-- ─── Desktop ─── --}}
        <div class="hidden sm:flex items-stretch justify-between">

            {{-- Range readout --}}
            <div class="flex items-center gap-4 px-5 border-r border-rule bg-ivory-alt min-w-[260px]">
                <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber-ink">§ Range</span>
                <p class="font-mono text-[11px] tabular-nums tracking-[0.08em] uppercase text-ink-muted">
                    @if ($paginator->firstItem())
                        <span class="text-ink font-bold">{{ str_pad($paginator->firstItem(), 3, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-ink-muted/60">—</span>
                        <span class="text-ink font-bold">{{ str_pad($paginator->lastItem(), 3, '0', STR_PAD_LEFT) }}</span>
                        <span class="text-ink-muted/60 mx-1">/</span>
                        <span class="text-ink font-bold">{{ $paginator->total() }}</span>
                    @else
                        <span class="text-ink font-bold">{{ $paginator->count() }}</span>
                    @endif
                </p>
            </div>

            {{-- Page tiles --}}
            <div class="flex items-stretch">

                {{-- Previous --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
                          class="inline-flex items-center justify-center w-12 border-l border-rule
                                 font-mono text-[11px] text-ink-muted/40 bg-ivory-alt cursor-not-allowed">
                        <x-heroicon-s-chevron-left class="w-4 h-4" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="inline-flex items-center justify-center w-12 border-l border-rule
                              font-mono text-[11px] text-ink bg-paper
                              hover:bg-ink hover:text-ivory transition-colors"
                       aria-label="{{ __('pagination.previous') }}">
                        <x-heroicon-s-chevron-left class="w-4 h-4" />
                    </a>
                @endif

                {{-- Page number elements --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true"
                              class="inline-flex items-center justify-center w-10 border-l border-rule
                                     font-mono text-[11px] font-bold tabular-nums text-ink-muted/60 bg-paper cursor-default">
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @php
                                $pageLabel = str_pad((string) $page, 2, '0', STR_PAD_LEFT);
                                $isCurrent = $page == $paginator->currentPage();
                            @endphp
                            @if ($isCurrent)
                                <span aria-current="page"
                                      class="relative inline-flex items-center justify-center w-12 border-l border-ink
                                             font-mono text-[13px] font-bold tabular-nums text-ivory bg-ink cursor-default">
                                    {{ $pageLabel }}
                                    <span class="absolute -top-[2px] left-1/2 -translate-x-1/2 w-6 h-[2px] bg-amber" aria-hidden="true"></span>
                                </span>
                            @else
                                <a href="{{ $url }}"
                                   class="inline-flex items-center justify-center w-12 border-l border-rule
                                          font-mono text-[13px] font-bold tabular-nums text-ink bg-paper
                                          hover:bg-ink hover:text-ivory transition-colors"
                                   aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $pageLabel }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="inline-flex items-center justify-center w-12 border-l border-rule
                              font-mono text-[11px] text-ink bg-paper
                              hover:bg-ink hover:text-ivory transition-colors"
                       aria-label="{{ __('pagination.next') }}">
                        <x-heroicon-s-chevron-right class="w-4 h-4" />
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                          class="inline-flex items-center justify-center w-12 border-l border-rule
                                 font-mono text-[11px] text-ink-muted/40 bg-ivory-alt cursor-not-allowed">
                        <x-heroicon-s-chevron-right class="w-4 h-4" />
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
