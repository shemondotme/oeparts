@if ($paginator->hasPages())
    {{-- ══════════════════════════════════════════════════════════════
         INDUSTRIAL BLUEPRINT — Pagination Ledger
         ══════════════════════════════════════════════════════════════ --}}
    <nav role="navigation" aria-label="{{ trans('pagination.navigation') }}"
         class="border border-ink bg-paper overflow-hidden">

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
        <div class="hidden sm:flex flex-wrap items-stretch justify-between">

            {{-- Range readout --}}
            <div class="flex grow items-center gap-4 px-5 border-r border-rule bg-ivory-alt min-w-[260px] h-11">
                <span class="font-mono text-[10px] font-bold tracking-[0.26em] uppercase text-amber-ink">{{ trans('pagination.range') }}</span>
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
            <div class="flex items-stretch h-11 grow max-w-full">

                {{-- Previous --}}
                @if ($paginator->onFirstPage())
                    {{-- role="link": a plain <span> has role=generic, which doesn't
                         support naming — aria-label on it is an ARIA-prohibited-
                         attribute violation (confirmed via axe-core). --}}
                    <span role="link" aria-disabled="true" aria-label="{{ __('pagination.previous') }}"
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

                {{-- Page number elements: always first, last and the current page ±2, one "…" for each gap.
                     Laravel hands over up to 13 tiles; that no longer fits the column on a laptop. --}}
                @php
                    $pages = collect($elements)->filter(fn ($e) => is_array($e))->reduce(fn ($c, $e) => $c + $e, []);
                    $current = $paginator->currentPage();
                    $last = $paginator->lastPage();
                    $shown = [];
                    $prev = 0;
                    foreach ($pages as $page => $url) {
                        if ($page == 1 || $page == $last || abs($page - $current) <= 2) {
                            if ($prev && $page - $prev > 1) { $shown[] = ['gap' => true]; }
                            $shown[] = ['page' => $page, 'url' => $url];
                            $prev = $page;
                        }
                    }
                @endphp
                @foreach ($shown as $item)
                    @if (isset($item['gap']))
                        <span aria-hidden="true"
                              class="inline-flex items-center justify-center w-9 grow border-l border-rule
                                     font-mono text-[11px] font-bold text-ink-muted/60 bg-paper cursor-default">...</span>
                    @else
                        @php
                            $page = $item['page'];
                            $pageLabel = str_pad((string) $page, 2, '0', STR_PAD_LEFT);
                            $isCurrent = $page == $current;
                        @endphp
                        @if ($isCurrent)
                            <span aria-current="page"
                                  class="relative inline-flex items-center justify-center w-12 grow border-l border-ink
                                         font-mono text-[13px] font-bold tabular-nums text-ivory bg-ink cursor-default">
                                {{ $pageLabel }}
                                <span class="absolute -top-[2px] left-1/2 -translate-x-1/2 w-6 h-[2px] bg-amber" aria-hidden="true"></span>
                            </span>
                        @else
                            <a href="{{ $item['url'] }}"
                               class="inline-flex items-center justify-center w-12 grow border-l border-rule
                                      font-mono text-[13px] font-bold tabular-nums text-ink bg-paper
                                      hover:bg-ink hover:text-ivory transition-colors"
                               aria-label="{{ trans('pagination.go_to_page', ['page' => $page]) }}">
                                {{ $pageLabel }}
                            </a>
                        @endif
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
                    <span role="link" aria-disabled="true" aria-label="{{ __('pagination.next') }}"
                          class="inline-flex items-center justify-center w-12 border-l border-rule
                                 font-mono text-[11px] text-ink-muted/40 bg-ivory-alt cursor-not-allowed">
                        <x-heroicon-s-chevron-right class="w-4 h-4" />
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
