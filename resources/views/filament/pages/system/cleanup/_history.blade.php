@use('App\Services\Cleanup\CleanupText', 'T')
@use('App\Services\Cleanup\TargetRegistry')
{{-- Cleanup History: every run, who/what started it, and how it ended. --}}
@php

    $runs = $this->history;
    $labels = collect(app(TargetRegistry::class)->all())->map->label();
@endphp
<div class="oc-card">
    <div class="oc-card-head"><div class="oc-h">Cleanup history</div></div>
    <p class="oc-sub">Every preview and cleanup, whether you started it or the schedule did. Kept for {{ $this->historyDays }} days (set below).</p>

    @if($runs->isEmpty())
        <p class="oc-muted">Nothing has been run yet.</p>
    @else
        <div style="overflow-x:auto;">
            <table class="oc-table">
                <thead>
                    <tr><th>Run</th><th>When</th><th>Started by</th><th>What</th><th>Result</th><th class="num">Removed</th><th class="num">Freed</th><th></th></tr>
                </thead>
                <tbody>
                @foreach($runs as $h)
                    @php $dry = $h->isDryRun(); @endphp
                    <tr wire:key="oc-hist-{{ $h->id }}">
                        <td class="oc-mono">#{{ $h->id }}</td>
                        <td title="{{ $h->started_at }}">{{ ($h->started_at ?? $h->created_at)->diffForHumans() }}</td>
                        <td>{{ $h->trigger === 'manual' ? ($h->admin?->name ?? 'An admin') : ($h->trigger === 'scheduled' ? 'Schedule' : 'Resume') }}</td>
                        <td>
                            {{ $h->target ? ($labels[$h->target] ?? $h->target) : $h->items()->count().' targets' }}
                            @if($dry)<span class="oc-pill info">preview</span>@endif
                        </td>
                        <td><span class="oc-pill {{ T::tone($h->status, $dry) }}">{{ T::status($h->status, $dry) }}</span></td>
                        <td class="num">{{ $dry ? '—' : T::number($h->deleted) }}</td>
                        <td class="num">{{ $dry ? '—' : T::bytes($h->bytes_freed) }}</td>
                        <td class="num"><button type="button" class="oc-btn" wire:click="showRun({{ $h->id }})">Details</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($this->historyTotal > $runs->count())
            <div style="margin-top:.75rem;"><button type="button" class="oc-btn" wire:click="loadMoreHistory">Show more ({{ $this->historyTotal - $runs->count() }} older)</button></div>
        @endif
    @endif
</div>
