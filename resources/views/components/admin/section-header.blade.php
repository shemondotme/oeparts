<div {{ $attributes->merge(['class' => 'mb-6']) }}>
    <h2 class="text-lg font-semibold text-gray-900 mb-2 border-l-4 border-amber pl-3">
        {{ $title }}
    </h2>
    @if(isset($description))
        <p class="text-sm text-gray-500">{{ $description }}</p>
    @endif
</div>