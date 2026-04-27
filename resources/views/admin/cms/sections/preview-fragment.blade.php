@if($content && is_array($content))
<div class="prose prose-sm max-w-none">
    @if(isset($content['headline']))
        <h2>{{ $content['headline'] }}</h2>
    @endif
    
    @if(isset($content['subheadline']))
        <p class="lead">{{ $content['subheadline'] }}</p>
    @endif
    
    @if(isset($content['description']))
        <div>{!! $content['description'] !!}</div>
    @endif
    
    @if(isset($content['button_text']) && isset($content['cta_url']))
        <a href="{{ $content['cta_url'] }}" class="btn btn-primary">
            {{ $content['button_text'] }}
        </a>
    @endif
</div>
@else
    <p class="text-gray-500 italic text-sm">No content available for preview</p>
@endif
