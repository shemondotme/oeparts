{{--
    <x-admin.section-card title="..." description="..." [actions slot]>
        Content goes here
    </x-admin.section-card>

    Props:
      title       — (required) card heading
      description — (optional) subtitle below the heading
      noPadding   — (optional, bool) omit inner body padding (e.g. for tables that bleed edge-to-edge)
      interactive — (optional, bool) add lift-on-hover via bp-card--interactive
--}}
@props([
    'title',
    'description' => null,
    'noPadding'   => false,
    'interactive' => false,
])

<div {{ $attributes->merge(['class' => 'bp-card overflow-hidden' . ($interactive ? ' bp-card--interactive' : '')]) }}>
    {{-- Header --}}
    @if($title || isset($actions))
        <div class="bp-card-header flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="truncate font-display text-[0.9375rem] font-semibold tracking-tight" style="color: var(--color-text-primary);">
                    {{ $title }}
                </h2>
                @if($description)
                    <p class="mt-0.5 text-xs" style="color: var(--color-text-muted);">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{-- Body --}}
    <div @class(['p-5' => !$noPadding])>
        {{ $slot }}
    </div>
</div>
