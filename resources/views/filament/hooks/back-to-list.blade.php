@if($url)
    <x-filament::button
        tag="a"
        :href="$url"
        color="gray"
        outlined
        icon="heroicon-o-arrow-left"
    >
        Back to list
    </x-filament::button>
@endif
