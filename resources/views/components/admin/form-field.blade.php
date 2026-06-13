@props([
    'label',
    'name' => null,
    'error' => null,
    'helper' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <label @if($name) for="{{ $name }}" @endif class="block text-[11px] font-semibold uppercase tracking-[0.1em]" style="color: var(--color-text-muted);">
        {{ $label }}
    </label>

    @if($helper)
        <p @if($name) id="{{ $name }}-helper" @endif class="text-xs leading-relaxed" style="color: var(--color-text-muted);">{{ $helper }}</p>
    @endif

    {{ $slot }}

    @if($error)
        <p class="text-xs font-medium" style="color: var(--color-danger-600);" role="alert">{{ $error }}</p>
    @elseif($name)
        @error($name)
            <p class="text-xs font-medium" style="color: var(--color-danger-600);" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
