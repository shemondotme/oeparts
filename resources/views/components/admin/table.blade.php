<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="bp-table">
        {{ $slot }}
    </table>
</div>
