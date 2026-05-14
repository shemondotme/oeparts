@props([
    'label',
    'name' => null,
    'error' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <label @if($name) for="{{ $name }}" @endif class="bp-spec block">
        {{ $label }}
    </label>

    {{ $slot }}

    @if($error)
        <p class="font-mono text-xs text-red-600">{{ $error }}</p>
    @endif
</div>
