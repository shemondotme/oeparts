@extends('layouts.installer')

@section('title', 'Installation Complete')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8 text-center">
    <div class="mb-6 flex justify-center">
        <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center">
            <x-heroicon-o-check class="w-10 h-10 text-green-600" />
        </div>
    </div>

    <h1 class="text-3xl font-bold text-navy mb-3">Installation Complete!</h1>
    <p class="text-lg text-muted mb-6 max-w-2xl mx-auto">
        OEMHub has been successfully installed and configured. You can now access your website and admin panel.
    </p>

    <div class="grid md:grid-cols-2 gap-6 mb-8 max-w-2xl mx-auto">
        <div class="bg-slate-50 border border-slate-200 rounded-lg p-5">
            <div class="w-10 h-10 rounded-full bg-navy text-white flex items-center justify-center mb-3 mx-auto">
                <x-heroicon-o-globe-alt class="w-5 h-5" />
            </div>
            <h3 class="font-semibold text-slate-800 mb-2">Visit Your Website</h3>
            <p class="text-sm text-muted mb-3">Check out your newly installed site.</p>
            <a href="{{ url('/') }}" target="_blank" class="btn-outline w-full">
                Go to Homepage
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 ml-2" />
            </a>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-lg p-5">
            <div class="w-10 h-10 rounded-full bg-navy text-white flex items-center justify-center mb-3 mx-auto">
                <x-heroicon-o-cog class="w-5 h-5" />
            </div>
            <h3 class="font-semibold text-slate-800 mb-2">Admin Dashboard</h3>
            <p class="text-sm text-muted mb-3">Manage your site, products, and orders.</p>
            <a href="{{ route('admin.login') }}" target="_blank" class="btn-primary w-full">
                Go to Admin
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </a>
        </div>
    </div>

    <div class="mb-8 p-5 bg-blue-50 border border-blue-200 rounded-lg max-w-2xl mx-auto">
        <h3 class="font-semibold text-blue-800 mb-2">Next Steps</h3>
        <ul class="text-sm text-blue-700 space-y-1 text-left">
            <li class="flex items-start gap-2">
                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                <span>Review your site settings in the admin panel</span>
            </li>
            <li class="flex items-start gap-2">
                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                <span>Add your first products and manufacturers</span>
            </li>
            <li class="flex items-start gap-2">
                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                <span>Configure payment gateways if needed</span>
            </li>
            <li class="flex items-start gap-2">
                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                <span>Set up email notifications for orders</span>
            </li>
        </ul>
    </div>

    <div class="p-5 bg-amber-50 border border-amber-200 rounded-lg max-w-2xl mx-auto mb-8">
        <div class="flex items-start gap-3">
            <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
            <div class="text-left">
                <h3 class="font-semibold text-amber-800 mb-1">Security Reminder</h3>
                <p class="text-sm text-amber-700">
                    For security reasons, the installer has been disabled. If you need to re‑run it, delete the file
                    <code class="bg-amber-100 px-1 rounded">storage/installed.lock</code>.
                </p>
            </div>
        </div>
    </div>

    <div class="pt-6 border-t border-slate-200">
        <p class="text-sm text-muted">
            Thank you for choosing OEMHub. Need help? Check out our
            <a href="https://github.com/oemhub/docs" target="_blank" class="text-navy hover:underline font-medium">documentation</a>.
        </p>
    </div>
</div>
@endsection
