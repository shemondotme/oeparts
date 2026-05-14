@extends('layouts.admin')

@section('title', 'Block IP Address')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Block IP Address</h1>
            <p class="text-slate-600 mt-2">Add an IP address to the blocklist.</p>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.ip-blocklist.store') }}" class="bg-white rounded-xl border border-slate-200 p-6">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="ip_address" class="block text-sm font-medium text-slate-700 mb-1">
                        IP Address *
                    </label>
                    <input type="text" id="ip_address" name="ip_address" value="{{ old('ip_address') }}" required
                           placeholder="192.168.1.100"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy font-mono">
                    <p class="mt-1 text-xs text-slate-500">Enter the IP address you want to block.</p>
                </div>

                <div>
                    <label for="reason" class="block text-sm font-medium text-slate-700 mb-1">
                        Reason *
                    </label>
                    <textarea id="reason" name="reason" rows="3" required
                              placeholder="e.g., Suspicious activity, brute force attempts, spam..."
                              class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy"></textarea>
                </div>

                <div>
                    <label for="expires_at" class="block text-sm font-medium text-slate-700 mb-1">
                        Expires At (optional)
                    </label>
                    <input type="datetime-local" id="expires_at" name="expires_at" value="{{ old('expires_at') }}"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                    <p class="mt-1 text-xs text-slate-500">Leave empty for permanent block.</p>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.settings.ip-blocklist.index') }}"
                   class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-navy rounded-lg hover:bg-navy/90">
                    Block IP
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
