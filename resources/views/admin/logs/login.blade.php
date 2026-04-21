@extends('layouts.admin')

@section('title', 'Login Logs')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Login Logs</h1>
                    <p class="text-slate-600 mt-2">Track all login attempts (admin and customer).</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.logs.activity') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg">Activity Logs</a>
                    <a href="{{ route('admin.logs.cron') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg">Cron Logs</a>
                    <a href="{{ route('admin.logs.email') }}" class="inline-flex items-center px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg">Email Logs</a>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">User Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 text-sm">#{{ $log->id }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $log->user_type === 'admin' ? 'bg-navy/10 text-navy' : 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst($log->user_type) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">{{ $log->email }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <span class="px-2 py-1 text-xs font-medium rounded {{ $log->status === 'success' ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm font-mono">{{ $log->ip_address }}</td>
                                <td class="px-6 py-4 text-sm">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-6 py-8 text-center text-slate-500">No login logs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($logs->hasPages())<div class="px-6 py-4 border-t">{{ $logs->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
