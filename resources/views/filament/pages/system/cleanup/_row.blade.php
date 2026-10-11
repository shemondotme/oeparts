@use('App\Services\Cleanup\CleanupText', 'T')
{{-- One cleanup target. Expects: $row (see CleanupOverview::targets()). --}}
@php
    $isDb = $row['kind'] === 'db';
    $locked = $this->running;
    $key = $row['key'];
@endphp
<div class="oc-row" wire:key="oc-row-{{ $key }}">
    <div>
        <div class="oc-row-title">
            {{ $row['label'] }}
            @if($row['report_only'])<span class="oc-pill info">report only</span>@endif
            @if($row['needs_lock'])<span class="oc-pill muted" title="Takes the shared backup/update lock while it runs">needs idle system</span>@endif
        </div>
        <div class="oc-muted">{{ $row['description'] }}</div>

        <div class="oc-nums">
            @if($isDb)
                <span>Rows <b>~{{ T::number($row['rows']) }}</b></span>
                <span>Size <b>{{ T::bytes($row['bytes']) }}</b></span>
                <span>Oldest <b>{{ $row['oldest'] ? $row['oldest']->format('Y-m-d') : '—' }}</b></span>
            @endif
            <span>
                Eligible now
                @if($row['eligible_rows'] === null)
                    <b>not measured</b>
                @else
                    <b>~{{ T::number($row['eligible_rows']) }}</b>
                    @if($row['eligible_bytes'])<b>(~{{ T::bytes($row['eligible_bytes']) }})</b>@endif
                @endif
            </span>
            <span>
                Last cleaned
                @if($row['last_at'])
                    <b>{{ $row['last_at']->diffForHumans() }}</b>
                    — {{ T::number($row['last_deleted']) }} removed
                    @if($row['last_bytes'])
                        , {{ T::bytes($row['last_bytes']) }} freed
                    @endif
                    <span class="oc-pill {{ T::tone($row['last_status']) }}">{{ T::status($row['last_status']) }}</span>
                @else
                    <b>never from here</b>
                @endif
            </span>
            @if($row['measured_at'])
                <span title="Sizes are estimates, refreshed daily or with the refresh button">measured {{ $row['measured_at']->diffForHumans() }}</span>
            @endif
        </div>
    </div>

    <div class="oc-ctrl">
        @if($row['age_based'])
            <label class="oc-muted" style="display:flex;align-items:center;gap:.4rem;">
                Keep
                <input type="number" class="oc-in" min="{{ max(1, $row['floor_days']) }}" max="3650"
                    value="{{ $this->retentionInputs[$key] ?? $row['days'] }}"
                    wire:change="saveRetention('{{ $key }}', $event.target.value)"
                    @disabled($row['report_only'])>
                days
            </label>
            @if($row['floor_days'] > 0)
                <span class="oc-muted" style="font-size:.7rem;">minimum {{ $row['floor_days'] }} days</span>
            @endif
        @else
            <span class="oc-muted">Not age-based</span>
        @endif

        @unless($row['report_only'])
            <label class="oc-muted" style="display:flex;align-items:center;gap:.5rem;cursor:pointer;">
                <button type="button" class="oc-switch {{ $row['auto'] ? 'on' : '' }}" wire:click="toggleAuto('{{ $key }}')"
                    role="switch" aria-checked="{{ $row['auto'] ? 'true' : 'false' }}" aria-label="Automatic cleaning for {{ $row['label'] }}"></button>
                Automatic
            </label>
        @endunless
    </div>

    <div class="oc-actions">
        <button type="button" class="oc-btn" wire:click="startPreview('{{ $key }}')" @disabled($locked)>
            <x-heroicon-o-eye style="width:.9rem;height:.9rem;" /> Preview
        </button>
        @unless($row['report_only'])
            <button type="button" class="oc-btn oc-btn-danger" wire:click="mountAction('clean', @js(['target' => $key]))" @disabled($locked)>
                <x-heroicon-o-trash style="width:.9rem;height:.9rem;" /> Clean now
            </button>
            @if($row['truncatable'])
                <button type="button" class="oc-btn oc-btn-warn" wire:click="mountAction('deleteAll', @js(['target' => $key]))" @disabled($locked)
                    title="Delete every row that exists now, not only the old ones">
                    Delete all
                </button>
            @endif
        @endunless
        <button type="button" class="oc-btn" wire:click="refreshStats('{{ $key }}')" wire:loading.attr="disabled" wire:target="refreshStats('{{ $key }}')" title="Re-measure this one">
            <x-heroicon-o-arrow-path style="width:.9rem;height:.9rem;" />
        </button>
    </div>
</div>
