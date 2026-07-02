@props([
    'text' => '',
    'label' => null,
])

<span
    x-data="{ copied: false }"
    @click="
        navigator.clipboard.writeText('{{ $text }}').then(() => {
            copied = true;
            setTimeout(() => copied = false, 1500);
        });
    "
    class="inline-flex items-center gap-1.5 cursor-pointer group"
>
    @if($label)
        <span class="font-mono text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $label }}</span>
    @else
        <span class="font-mono text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $text }}</span>
    @endif

    <span x-show="!copied" class="opacity-0 group-hover:opacity-100 transition-opacity duration-150">
        <x-heroicon-o-clipboard-document class="w-3.5 h-3.5 text-zinc-400 dark:text-zinc-500" />
    </span>

    <span x-show="copied" x-cloak>
        <x-heroicon-o-check class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" />
    </span>
</span>
