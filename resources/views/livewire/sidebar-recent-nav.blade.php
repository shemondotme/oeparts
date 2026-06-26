<div class="op-sidebar-recent" role="navigation" aria-label="Recently viewed pages">
    <div class="op-sidebar-recent-header">
        <span class="op-sidebar-recent-title">Recent</span>
    </div>

    @if (count($recent) > 0)
        <div class="op-sidebar-recent-list">
            @foreach ($recent as $item)
                <div class="op-sidebar-recent-item">
                    <a href="{{ $item['url'] }}" wire:navigate class="op-sidebar-recent-link">
                        {{ \Filament\Support\generate_icon_html($item['icon'], attributes: (new \Illuminate\View\ComponentAttributeBag)->class(['op-sidebar-recent-icon'])) }}
                        <span>{{ $item['label'] }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <p class="op-sidebar-recent-empty">Pages you visit will appear here.</p>
    @endif
</div>
