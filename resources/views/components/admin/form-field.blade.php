@props([
    'label',
    'name' => null,
    'error' => null,
    'helper' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <label @if($name) for="{{ $name }}" @endif class="block text-[11px] font-semibold uppercase tracking-[0.1em] text-slate-500">
        {{ $label }}
    </label>

    @if($helper)
        <p @if($name) id="{{ $name }}-helper" @endif class="text-xs leading-relaxed text-slate-500">{{ $helper }}</p>
    @endif

    {{ $slot }}

    @if($error)
        <p class="text-xs font-medium text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
