@use('App\Services\Cleanup\CleanupText', 'T')
{{-- A card with one row per cleanup target. Expects: $title, $subtitle, $rows (from CleanupOverview::targets()). --}}
<div class="oc-card">
    <div class="oc-card-head">
        <div class="oc-h">{{ $title }}</div>
    </div>
    @if(! empty($subtitle))
        <p class="oc-sub">{{ $subtitle }}</p>
    @endif

    @forelse($rows as $row)
        @include('filament.pages.system.cleanup._row', ['row' => $row])
    @empty
        <p class="oc-muted">Nothing in this group.</p>
    @endforelse
</div>
