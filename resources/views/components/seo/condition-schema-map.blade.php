@php
    $map = \App\Services\SeoService::conditionSchemaMap();
@endphp
<div class="fi-in-text text-sm space-y-1">
    @foreach($map as $slug => $schemaUrl)
        <div class="flex items-center gap-2">
            <code class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs">{{ $slug }}</code>
            <span>&rarr;</span>
            <code class="rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 text-xs">{{ $schemaUrl }}</code>
        </div>
    @endforeach
</div>
