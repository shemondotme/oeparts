<x-filament-widgets::widget class="fi-wi-health-strip">
    @php
        $items = [
            [
                'key'   => 'db',
                'label' => 'Database',
                'path'  => 'M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125',
            ],
            [
                'key'   => 'redis',
                'label' => 'Redis',
                'path'  => 'M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z',
            ],
            [
                'key'   => 'queue',
                'label' => 'Queue',
                'path'  => 'M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z',
            ],
            [
                'key'   => 'scheduler',
                'label' => 'Scheduler',
                'path'  => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
            ],
            [
                'key'   => 'cache',
                'label' => 'Cache',
                'path'  => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
            ],
            [
                'key'   => 'storage',
                'label' => 'Storage',
                'path'  => 'M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z',
            ],
        ];

        $colorVarMap = [
            'success' => 'var(--color-success-500)',
            'warning' => 'var(--color-warning-500)',
            'danger'  => 'var(--color-danger-500)',
            'gray'    => 'var(--color-text-secondary)',
        ];
    @endphp

    <div class="flex divide-x overflow-x-auto rounded-md border"
         style="min-height: 80px; border-color: var(--color-border-default); background: var(--color-bg-surface);"
         role="region"
         aria-label="System health indicators">
        @foreach ($items as $idx => $item)
            @php
                $check    = $checks[$item['key']] ?? ['label' => '—', 'detail' => '', 'color' => 'gray'];
                $colorVar = $colorVarMap[$check['color']] ?? 'var(--color-text-secondary)';
                $isPulsing = in_array($check['color'], ['warning', 'danger']);
                $delayMs  = $idx * 50;
            @endphp
            <div class="relative flex flex-col items-center justify-center flex-1 min-w-[110px] px-3 py-3 group cursor-default transition-colors duration-200 hover:bg-[var(--color-bg-inset)]"
                 style="border-left: 3px solid {{ $colorVar }}; opacity: 0; animation: fade-in-up 0.28s ease-out {{ $delayMs }}ms forwards;"
                 tabindex="0"
                 aria-label="{{ $item['label'] }}: {{ $check['label'] }}{{ $check['detail'] ? '. ' . $check['detail'] : '' }}">

                <svg class="w-5 h-5 mb-0.5 flex-shrink-0"
                     fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"
                     style="color: {{ $colorVar }};"
                     aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['path'] }}"/>
                </svg>

                <span class="text-[9px] font-bold uppercase tracking-widest leading-none"
                      style="color: var(--color-text-muted);">
                    {{ $item['label'] }}
                </span>

                <span class="flex items-center gap-1 text-xs font-semibold leading-tight mt-0.5"
                      style="color: var(--color-text-primary);">
                    {{ $check['label'] }}
                    @if ($isPulsing)
                        <span class="w-1.5 h-1.5 rounded-full op-badge-pulse flex-shrink-0"
                              style="background: {{ $colorVar }};"
                              aria-hidden="true"></span>
                    @endif
                </span>

                {{-- Detail tooltip on hover / focus --}}
                @if ($check['detail'])
                    <div class="absolute -bottom-9 left-1/2 -translate-x-1/2 z-50 hidden group-hover:block group-focus:block pointer-events-none whitespace-nowrap"
                         role="tooltip">
                        <div class="text-[10px] px-2 py-1 rounded-md shadow-lg"
                             style="background: var(--color-bg-canvas); border: 1px solid var(--color-border-default); color: var(--color-text-secondary);">
                            {{ $check['detail'] }}
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
