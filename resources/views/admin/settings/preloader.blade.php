@extends('layouts.admin')

@section('title', 'Preloader Settings')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-5xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Preloader Settings</h1>
                    <p class="text-slate-600 mt-2">Configure the full-screen preloader that displays while pages are loading.</p>
                </div>
                <a href="{{ route('admin.settings.index') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Back to Settings
                </a>
            </div>

            {{-- Breadcrumb --}}
            <nav class="flex items-center text-sm text-slate-500">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-slate-700">Dashboard</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <a href="{{ route('admin.settings.index') }}" class="hover:text-slate-700">Settings</a>
                <x-heroicon-o-chevron-right class="w-3 h-3 mx-2" />
                <span class="text-slate-900 font-medium">Preloader</span>
            </nav>
        </div>

        {{-- Error/Success Messages --}}
        @if($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <h3 class="text-sm font-medium text-red-800">There were errors:</h3>
                        <ul class="mt-2 text-sm text-red-700 space-y-1 list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                    <div class="text-sm text-emerald-700">{{ session('success') }}</div>
                </div>
            </div>
        @endif

        {{-- Main Form --}}
        <form method="POST" action="{{ route('admin.settings.preloader.update') }}" class="space-y-8">
            @csrf
            @method('PUT')

            {{-- 1. Enable/Disable Section --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h2 class="text-lg font-semibold text-slate-900">Enable Preloader</h2>
                            <p class="text-sm text-slate-600 mt-1">Turn the page preloader on or off globally. When disabled, the preloader will not display on any pages.</p>
                        </div>
                        <div class="ml-4">
                            <div class="flex items-center">
                                <input type="hidden" name="preloader_enabled" value="0">
                                <input type="checkbox" 
                                       id="preloader_enabled" 
                                       name="preloader_enabled" 
                                       value="1"
                                       {{ $enabled ? 'checked' : '' }}
                                       class="w-5 h-5 text-navy border-slate-300 rounded focus:ring-navy focus:ring-2 cursor-pointer">
                                <label for="preloader_enabled" class="ml-3 text-sm font-medium text-slate-700 cursor-pointer">
                                    Preloader is {{ $enabled ? 'Enabled' : 'Disabled' }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 2. Page Selection Section --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-slate-900">Select Pages</h2>
                        <p class="text-sm text-slate-600 mt-1">Choose which pages should display the preloader. Only selected pages will show the preloader when it's enabled.</p>
                    </div>

                    <div class="space-y-3">
                        @foreach($availablePages as $pageKey => $pageInfo)
                            <label class="relative flex items-start p-4 border border-slate-200 rounded-lg hover:bg-slate-50 cursor-pointer transition-colors">
                                <input type="checkbox" 
                                       name="preloader_pages[]" 
                                       value="{{ $pageKey }}"
                                       {{ collect($selectedPages)->contains(fn($p) => str_contains($p, str_replace('*', '', $pageInfo['path']))) ? 'checked' : '' }}
                                       class="w-4 h-4 text-navy border-slate-300 rounded focus:ring-navy focus:ring-2 mt-1">
                                <div class="ml-3 flex-1">
                                    <span class="block text-sm font-medium text-slate-900">{{ $pageInfo['label'] }}</span>
                                    <span class="block text-sm text-slate-600 mt-0.5">{{ $pageInfo['description'] }}</span>
                                    <span class="inline-block text-xs font-mono bg-slate-100 text-slate-700 px-2 py-1 rounded mt-2">{{ $pageInfo['path'] }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex gap-3">
                            <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-blue-700">
                                <strong>Tip:</strong> Select at least one page to enable the preloader. The preloader will only appear on selected pages.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 3. Display Timing Section --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-6">Display Timing</h2>

                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label for="preloader_min_display_ms" class="block text-sm font-medium text-slate-900">
                                Minimum Display Time (ms)
                            </label>
                            <p class="text-xs text-slate-500 mt-1">How long the preloader stays visible at minimum</p>
                            <input type="number" 
                                   id="preloader_min_display_ms" 
                                   name="preloader_min_display_ms" 
                                   value="{{ $minDisplayMs }}"
                                   min="0"
                                   max="5000"
                                   step="50"
                                   class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <p class="text-xs text-slate-600 mt-2">Recommended: 300-600ms</p>
                        </div>

                        <div>
                            <label for="preloader_max_display_ms" class="block text-sm font-medium text-slate-900">
                                Maximum Display Time (ms)
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Force-hide preloader after this duration (max timeout)</p>
                            <input type="number" 
                                   id="preloader_max_display_ms" 
                                   name="preloader_max_display_ms" 
                                   value="{{ $maxDisplayMs }}"
                                   min="500"
                                   max="600000"
                                   step="100"
                                   class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy sm:text-sm">
                            <p class="text-xs text-slate-600 mt-2">Recommended: 5000-10000ms</p>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <div class="flex gap-3">
                            <x-heroicon-o-light-bulb class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                            <div class="text-sm text-amber-700">
                                <strong>Pro tip:</strong> Set minimum time to match your page load speed. Maximum time acts as a safety net to prevent infinite loading screens.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Preloader Text Customization --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm">
                <div class="p-6">
                    <h2 class="text-lg font-semibold text-slate-900 mb-6">Preloader Text</h2>
                    <p class="text-sm text-slate-600 mb-6">Customize the text displayed on the preloader (supports all 5 languages as JSON).</p>

                    <div class="space-y-6">
                        <div>
                            <label for="preloader_headline" class="block text-sm font-medium text-slate-900">
                                Headline Text
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Main heading text (e.g., "OEM·HUB.")</p>
                            <textarea id="preloader_headline" 
                                      name="preloader_headline" 
                                      rows="3"
                                      class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm font-mono text-xs focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy"
                                      placeholder='{"en":"OEM·HUB.","de":"OEM·HUB.","fr":"OEM·HUB.","es":"OEM·HUB.","lt":"OEM·HUB."}'>@if(is_string($headlineText)){{ $headlineText }}@else{{ json_encode($headlineText, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}@endif</textarea>
                            <p class="mt-1 text-xs text-slate-500">Format: <code class="bg-slate-100 px-1 rounded">{"en":"text","de":"text",...}</code></p>
                        </div>

                        <div>
                            <label for="preloader_spec_line" class="block text-sm font-medium text-slate-900">
                                Spec Line Text
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Technical spec line (e.g., "§ SYS · INIT / EU")</p>
                            <textarea id="preloader_spec_line" 
                                      name="preloader_spec_line" 
                                      rows="3"
                                      class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm font-mono text-xs focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy"
                                      placeholder='{"en":"§ SYS · INIT / EU","de":"§ SYS · INIT / EU",...}'>@if(is_string($specLineText)){{ $specLineText }}@else{{ json_encode($specLineText, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}@endif</textarea>
                        </div>

                        <div>
                            <label for="preloader_subline" class="block text-sm font-medium text-slate-900">
                                Subline Text
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Subtitle text (e.g., "Genuine Parts Index")</p>
                            <textarea id="preloader_subline" 
                                      name="preloader_subline" 
                                      rows="3"
                                      class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm font-mono text-xs focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy"
                                      placeholder='{"en":"Genuine Parts Index","de":"Genuine Parts Index",...}'>@if(is_string($sublineText)){{ $sublineText }}@else{{ json_encode($sublineText, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}@endif</textarea>
                        </div>

                        <div>
                            <label for="preloader_status_line" class="block text-sm font-medium text-slate-900">
                                Status Line Text
                            </label>
                            <p class="text-xs text-slate-500 mt-1">Status message text (e.g., "Calibrating Index")</p>
                            <textarea id="preloader_status_line" 
                                      name="preloader_status_line" 
                                      rows="3"
                                      class="block w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg shadow-sm font-mono text-xs focus:outline-none focus:ring-2 focus:ring-navy focus:border-navy"
                                      placeholder='{"en":"Calibrating Index","de":"Calibrating Index",...}'>@if(is_string($statusLineText)){{ $statusLineText }}@else{{ json_encode($statusLineText, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}@endif</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Form Actions --}}
            <div class="flex items-center justify-between gap-4 px-6 py-4 bg-slate-50 border-t border-slate-200 rounded-b-xl">
                <div class="text-sm text-slate-600">
                    <x-heroicon-o-information-circle class="w-4 h-4 inline mr-1" />
                    Changes are saved immediately and cached for performance.
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" 
                            onclick="window.location.reload()"
                            class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-6 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                        <x-heroicon-o-check-circle class="w-4 h-4 inline mr-2" />
                        Save Preloader Settings
                    </button>
                </div>
            </div>
        </form>

        {{-- Info Box --}}
        <div class="mt-8 p-6 bg-slate-50 rounded-xl border border-slate-200">
            <h3 class="font-semibold text-slate-900 mb-3">How It Works</h3>
            <ul class="text-sm text-slate-700 space-y-2 list-disc list-inside">
                <li><strong>Enable:</strong> Toggle the preloader on/off globally</li>
                <li><strong>Pages:</strong> Select which pages show the preloader</li>
                <li><strong>Timing:</strong> Control how long the preloader displays</li>
                <li><strong>Text:</strong> Customize messages in all 5 languages</li>
                <li><strong>Result:</strong> Selected pages will show the preloader while loading</li>
            </ul>
        </div>
    </div>
</div>

<style>
    input[type="checkbox"]:checked + label,
    input[type="checkbox"]:checked ~ * {
        /* Allow browser default styling */
    }
</style>
@endsection
