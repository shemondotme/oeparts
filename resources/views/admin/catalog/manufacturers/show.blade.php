@extends('layouts.admin')

@section('title', 'Manufacturer Details')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manufacturer Details</h1>
                <p class="text-gray-600 mt-1">View complete information for {{ $manufacturer->name['en'] ?? 'Manufacturer' }}.</p>
            </div>
            <div class="flex space-x-3">
                <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                    <x-heroicon-o-pencil class="w-4 h-4 mr-2" />
                    Edit
                </a>
                <a href="{{ route('admin.catalog.manufacturers.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber">
                    <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                    Back to Manufacturers
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Left Column: Manufacturer Details --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Basic Information Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1">
                                <div class="bg-gray-50 border border-gray-200 rounded p-3">
                                    <pre class="text-sm text-gray-800 whitespace-pre-wrap">{{ json_encode($manufacturer->name, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Slug</dt>
                            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $manufacturer->slug }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Country</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($manufacturer->country_code)
                                    {{ $countries[$manufacturer->country_code] ?? $manufacturer->country_code }}
                                @else
                                    Not specified
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Logo</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($manufacturer->logo)
                                    <a href="{{ route('admin.media.show', $manufacturer->logo) }}" class="text-amber hover:text-amber/80">
                                        {{ $manufacturer->logo->original_filename }}
                                    </a>
                                @else
                                    No logo
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Description Card --}}
            @if($manufacturer->description)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Description</h2>
                </div>
                <div class="p-6">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <pre class="text-sm text-gray-800 whitespace-pre-wrap">{{ json_encode($manufacturer->description, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            </div>
            @endif

            {{-- Contact & Website Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Contact & Website</h2>
                </div>
                <div class="p-6">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($manufacturer->website_url)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Website</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ $manufacturer->website_url }}" target="_blank" class="text-amber hover:text-amber/80 break-all">
                                    {{ $manufacturer->website_url }}
                                </a>
                            </dd>
                        </div>
                        @endif
                        @if($manufacturer->contact_email)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="mailto:{{ $manufacturer->contact_email }}" class="text-amber hover:text-amber/80">
                                    {{ $manufacturer->contact_email }}
                                </a>
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Statistics Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Statistics</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="text-2xl font-bold text-gray-900">{{ $manufacturer->products->count() }}</div>
                            <div class="text-sm text-gray-600">Products</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="text-2xl font-bold text-gray-900">{{ $manufacturer->carModels->count() }}</div>
                            <div class="text-sm text-gray-600">Car Models</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 border border-gray-200 rounded-lg">
                            <div class="text-2xl font-bold text-gray-900">{{ $manufacturer->created_at->diffForHumans() }}</div>
                            <div class="text-sm text-gray-600">Created</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Status & Actions --}}
        <div class="space-y-8">
            {{-- Status Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Status</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Active Status</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $manufacturer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $manufacturer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">OEM Verified</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $manufacturer->is_oem_verified ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $manufacturer->is_oem_verified ? 'Verified' : 'Not Verified' }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">Featured</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $manufacturer->is_featured ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $manufacturer->is_featured ? 'Featured' : 'Not Featured' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Actions Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Quick Actions</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-3">
                        <a href="{{ route('admin.catalog.manufacturers.edit', $manufacturer) }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                            <x-heroicon-o-pencil class="w-4 h-4 mr-2" />
                            Edit Manufacturer
                        </a>
                        <button type="button" 
                                onclick="if(confirm('Are you sure you want to delete this manufacturer? This action cannot be undone.')) { document.getElementById('delete-form').submit(); }"
                                class="w-full flex items-center justify-center px-4 py-2 border border-red-300 rounded-lg text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red transition-colors">
                            <x-heroicon-o-trash class="w-4 h-4 mr-2" />
                            Delete Manufacturer
                        </button>
                        <a href="{{ route('admin.catalog.products.index', ['manufacturer' => $manufacturer->id]) }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                            <x-heroicon-o-cube class="w-4 h-4 mr-2" />
                            View Products
                        </a>
                        <a href="{{ route('admin.catalog.car-models.index', ['manufacturer' => $manufacturer->id]) }}" 
                           class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber transition-colors">
                            <x-heroicon-o-truck class="w-4 h-4 mr-2" />
                            View Car Models
                        </a>
                    </div>
                </div>
            </div>

            {{-- Metadata Card --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Metadata</h2>
                </div>
                <div class="p-6">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $manufacturer->created_at->format('M d, Y H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Last Updated</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $manufacturer->updated_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @if($manufacturer->deleted_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Deleted</dt>
                            <dd class="mt-1 text-sm text-red-600">{{ $manufacturer->deleted_at->format('M d, Y H:i') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Delete Form --}}
<form id="delete-form" action="{{ route('admin.catalog.manufacturers.destroy', $manufacturer) }}" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>
@endsection