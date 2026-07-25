@extends('layouts.installer')

@section('title', 'Installation Complete')

@section('content')
<div class="bg-paper rounded-2xl border border-rule shadow-admin-card p-6 md:p-10 text-center">
    <div class="mb-6 flex justify-center">
        <div class="w-20 h-20 rounded-full bg-amber/15 flex items-center justify-center">
            <x-heroicon-o-check class="w-10 h-10 text-amber-text" />
        </div>
    </div>

    <h1 class="font-display text-3xl font-extrabold text-ink mb-3">Installation Complete!</h1>
    <p class="text-lg text-muted mb-8 max-w-2xl mx-auto">
        OeParts has been successfully installed and configured. You can now access your website and admin panel.
    </p>

    <div class="grid md:grid-cols-2 gap-6 mb-8 max-w-2xl mx-auto">
        <div class="bg-bg-page border border-rule rounded-xl p-5">
            <div class="w-10 h-10 rounded-full bg-navy text-white flex items-center justify-center mb-3 mx-auto">
                <x-heroicon-o-globe-alt class="w-5 h-5" />
            </div>
            <h3 class="font-semibold text-ink mb-2">Visit Your Website</h3>
            <p class="text-sm text-muted mb-3">Check out your newly installed site.</p>
            <a href="{{ url('/') }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-rule text-ink hover:bg-paper transition-all duration-200 w-full">
                Go to Homepage
                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4 ml-2" />
            </a>
        </div>

        <div class="bg-bg-page border border-rule rounded-xl p-5">
            <div class="w-10 h-10 rounded-full bg-navy text-white flex items-center justify-center mb-3 mx-auto">
                <x-heroicon-o-cog class="w-5 h-5" />
            </div>
            <h3 class="font-semibold text-ink mb-2">Admin Dashboard</h3>
            <p class="text-sm text-muted mb-3">Manage your site, products, and orders.</p>
            <a href="{{ route('filament.admin.auth.login') }}" target="_blank" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200 w-full">
                Go to Admin
                <x-heroicon-o-arrow-right class="w-4 h-4 ml-2" />
            </a>
        </div>
    </div>

    <div class="mb-8 p-5 bg-navy/5 border border-navy/20 rounded-xl max-w-2xl mx-auto">
        <h3 class="font-semibold text-navy mb-2 text-left">Next Steps</h3>
        <ul class="text-sm text-navy space-y-1 text-left">
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
                <span>Enable OTP / two-step verification in Security Settings once real mail delivery works — it starts OFF so a fresh install isn't blocked by missing SMTP</span>
            </li>
            <li class="flex items-start gap-2">
                <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 shrink-0 mt-0.5" />
                <span>Set up email notifications for orders</span>
            </li>
        </ul>
    </div>

    <div class="p-5 bg-amber/10 border border-amber/30 rounded-xl max-w-2xl mx-auto mb-8">
        <div class="flex items-start gap-3">
            <x-heroicon-o-shield-exclamation class="w-5 h-5 text-amber-text shrink-0 mt-0.5" />
            <div class="text-left">
                <h3 class="font-semibold text-amber-text mb-1">Security Reminder</h3>
                <p class="text-sm text-amber-text">
                    For security reasons, the installer is now disabled — this is deliberate and permanent for
                    this install. It cannot be re-enabled by deleting
                    <code class="bg-ink/10 px-1 rounded">storage/installed.lock</code>: the installer also
                    checks for real data in the database and will refuse to run again rather than risk erasing
                    it. To start over on a genuinely fresh database, restore a clean database first, then delete
                    the lock file.
                </p>
            </div>
        </div>
    </div>

    <div class="pt-6 border-t border-rule">
        <p class="text-sm text-muted">
            Thank you for choosing OeParts. Need help? Check out our
            <a href="https://github.com/oeparts/docs" target="_blank" class="text-navy hover:underline font-medium">documentation</a>.
        </p>
    </div>
</div>
@endsection
