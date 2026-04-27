@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Settings</h1>
            <p class="text-slate-600 mt-2">Manage all system settings grouped by category.</p>
        </div>

        {{-- Settings Groups Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($groups as $group => $settings)
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
                    <div class="p-5">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="font-semibold text-slate-900 capitalize">{{ str_replace('_', ' ', $group) }}</h3>
                                <p class="text-sm text-slate-500 mt-1">{{ count($settings) }} setting{{ count($settings) !== 1 ? 's' : '' }}</p>
                            </div>
                            <div class="flex items-center">
                                @if(in_array($group, ['payment', 'mail', 'sms', 'api']))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                        <x-heroicon-o-lock-closed class="w-3 h-3 mr-1" />
                                        Secure
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4">
                            <ul class="space-y-1.5">
                                @foreach($settings->take(3) as $setting)
                                    <li class="flex items-center text-sm">
                                        <x-heroicon-o-key class="w-3.5 h-3.5 text-slate-400 mr-2 shrink-0" />
                                        <span class="text-slate-700 truncate">{{ $setting->key }}</span>
                                        <span class="ml-auto text-xs text-slate-500 font-mono">{{ $setting->type }}</span>
                                    </li>
                                @endforeach
                                @if(count($settings) > 3)
                                    <li class="text-xs text-slate-500 pt-1">
                                        +{{ count($settings) - 3 }} more...
                                    </li>
                                @endif
                            </ul>
                        </div>

                        <div class="mt-6 pt-5 border-t border-slate-100">
                            <a href="{{ route('admin.settings.edit', $group) }}" 
                               class="inline-flex items-center justify-center w-full px-4 py-2 text-sm font-medium text-white bg-navy hover:bg-navy/90 rounded-lg transition-colors">
                                <x-heroicon-o-cog-6-tooth class="w-4 h-4 mr-2" />
                                Edit Settings
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Add New Setting Group Card --}}
            <div class="bg-white rounded-xl border-2 border-dashed border-slate-300 hover:border-slate-400 transition-colors">
                <div class="p-5 flex flex-col items-center justify-center h-full min-h-[200px]">
                    <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <x-heroicon-o-plus class="w-6 h-6 text-slate-500" />
                    </div>
                    <h3 class="font-semibold text-slate-900">Add New Setting</h3>
                    <p class="text-sm text-slate-500 mt-1 text-center">Create a custom setting group</p>
                    <a href="{{ route('admin.settings.create') }}" 
                       class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                        Create New
                    </a>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="mt-10 bg-white rounded-xl border border-slate-200 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">Quick Actions</h3>
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('admin.settings.preloader') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-navy to-navy/80 hover:from-navy/90 hover:to-navy rounded-lg transition-colors">
                    <x-heroicon-o-ellipsis-horizontal-circle class="w-4 h-4 mr-2" />
                    Preloader Settings
                </a>
                <a href="{{ route('admin.settings.edit', 'general') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    <x-heroicon-o-globe-alt class="w-4 h-4 mr-2" />
                    General Settings
                </a>
                <a href="{{ route('admin.settings.edit', 'payment') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    <x-heroicon-o-credit-card class="w-4 h-4 mr-2" />
                    Payment Settings
                </a>
                <a href="{{ route('admin.settings.edit', 'mail') }}" 
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    <x-heroicon-o-envelope class="w-4 h-4 mr-2" />
                    Email Settings
                </a>
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    <x-heroicon-o-arrow-path class="w-4 h-4 mr-2" />
                    Clear Cache
                </button>
                <button type="button" 
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                    <x-heroicon-o-document-text class="w-4 h-4 mr-2" />
                    Export Settings
                </button>
            </div>
        </div>
    </div>
</div>
@endsection