<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
        {{ $slot }}
    </table>
</div>
