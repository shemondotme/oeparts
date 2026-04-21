@extends('layouts.admin')

@section('title', 'Add Redirect')

@section('content')
<div class="px-6 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-slate-900">Add URL Redirect</h1>
            <p class="text-slate-600 mt-2">Create a new 301 or 302 redirect.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.redirects.store') }}" class="bg-white rounded-xl border border-slate-200 p-6">
            @csrf

            <div class="space-y-6">
                <div>
                    <label for="from_url" class="block text-sm font-medium text-slate-700 mb-1">From URL *</label>
                    <input type="text" id="from_url" name="from_url" value="{{ old('from_url') }}" required
                           placeholder="/old-page"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy font-mono">
                    <p class="mt-1 text-xs text-slate-500">The old URL path (e.g., /old-page)</p>
                </div>

                <div>
                    <label for="to_url" class="block text-sm font-medium text-slate-700 mb-1">To URL *</label>
                    <input type="text" id="to_url" name="to_url" value="{{ old('to_url') }}" required
                           placeholder="/new-page"
                           class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy font-mono">
                    <p class="mt-1 text-xs text-slate-500">The new URL path (e.g., /new-page)</p>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-slate-700 mb-1">Redirect Type *</label>
                    <select id="type" name="type" required class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy">
                        <option value="301" {{ old('type') === '301' ? 'selected' : '' }}>301 - Permanent Move</option>
                        <option value="302" {{ old('type') === '302' ? 'selected' : '' }}>302 - Temporary Move</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Use 301 for permanent URL changes (better for SEO)</p>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                           class="w-4 h-4 text-navy border-slate-300 rounded focus:ring-navy">
                    <label for="is_active" class="text-sm text-slate-700">Active</label>
                </div>
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <a href="{{ route('admin.settings.redirects') }}" class="px-5 py-2.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-navy rounded-lg hover:bg-navy/90">Create Redirect</button>
            </div>
        </form>
    </div>
</div>
@endsection
