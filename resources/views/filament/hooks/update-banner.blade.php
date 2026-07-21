@php
    $status = app(\App\Services\Updates\UpdateChecker::class)->cached();
    $user = auth('admin')->user();
    $show = $status && $status->updateAvailable && $user && $user->can('view updates');
@endphp

@if($show)
    @php
        $security = $status->security;
        $url = \App\Filament\Pages\System\SystemUpdates::getUrl();
    @endphp
    {{--
        Explicit Tailwind dark: variants, NOT inline style="" against raw
        Filament shade variables (--warning-50 / --danger-50 are fixed, always-
        light shades with no dark-mode counterpart — pairing them with a
        theme-aware text color like --color-text-primary silently turned the
        text near-white-on-near-white the moment dark mode was active).
        Matches the dark:-variant convention already used in
        audit-trail-detail.blade.php / backup-dashboard.blade.php.
    --}}
    <div @class([
        'mb-4 rounded-xl border px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3',
        'bg-red-50 border-red-300 dark:bg-red-950/20 dark:border-red-800' => $security,
        'bg-amber-50 border-amber-300 dark:bg-amber-950/20 dark:border-amber-800' => ! $security,
    ])>
        <div @class([
            'flex items-center gap-3 text-sm',
            'text-red-900 dark:text-red-100' => $security,
            'text-amber-900 dark:text-amber-100' => ! $security,
        ])>
            <x-heroicon-o-arrow-up-circle @class([
                'w-5 h-5 shrink-0',
                'text-red-600 dark:text-red-400' => $security,
                'text-amber-600 dark:text-amber-400' => ! $security,
            ]) />
            <span>
                <strong>{{ $security ? 'Security update' : 'Update' }} available</strong>
                — OeParts {{ $status->latestVersion }} (installed {{ $status->currentVersion }}).
            </span>
        </div>
        <a href="{{ $url }}"
           @class([
                'op-focus-ring op-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider whitespace-nowrap self-start sm:self-auto text-white',
                'bg-red-600 hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600' => $security,
                'bg-amber-600 hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600' => ! $security,
           ])>
            <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
            View details
        </a>
    </div>
@endif
