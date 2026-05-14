@extends('layouts.admin')

@section('title', 'IP Blocklist')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">IP Blocklist</h1>
                    <p class="text-slate-600 mt-2">Block or allow specific IP addresses.</p>
                </div>
                <a href="{{ route('admin.settings.ip-blocklist.create') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-navy rounded-lg hover:bg-navy/90 transition-colors">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    Block IP
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.settings.ip-blocklist.index') }}" class="mb-6">
            <div class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search IP address..."
                       class="flex-1 rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                <select name="status" class="rounded-lg border border-slate-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-navy text-white rounded-lg hover:bg-navy/90">Filter</button>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Reason</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Blocked By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        @forelse($blocklist as $ip)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-mono text-slate-900">{{ $ip->ip_address }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $ip->reason }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $ip->blocker->name ?? 'System' }}</td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    {{ $ip->expires_at ? $ip->expires_at->format('Y-m-d H:i') : 'Never' }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $ip->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $ip->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('admin.settings.ip-blocklist.toggle', $ip) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-slate-600 hover:text-navy">
                                                {{ $ip->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.settings.ip-blocklist.destroy', $ip) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800"
                                                    onclick="return confirm('Remove this IP from blocklist?')">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                    No blocked IP addresses found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($blocklist->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">
                    {{ $blocklist->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
