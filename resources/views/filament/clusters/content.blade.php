@include('filament.components.admin-styles')
<x-filament-panels::page>
    <div class="space-y-6" x-data="{ search: '' }">
        <div class="op-card relative overflow-hidden p-6 page-header-gradient page-header-border">
            <div class="absolute inset-0" style="background: radial-gradient(circle at 30% 30%, rgba(16, 185, 129, 0.08), transparent 50%);"></div>
            <div class="relative flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold tracking-tight flex items-center gap-2" style="font-family: var(--font-display); color: var(--color-text-on-accent);">
                        <x-heroicon-o-document-text class="w-5 h-5" style="color: var(--color-success-500);" />
                        Content Management
                    </h2>
                    <p class="mt-1 text-sm max-w-2xl leading-relaxed" style="color: var(--color-text-muted);">
                        Manage pages, sections, menus, FAQs, blog posts, media, and content revisions. Control all storefront content from here.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs font-mono px-3 py-1.5 rounded-lg shrink-0 w-fit"
                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: var(--color-success-400);">
                    <span class="h-2 w-2 rounded-full animate-pulse" style="background: var(--color-success-500);"></span>
                    ACTIVE
                </div>
            </div>
        </div>

        @include('filament.components.cluster-search', ['placeholder' => 'Search content... (e.g. pages, blog, media, menu)'])

        @php
            $sections = [
                'Pages & Structure' => [
                    'icon' => 'heroicon-o-document-duplicate',
                    'keywords' => 'pages sections menus faq',
                    'items' => [
                        ['Pages', '/admin/pages', 'Static pages with CMS content blocks', 'heroicon-o-document'],
                        ['Sections', '/admin/sections', 'Reusable homepage content sections', 'heroicon-o-squares-2x2'],
                        ['Menus', '/admin/menus', 'Navigation menu structure and links', 'heroicon-o-bars-3'],
                        ['FAQs', '/admin/faqs', 'Frequently asked questions', 'heroicon-o-question-mark-circle'],
                    ]
                ],
                'Blog & Media' => [
                    'icon' => 'heroicon-o-pencil-square',
                    'keywords' => 'blog posts media files images',
                    'items' => [
                        ['Blog Posts', '/admin/blog-posts', 'Blog articles with rich content', 'heroicon-o-newspaper'],
                        ['Media Files', '/admin/media-files', 'Upload and manage site media assets', 'heroicon-o-photo'],
                    ]
                ],
                'Revisions' => [
                    'icon' => 'heroicon-o-clock',
                    'keywords' => 'revisions history audit',
                    'items' => [
                        ['Revision History', '/admin/content-revisions', 'Audit trail of all content changes with snapshots', 'heroicon-o-arrow-uturn-left'],
                    ]
                ],
            ];
        @endphp

        @foreach ($sections as $heading => $sectionData)
            <div
                x-show="search === '' || '{{ strtolower($heading) . ' ' . $sectionData['keywords'] }}'.includes(search.toLowerCase()) || {{ collect($sectionData['items'])->map(fn($i) => strtolower($i[0] . ' ' . $i[2]))->implode(' ') }}.includes(search.toLowerCase())"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="op-card overflow-hidden"
                style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);"
            >
                <div class="px-6 py-4 flex items-center gap-3" style="border-bottom: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);">
                    <div class="p-1.5 rounded-lg" style="background: var(--color-bg-surface); color: var(--color-text-muted);">
                        @svg($sectionData['icon'], 'w-4 h-4')
                    </div>
                    <h3 class="text-xs font-bold uppercase tracking-widest font-mono" style="color: var(--color-text-muted);">
                        {{ $heading }}
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach ($sectionData['items'] as $item)
                            <a href="{{ url($item[1]) }}"
                               x-show="search === '' || '{{ strtolower($item[0] . ' ' . $item[2]) }}'.includes(search.toLowerCase())"
                               class="settings-link op-focus-ring op-press flex items-start gap-4 p-4 rounded-xl transition-all duration-200 no-underline group"
                               style="border: 1px solid var(--color-border-subtle); background: var(--color-bg-inset);"
                            >
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-all duration-200 group-hover:scale-110"
                                    style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default);">
                                    @svg($item[3], 'w-5 h-5 transition-colors duration-200', ['style' => 'color: var(--color-success-600);'])
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-semibold transition-colors duration-200" style="color: var(--color-text-primary); font-family: var(--font-display);">
                                        {{ $item[0] }}
                                    </div>
                                    <div class="mt-1 text-xs leading-normal" style="color: var(--color-text-muted);">
                                        {{ $item[2] }}
                                    </div>
                                </div>
                                <div class="flex items-center self-center opacity-0 group-hover:opacity-100 translate-x-[-4px] group-hover:translate-x-0 transition-all duration-300">
                                    <x-heroicon-o-arrow-right class="w-4 h-4" style="color: var(--color-accent-500);" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach

        @php
            $allItemKeywords = collect($sections)->flatMap(function ($section) {
                return collect($section['items'])->map(fn($item) => strtolower($item[0] . ' ' . $item[2]));
            })->values()->implode(' ');
            $sectionKeywords = collect($sections)->map(fn($s, $k) => strtolower($k . ' ' . $s['keywords']))->values()->implode(' ');
        @endphp
        <div x-show="search.length > 0 && !'{{ $sectionKeywords }}'.includes(search.toLowerCase()) && !'{{ $allItemKeywords }}'.includes(search.toLowerCase())"
            class="op-card p-8 text-center" style="background: var(--color-bg-surface); border: 1px solid var(--color-border-subtle);">
            <p class="text-sm font-medium" style="color: var(--color-text-muted);">No content matches your search.</p>
        </div>
    </div>
</x-filament-panels::page>
