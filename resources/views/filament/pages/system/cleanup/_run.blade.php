@use('App\Models\CleanupRun')
@use('App\Services\Cleanup\CleanupProgress')
@use('App\Services\Cleanup\CleanupText', 'T')
{{-- The run panel: live progress while a cleanup/preview runs, the verified result afterwards. --}}
@php

    $run = $this->run;
@endphp

@if($run)
    @php
        $progress = CleanupProgress::fromRun($run);
        $dry = $run->isDryRun();
        $terminal = $run->isTerminal();
        $items = $this->itemsOf($run);
        $tone = T::tone($run->status, $dry);
        $paused = $run->status === CleanupRun::STATUS_PAUSED;
        $previewTarget = ($dry && $terminal && $run->status === CleanupRun::STATUS_SUCCESS && $run->kind === CleanupRun::KIND_SINGLE && $run->targeted > 0) ? $run->target : null;
        $canDeletePreview = $previewTarget !== null && isset($this->targets[$previewTarget]) && ! $this->targets[$previewTarget]['report_only'];
        $hasLeftovers = $terminal && ! $dry && in_array($run->status, [CleanupRun::STATUS_PARTIAL, CleanupRun::STATUS_FAILED, CleanupRun::STATUS_CANCELLED], true) && $run->items->contains(fn ($i) => $i->status !== 'success');
    @endphp

    <div class="oc-card" @if($this->running) wire:poll.2s="pollRun" @endif wire:key="oc-run-{{ $run->id }}">
        <div class="oc-card-head">
            <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
                <span class="oc-h">{{ $dry ? 'Preview' : 'Cleanup' }} #{{ $run->id }}</span>
                <span class="oc-pill {{ $tone }}">{{ $terminal ? T::status($run->status, $dry) : ($paused ? 'Waiting' : 'Running') }}</span>
                @if($run->trigger !== 'manual')<span class="oc-pill muted">{{ $run->trigger === 'scheduled' ? 'automatic' : 'resumed' }}</span>@endif
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                @if(! $terminal)
                    @if($run->isCancelRequested())
                        <span class="oc-muted">Stopping after the current item…</span>
                    @else
                        <button type="button" class="oc-btn" wire:click="cancelRun" wire:confirm="Stop this {{ $dry ? 'preview' : 'cleanup' }}? What was already removed stays removed.">
                            <x-heroicon-o-stop style="width:.9rem;height:.9rem;" /> Cancel
                        </button>
                    @endif
                @else
                    <button type="button" class="oc-btn" wire:click="dismissRun"><x-heroicon-o-x-mark style="width:.9rem;height:.9rem;" /> Dismiss</button>
                @endif
            </div>
        </div>

        @if(! $terminal)
            {{-- ── in progress ── --}}
            <div style="display:flex;justify-content:space-between;align-items:baseline;gap:1rem;margin-bottom:.4rem;">
                <span class="oc-strong" style="font-size:.9rem;">{{ $run->message ?: 'Working…' }}</span>
                <span class="oc-mono oc-muted">{{ $progress->percent }}%</span>
            </div>
            <div class="oc-bar {{ $paused ? 'warn' : '' }}"><i style="width: {{ max(2, $progress->percent) }}%"></i></div>
            <div class="oc-nums" style="margin-top:.6rem;">
                <span>Step <b>{{ ucfirst((string) $run->step) }}</b></span>
                <span>Found <b>{{ T::number($run->targeted) }}</b></span>
                <span>Removed <b>{{ T::number($run->deleted) }}</b></span>
                <span>Elapsed <b>{{ T::duration($progress->elapsedSeconds) }}</b></span>
                @if($progress->etaSeconds !== null)<span>About <b>{{ T::duration($progress->etaSeconds) }}</b> left</span>@endif
            </div>
            @if($paused)
                <div class="oc-note warn" style="margin-top:.75rem;">{{ $run->message }} It continues by itself.</div>
            @endif
            <p class="oc-muted" style="margin-top:.6rem;">Safe to leave this page open, or to close it: an unattended run is picked up again within minutes. It can never run at the same time as a backup, update or another cleanup.</p>
        @else
            {{-- ── finished: the verified result ── --}}
            <div class="oc-note {{ $dry ? 'info' : ($run->status === 'success' ? 'ok' : ($run->status === 'failed' ? 'down' : 'warn')) }}">
                <strong>{{ $run->message }}</strong>
                @if(! $dry && $run->bytes_freed !== null)
                    <span> Freed about {{ T::bytes($run->bytes_freed) }}.</span>
                @endif
                @if($dry && $items->sum(fn ($r) => (int) $r['item']->bytes_freed_apparent) > 0)
                    <span> Would free about {{ T::bytes((int) $items->sum(fn ($r) => (int) $r['item']->bytes_freed_apparent)) }}.</span>
                @endif
            </div>

            <div style="overflow-x:auto;margin-top:.9rem;">
                <table class="oc-table">
                    <thead>
                        <tr>
                            <th>What</th><th>Result</th>
                            <th class="num">Found</th>
                            <th class="num">{{ $dry ? 'Would remove' : 'Removed' }}</th>
                            @unless($dry)<th class="num" title="Removed by something else while this ran">Gone elsewhere</th><th class="num">Left</th><th class="num">Freed</th>@endunless
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $r)
                        @php $item = $r['item']; @endphp
                        <tr>
                            <td class="oc-strong">{{ $r['label'] }}
                                @foreach($r['reasons'] as $reason)<div class="oc-muted" style="font-weight:400;">{{ $reason }}</div>@endforeach
                            </td>
                            <td><span class="oc-pill {{ $dry && $item->status === 'success' ? 'info' : $r['tone'] }}">{{ $dry && $item->status === 'success' ? 'Counted' : $r['status'] }}</span></td>
                            <td class="num">{{ T::number($item->targeted) }}</td>
                            <td class="num">{{ $dry ? T::number($item->targeted) : T::number($item->deleted) }}</td>
                            @unless($dry)
                                <td class="num">{{ T::number($item->external_gone) }}</td>
                                <td class="num">{{ T::number($item->remaining) }}</td>
                                <td class="num" title="{{ $item->fs_free_before !== null && $item->fs_free_after !== null ? 'Disk free before/after: '.T::bytes($item->fs_free_before).' → '.T::bytes($item->fs_free_after) : '' }}">
                                    {{ T::bytes($item->bytes_freed_allocated ?? $item->bytes_freed_apparent) }}
                                </td>
                            @endunless
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            @unless($dry)
                <div class="oc-eq" style="margin-top:.75rem;">
                    Found {{ T::number($run->targeted) }} = removed {{ T::number($run->deleted) }}
                    + gone elsewhere {{ T::number((int) $run->items->sum('external_gone')) }}
                    + left {{ T::number($run->remaining) }}
                    @if((int) $run->items->whereIn('status', ['skipped_guard', 'skipped_floor', 'blocked_busy'])->sum('targeted') > 0)
                        (left includes what was skipped)
                    @endif
                </div>
                <p class="oc-muted" style="margin-top:.5rem;">
                    Checked after deleting: the same rule was counted again, independently of the delete.
                    @if($items->contains(fn ($r) => $r['item']->bytes_freed_apparent !== null && $r['item']->bytes_freed_allocated === null && $this->targets[$r['item']->target]['kind'] === 'db'))
                        Space freed inside the database is reusable by the database straight away; the file on disk only shrinks after the table is optimized.
                    @endif
                </p>
            @endunless

            <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.9rem;">
                @if($canDeletePreview)
                    <button type="button" class="oc-btn oc-btn-danger" wire:click="mountAction('clean', @js(['target' => $previewTarget, 'preview' => $run->id]))">
                        <x-heroicon-o-trash style="width:.9rem;height:.9rem;" /> Delete these {{ T::number($run->targeted) }} now
                    </button>
                @endif
                @if($hasLeftovers)
                    <button type="button" class="oc-btn oc-btn-primary" wire:click="resumeRun"><x-heroicon-o-play style="width:.9rem;height:.9rem;" /> Resume what is left</button>
                @endif
            </div>
        @endif
    </div>
@endif
