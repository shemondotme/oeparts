@extends('layouts.admin')

@section('title', 'Part Inquiries')

@section('content')
<div class="px-6 py-8">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Part Inquiries</h1>
            <p class="text-gray-600 mt-1">Manage customer requests for parts not found in the catalog</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.cms.inquiries.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                Export CSV
            </a>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session('success'))
        <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" />
            <span class="text-sm">{{ session('success') }}</span>
        </div>
    @endif

    {{-- Status Filter Tabs --}}
    <div class="flex items-center gap-2 mb-6 flex-wrap">
        <a href="{{ route('admin.cms.inquiries.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
               {{ $currentStatus === 'all' ? 'bg-[#0B3A68] text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
            All
            <span class="text-xs {{ $currentStatus === 'all' ? 'bg-white/20' : 'bg-gray-100' }} px-1.5 py-0.5 rounded-full">
                {{ $inquiries->total() }}
            </span>
        </a>
        @foreach($statuses as $status)
            <a href="{{ route('admin.cms.inquiries.index', ['status' => $status->value]) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors
                   {{ $currentStatus === $status->value ? 'bg-[#0B3A68] text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                @if($status->value === 'new')
                    <x-heroicon-o-bell class="w-3.5 h-3.5" />
                @elseif($status->value === 'reviewing')
                    <x-heroicon-o-magnifying-glass class="w-3.5 h-3.5" />
                @elseif($status->value === 'sourced')
                    <x-heroicon-o-check-circle class="w-3.5 h-3.5" />
                @else
                    <x-heroicon-o-x-circle class="w-3.5 h-3.5" />
                @endif
                {{ ucfirst($status->value) }}
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inquiry</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Part Details</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($inquiries as $inquiry)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">#{{ $inquiry->id }}</div>
                                @if($inquiry->admin_note)
                                    <div class="text-xs text-amber-600 mt-0.5">Has note</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">{{ $inquiry->email }}</div>
                                @if($inquiry->vin_number)
                                    <div class="text-xs text-gray-500 font-mono mt-0.5">VIN: {{ $inquiry->vin_number }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-mono font-medium text-gray-900">{{ $inquiry->oem_number }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $inquiry->manufacturer }}
                                    @if($inquiry->car_model) — {{ $inquiry->car_model }}@endif
                                    @if($inquiry->year) ({{ $inquiry->year }})@endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($inquiry->status->value === 'new') bg-blue-100 text-blue-800
                                    @elseif($inquiry->status->value === 'reviewing') bg-yellow-100 text-yellow-800
                                    @elseif($inquiry->status->value === 'sourced') bg-green-100 text-green-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($inquiry->status->value) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $inquiry->created_at->format('M d, Y') }}
                                <div class="text-xs text-gray-400">{{ $inquiry->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.cms.inquiries.show', $inquiry) }}"
                                   class="text-[#0B3A68] hover:text-blue-900 inline-flex items-center gap-1">
                                    <x-heroicon-o-eye class="w-4 h-4" />
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <x-heroicon-o-inbox class="w-12 h-12 mx-auto text-gray-300 mb-3" />
                                <p class="text-lg font-medium text-gray-900">No inquiries found</p>
                                <p class="text-gray-600 mt-1">No part inquiries match the current filter.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $inquiries->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
        @foreach($statuses as $status)
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <p class="text-sm font-medium text-gray-500">{{ ucfirst($status->value) }}</p>
                <p class="text-2xl font-bold mt-1
                    @if($status->value === 'new') text-blue-600
                    @elseif($status->value === 'reviewing') text-yellow-600
                    @elseif($status->value === 'sourced') text-green-600
                    @else text-gray-600
                    @endif">
                    {{ count($kanban[$status->value] ?? []) }}
                </p>
            </div>
        @endforeach
    </div>
</div>
@endsection
