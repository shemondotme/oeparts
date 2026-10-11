@use('App\Services\Cleanup\CleanupText', 'T')
{{-- Disk overview: how full, where it went, how fast it grows, what a cleanup could give back. --}}
@php

    $d = $this->disk;
    $level = $d['level'];
    $barTone = match ($level) { 'fail' => 'down', 'warn' => 'warn', default => 'ok' };
    $breakdown = collect($d['breakdown'])->sortByDesc(fn ($v) => $v['bytes'] ?? 0);
    $reclaimable = (int) collect($this->targets)->sum(fn ($r) => (int) ($r['eligible_bytes'] ?? 0));
    $measured = collect($this->targets)->max('measured_at');
@endphp
<div class="oc-card">
    <div class="oc-card-head">
        <div class="oc-h">Disk &amp; overview</div>
        <div style="display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;">
            <span class="oc-muted">
                @if($measured) Numbers measured {{ $measured->diffForHumans() }} @else Numbers not measured yet @endif
            </span>
            <button type="button" class="oc-btn" wire:click="refreshStats" wire:loading.attr="disabled" wire:target="refreshStats">
                <x-heroicon-o-arrow-path style="width:.9rem;height:.9rem;" wire:loading.remove wire:target="refreshStats" />
                <span wire:loading wire:target="refreshStats">Measuring…</span>
                <span wire:loading.remove wire:target="refreshStats">Refresh numbers</span>
            </button>
            @unless($this->running)
                <button type="button" class="oc-btn oc-btn-danger" wire:click="mountAction('cleanAll')">Clean everything that is due</button>
            @endunless
        </div>
    </div>

    <div class="oc-grid">
        <div class="oc-tile">
            <div class="oc-tile-l">Disk used</div>
            <div class="oc-tile-v">{{ $d['used_pct'] !== null ? $d['used_pct'].'%' : '—' }}</div>
            <div class="oc-bar {{ $barTone }}" style="margin-top:.5rem;"><i style="width: {{ min(100, max(1, (float) ($d['used_pct'] ?? 0))) }}%"></i></div>
            <div class="oc-tile-m">
                {{ T::bytes($d['used']) }} of {{ T::bytes($d['total']) }}
                @if($level === 'warn') — above the {{ $d['warn_pct'] }}% warning @elseif($level === 'fail') — critically full @endif
            </div>
        </div>
        <div class="oc-tile">
            <div class="oc-tile-l">Free space</div>
            <div class="oc-tile-v">{{ T::bytes($d['free']) }}</div>
            <div class="oc-tile-m">on the drive that holds the application's data</div>
        </div>
        <div class="oc-tile">
            <div class="oc-tile-l">Could be cleaned</div>
            <div class="oc-tile-v">{{ $reclaimable > 0 ? '~'.T::bytes($reclaimable) : '—' }}</div>
            <div class="oc-tile-m">{{ $reclaimable > 0 ? 'across everything eligible now (estimate)' : 'refresh the numbers to find out' }}</div>
        </div>
        <div class="oc-tile">
            <div class="oc-tile-l">Growth</div>
            <div class="oc-tile-v">
                @if($d['growth_bytes'] !== null)
                    {{ $d['growth_bytes'] >= 0 ? '+' : '−' }}{{ T::bytes(abs($d['growth_bytes'])) }}
                @else — @endif
            </div>
            <div class="oc-tile-m">{{ $d['growth_days'] !== null ? 'over the last '.$d['growth_days'].' day(s)' : 'needs a day of history' }}</div>
        </div>
    </div>

    @if($breakdown->isNotEmpty())
        <div style="margin-top:1rem;">
            <div class="oc-tile-l" style="margin-bottom:.4rem;">Where it goes @if($d['sampled_at']) <span style="text-transform:none;letter-spacing:0;font-weight:400;">(measured {{ $d['sampled_at']->diffForHumans() }})</span> @endif</div>
            <div class="oc-nums" style="gap:.4rem 1.5rem;">
                @foreach($breakdown as $label => $part)
                    <span>{{ ucfirst($label) }} <b>{{ ($part['complete'] ?? true) ? '' : 'at least ' }}{{ T::bytes($part['bytes'] ?? 0) }}</b></span>
                @endforeach
            </div>
        </div>
    @endif
</div>
