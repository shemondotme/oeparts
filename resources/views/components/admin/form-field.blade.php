@props([
    'label',
    'name' => null,
    'error' => null,
    'helper' => null,
])

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <label @if($name) for="{{ $name }}" @endif class="block text-[11px] font-semibold uppercase tracking-[0.1em] text-zinc-500 dark:text-zinc-400">
        {{ $label }}
    </label>

    @if($helper)
        <p @if($name) id="{{ $name }}-helper" @endif class="text-xs leading-relaxed text-zinc-500 dark:text-zinc-400">{{ $helper }}</p>
    @endif

    {{ $slot }}

    @if($error)
        <p class="text-xs font-medium text-rose-600 dark:text-rose-400" role="alert">{{ $error }}</p>
    @elseif($name)
        @error($name)
            <p class="text-xs font-medium text-rose-600 dark:text-rose-400" role="alert">{{ $message }}</p>
        @enderror
    @endif
</div>
