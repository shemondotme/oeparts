<x-filament-widgets::widget class="fi-wi-quick-actions op-fade-in">
    <div class="p-5 h-full flex flex-col" style="background: var(--glass-bg); backdrop-filter: var(--glass-blur); border: 1px solid var(--glass-border); border-top: 2px solid var(--aurora-indigo); border-radius: 20px; box-shadow: var(--glass-shadow);">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[10px] font-bold uppercase tracking-widest" style="color: var(--color-text-muted); font-family: var(--font-mono);">
                Quick Actions
            </h2>
        </div>
        
        <div class="space-y-1.5 flex-1">
            @foreach($actions as $index => $action)
                <a href="{{ $action['url'] }}" wire:navigate
                    class="op-focus-ring op-press flex items-center justify-between px-3 py-2.5 rounded-lg transition-all duration-200 group"
                    style="border: 1px solid transparent;"
                    onmouseover="this.style.borderColor='var(--glass-border)'; this.style.background='rgba(99,102,241,0.06)';"
                    onmouseout="this.style.borderColor='transparent'; this.style.background='transparent';"
                >
                    <div class="flex items-center gap-3">
                        @php
                        $colorStyles = [
                            'warning' => 'background: rgba(245,158,11,0.12); color: var(--aurora-violet);',
                            'info'    => 'background: rgba(99,102,241,0.12); color: var(--aurora-indigo);',
                            'success' => 'background: rgba(34,211,238,0.12); color: var(--aurora-cyan);',
                            'gray'    => 'background: rgba(99,102,241,0.08); color: var(--aurora-blue);',
                            'danger'  => 'background: rgba(244,63,94,0.12); color: var(--aurora-rose);',
                        ];
                    @endphp
                    <div class="p-1.5 rounded-lg flex items-center justify-center" style="{{ $colorStyles[$action['color']] ?? '' }}">
                            @svg($action['icon'], 'w-4 h-4')
                        </div>
                        <span class="text-sm font-medium transition-colors" style="color: var(--color-text-primary);">
                            {{ $action['label'] }}
                        </span>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <kbd class="px-1.5 py-0.5 text-[9px] font-mono font-bold rounded"
                            style="background: var(--color-bg-surface); border: 1px solid var(--color-border-default); color: var(--color-text-muted);">
                            @if($index === 0) &#8984;N
                            @elseif($index === 1) &#8984;O
                            @elseif($index === 2) &#8984;&#8679;R
                            @elseif($index === 3) &#8984;,
                            @else &#8984;&#8679;S
                            @endif
                        </kbd>
                        <div class="flex items-center justify-center transition-all transform group-hover:translate-x-0.5" style="color: var(--color-text-muted);">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
