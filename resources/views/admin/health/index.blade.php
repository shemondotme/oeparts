@extends('layouts.admin')

@section('title', 'System Health')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">System Health</h1>
                    <p class="text-slate-600 mt-2">Comprehensive system diagnostics and monitoring.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" 
                            onclick="window.location.reload()"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                        <x-heroicon-o-arrow-path class="w-4 h-4 mr-2" />
                        Refresh
                    </button>
                    <form method="GET" action="{{ route('admin.health.export') }}">
                        <button type="submit" 
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4 mr-2" />
                            Export Report
                        </button>
                    </form>
                </div>
            </div>

            {{-- Overall Status --}}
            <div class="mt-6">
                @php
                    $statusColors = [
                        'healthy' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                        'warning' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'critical' => 'bg-red-100 text-red-800 border-red-200',
                        'info' => 'bg-blue-100 text-blue-800 border-blue-200',
                    ];
                    $statusIcons = [
                        'healthy' => 'heroicon-o-check-circle',
                        'warning' => 'heroicon-o-exclamation-triangle',
                        'critical' => 'heroicon-o-x-circle',
                        'info' => 'heroicon-o-information-circle',
                    ];
                @endphp
                <div class="inline-flex items-center px-4 py-2 rounded-lg border {{ $statusColors[$overallStatus] ?? 'bg-slate-100 text-slate-800 border-slate-200' }}">
                    @if(isset($statusIcons[$overallStatus]))
                        @php $iconClass = $statusIcons[$overallStatus]; @endphp
                        <x-dynamic-component :component="$iconClass" class="w-5 h-5 mr-2" />
                    @endif
                    <span class="font-medium">Overall Status: {{ ucfirst($overallStatus) }}</span>
                </div>
                <span class="ml-4 text-sm text-slate-600">Last checked: {{ now()->format('Y-m-d H:i:s') }}</span>
            </div>
        </div>

        {{-- Health Checks Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Left Column: System Information --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
                    <h3 class="font-semibold text-slate-900 mb-4">System Information</h3>
                    <div class="space-y-3">
                        @foreach($systemInfo as $key => $value)
                            <div class="flex justify-between items-center py-2 border-b border-slate-100 last:border-b-0">
                                <span class="text-sm text-slate-600 capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                <span class="text-sm font-medium text-slate-900 font-mono">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="font-semibold text-slate-900 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <button type="button" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            <x-heroicon-o-cog-6-tooth class="w-4 h-4 mr-2" />
                            Run All Checks
                        </button>
                        <button type="button" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            <x-heroicon-o-archive-box class="w-4 h-4 mr-2" />
                            Clear Cache
                        </button>
                        <button type="button" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            <x-heroicon-o-queue-list class="w-4 h-4 mr-2" />
                            Restart Queue
                        </button>
                        <button type="button" 
                                class="w-full inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 mr-2" />
                            Emergency Mode
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right Column: Health Checks --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200">
                        <h3 class="font-semibold text-slate-900">Health Checks</h3>
                        <p class="text-sm text-slate-600 mt-1">{{ count($healthChecks) }} checks performed</p>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        @foreach($healthChecks as $check)
                            @php
                                $statusConfig = [
                                    'healthy' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'icon' => 'heroicon-o-check-circle'],
                                    'warning' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'icon' => 'heroicon-o-exclamation-triangle'],
                                    'critical' => ['bg' => 'bg-red-50', 'text' => 'text-red-700', 'border' => 'border-red-200', 'icon' => 'heroicon-o-x-circle'],
                                    'info' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'icon' => 'heroicon-o-information-circle'],
                                ];
                                $config = $statusConfig[$check['status']] ?? $statusConfig['info'];
                                $iconClass = $config['icon'];
                            @endphp
                            
                            <div class="p-6 hover:bg-slate-50 transition-colors">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center mb-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $config['bg'] }} {{ $config['text'] }} {{ $config['border'] }} mr-3">
                                                <x-dynamic-component :component="$iconClass" class="w-3 h-3 mr-1" />
                                                {{ ucfirst($check['status']) }}
                                            </span>
                                            <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                                                {{ $check['category'] }}
                                            </span>
                                        </div>
                                        
                                        <h4 class="font-medium text-slate-900">{{ $check['name'] }}</h4>
                                        <p class="text-sm text-slate-600 mt-1">{{ $check['description'] }}</p>
                                        <p class="text-xs text-slate-500 mt-2">{{ $check['details'] }}</p>
                                    </div>
                                    
                                    <div class="ml-4">
                                        <form method="POST" action="{{ route('admin.health.run-check') }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="check" value="{{ $check['name'] }}">
                                            <button type="submit" 
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                                                <x-heroicon-o-play class="w-3 h-3 mr-1" />
                                                Run
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Status Legend --}}
                <div class="mt-6 bg-white rounded-xl border border-slate-200 p-6">
                    <h4 class="font-medium text-slate-900 mb-4">Status Legend</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-emerald-500 mr-2"></div>
                            <span class="text-sm text-slate-700">Healthy</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-amber-500 mr-2"></div>
                            <span class="text-sm text-slate-700">Warning</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-red-500 mr-2"></div>
                            <span class="text-sm text-slate-700">Critical</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full bg-blue-500 mr-2"></div>
                            <span class="text-sm text-slate-700">Info</span>
                        </div>
                    </div>
                    <div class="mt-4 text-sm text-slate-600">
                        <p><span class="font-medium">Healthy:</span> System is functioning normally.</p>
                        <p><span class="font-medium">Warning:</span> Potential issue that needs attention.</p>
                        <p><span class="font-medium">Critical:</span> Immediate action required.</p>
                        <p><span class="font-medium">Info:</span> Informational status, no action needed.</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Monitoring Tools --}}
        <div class="mt-8 bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-900 mb-6">Monitoring Tools</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center mr-3">
                            <x-heroicon-o-chart-bar class="w-5 h-5 text-emerald-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-slate-900">Performance</h4>
                            <p class="text-sm text-slate-600">Response time & throughput</p>
                        </div>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500" style="width: 85%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-2">85% optimal</div>
                </div>
                
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mr-3">
                            <x-heroicon-o-server-stack class="w-5 h-5 text-blue-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-slate-900">Resources</h4>
                            <p class="text-sm text-slate-600">CPU, Memory, Disk</p>
                        </div>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500" style="width: 65%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-2">65% utilization</div>
                </div>
                
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center mb-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center mr-3">
                            <x-heroicon-o-shield-check class="w-5 h-5 text-amber-600" />
                        </div>
                        <div>
                            <h4 class="font-medium text-slate-900">Security</h4>
                            <p class="text-sm text-slate-600">Vulnerabilities & threats</p>
                        </div>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500" style="width: 92%"></div>
                    </div>
                    <div class="text-xs text-slate-500 mt-2">92% secure</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auto-refresh script --}}
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh every 5 minutes
    setInterval(function() {
        const refreshButton = document.querySelector('button[onclick*="window.location.reload"]');
        if (refreshButton) {
            // Show notification instead of auto-refresh
            console.log('Health check would refresh now');
        }
    }, 5 * 60 * 1000);
    
    // Status color animation
    const statusElements = document.querySelectorAll('[class*="bg-"]');
    statusElements.forEach(el => {
        if (el.classList.contains('bg-red-50') || el.classList.contains('bg-amber-50')) {
            el.classList.add('pulse-warning');
        }
    });
});
</script>
<style>
@keyframes pulse-warning {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}
.pulse-warning {
    animation: pulse-warning 2s infinite;
}
</style>
@endpush
@endsection