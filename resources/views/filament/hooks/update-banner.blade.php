@php
    $status = app(\App\Services\Updates\UpdateChecker::class)->cached();
    $user = auth('admin')->user();
    $show = $status && $status->updateAvailable && $user && $user->can('view updates');
@endphp

@if($show)
    @php
        $security = $status->security;
        $accent = $security ? 'var(--danger-500, #dc2626)' : 'var(--warning-500, #f59e0b)';
        $bg = $security ? 'var(--danger-50, #fef2f2)' : 'var(--warning-50, #fffbeb)';
        $url = \App\Filament\Pages\System\SystemUpdates::getUrl();
    @endphp
    <div class="mb-4 rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
         style="background: {{ $bg }}; border: 1px solid {{ $accent }};">
        <div class="flex items-center gap-3 text-sm" style="color: var(--color-text-primary, #111827);">
            <x-heroicon-o-arrow-up-circle class="w-5 h-5 shrink-0" style="color: {{ $accent }};" />
            <span>
                <strong>{{ $security ? 'Security update' : 'Update' }} available</strong>
                — OeParts {{ $status->latestVersion }} (installed {{ $status->currentVersion }}).
            </span>
        </div>
        <a href="{{ $url }}"
           class="op-focus-ring op-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider whitespace-nowrap self-start sm:self-auto"
           style="background: {{ $accent }}; color: white;">
            <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
            View details
        </a>
    </div>
@endif
