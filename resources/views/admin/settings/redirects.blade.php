@extends('layouts.admin')

@section('title', 'URL Redirects')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">URL Redirects</h1>
                    <p class="text-slate-600 mt-2">Manage 301 and 302 redirects for SEO.</p>
                </div>
                <a href="{{ route('admin.settings.redirects.create') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-navy rounded-lg hover:bg-navy/90">
                    <x-heroicon-o-plus class="w-4 h-4 mr-2" />
                    Add Redirect
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-6 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.settings.redirects') }}" class="mb-6">
            <div class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search URLs..."
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">From</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">To</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Hits</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        @forelse($redirects as $redirect)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm font-mono text-slate-900">{{ $redirect->from_url }}</td>
                                <td class="px-6 py-4 text-sm font-mono text-slate-600">{{ $redirect->to_url }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $redirect->type === 301 ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $redirect->type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">{{ $redirect->hit_count }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $redirect->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ $redirect->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.settings.redirects.edit', $redirect) }}" class="text-navy hover:text-navy/80">Edit</a>
                                        <form method="POST" action="{{ route('admin.settings.redirects.toggle', $redirect) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-slate-600 hover:text-navy">{{ $redirect->is_active ? 'Deactivate' : 'Activate' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.settings.redirects.destroy', $redirect) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-800" onclick="return confirm('Delete this redirect?')">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No redirects found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($redirects->hasPages())<div class="px-6 py-4 border-t">{{ $redirects->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
