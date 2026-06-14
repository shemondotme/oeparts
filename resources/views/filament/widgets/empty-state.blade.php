<div class="op-empty">
    @if($icon ?? false)
        <div class="op-empty-illustration">
            @svg($icon, 'w-20 h-20')
        </div>
    @endif
    @if($heading ?? false)
        <h3 class="op-empty-title">{{ $heading }}</h3>
    @endif
    @if($description ?? false)
        <p class="op-empty-desc">{{ $description }}</p>
    @endif
    @if(($ctaLabel ?? false) && ($ctaUrl ?? false))
        <div class="op-empty-actions mt-4">
            <a href="{{ $ctaUrl }}" class="op-cta" wire:navigate>
                {{ $ctaLabel }}
            </a>
        </div>
    @endif
</div>
