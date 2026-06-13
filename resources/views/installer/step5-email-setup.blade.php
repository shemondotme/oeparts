@extends('layouts.installer')

@section('title', 'Step 5: Email Setup')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">Email Configuration</h1>
    <p class="text-muted mb-6">Configure how your site sends emails (optional).</p>

    <form method="POST" action="{{ route('installer.process-email-setup') }}">
        @csrf

        <div class="mb-6">
            <label for="mail_driver" class="block text-sm font-medium text-slate-700 mb-1">
                Mail Driver
            </label>
            <select id="mail_driver" name="mail_driver"
                class="form-select w-full @error('mail_driver') border-red-300 @enderror" required>
                <option value="smtp" {{ old('mail_driver', 'smtp') == 'smtp' ? 'selected' : '' }}>SMTP</option>
                <option value="sendmail" {{ old('mail_driver') == 'sendmail' ? 'selected' : '' }}>Sendmail</option>
                <option value="mailgun" {{ old('mail_driver') == 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                <option value="ses" {{ old('mail_driver') == 'ses' ? 'selected' : '' }}>Amazon SES</option>
                <option value="postmark" {{ old('mail_driver') == 'postmark' ? 'selected' : '' }}>Postmark</option>
                <option value="log" {{ old('mail_driver') == 'log' ? 'selected' : '' }}>Log (testing)</option>
                <option value="array" {{ old('mail_driver') == 'array' ? 'selected' : '' }}>Array (testing)</option>
            </select>
            @error('mail_driver')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">For production, use SMTP or a transactional service</p>
        </div>

        <div id="smtp-fields" class="space-y-6 mb-6">
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label for="mail_host" class="block text-sm font-medium text-slate-700 mb-1">
                        SMTP Host
                    </label>
                    <input type="text" id="mail_host" name="mail_host" value="{{ old('mail_host', 'smtp.mailtrap.io') }}"
                        class="form-input w-full @error('mail_host') border-red-300 @enderror"
                        placeholder="smtp.gmail.com">
                    @error('mail_host')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="mail_port" class="block text-sm font-medium text-slate-700 mb-1">
                        SMTP Port
                    </label>
                    <input type="number" id="mail_port" name="mail_port" value="{{ old('mail_port', '587') }}"
                        class="form-input w-full @error('mail_port') border-red-300 @enderror"
                        placeholder="587">
                    @error('mail_port')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label for="mail_username" class="block text-sm font-medium text-slate-700 mb-1">
                        SMTP Username
                    </label>
                    <input type="text" id="mail_username" name="mail_username" value="{{ old('mail_username') }}"
                        class="form-input w-full @error('mail_username') border-red-300 @enderror"
                        placeholder="your-email@gmail.com">
                    @error('mail_username')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="mail_password" class="block text-sm font-medium text-slate-700 mb-1">
                        SMTP Password
                    </label>
                    <input type="password" id="mail_password" name="mail_password" value="{{ old('mail_password') }}"
                        class="form-input w-full @error('mail_password') border-red-300 @enderror"
                        placeholder="Your email password">
                    @error('mail_password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="mail_encryption" class="block text-sm font-medium text-slate-700 mb-1">
                    Encryption
                </label>
                <select id="mail_encryption" name="mail_encryption"
                    class="form-select w-full @error('mail_encryption') border-red-300 @enderror">
                    <option value="">None</option>
                    <option value="tls" {{ old('mail_encryption', 'tls') == 'tls' ? 'selected' : '' }}>TLS</option>
                    <option value="ssl" {{ old('mail_encryption') == 'ssl' ? 'selected' : '' }}>SSL</option>
                </select>
                @error('mail_encryption')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Usually TLS for port 587, SSL for port 465</p>
            </div>
        </div>

        <div class="grid md:grid-cols-2 gap-6 mb-8">
            <div>
                <label for="mail_from_address" class="block text-sm font-medium text-slate-700 mb-1">
                    From Email Address
                </label>
                <input type="email" id="mail_from_address" name="mail_from_address" value="{{ old('mail_from_address', 'noreply@example.com') }}"
                    class="form-input w-full @error('mail_from_address') border-red-300 @enderror"
                    placeholder="noreply@example.com" required>
                @error('mail_from_address')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Sender address for system emails</p>
            </div>

            <div>
                <label for="mail_from_name" class="block text-sm font-medium text-slate-700 mb-1">
                    From Name
                </label>
                <input type="text" id="mail_from_name" name="mail_from_name" value="{{ old('mail_from_name', 'OeParts') }}"
                    class="form-input w-full @error('mail_from_name') border-red-300 @enderror"
                    placeholder="OeParts" required>
                @error('mail_from_name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-muted">Display name for sender</p>
            </div>
        </div>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> You can skip SMTP configuration for now and use the "log" driver for testing. Email settings can be updated later in <code>.env</code> file.
                </div>
            </div>
        </div>

        <div class="flex justify-between items-center pt-6 border-t border-slate-200">
            <a href="{{ route('installer.admin-account') }}" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 transition-all duration-200">
                <x-heroicon-o-arrow-left class="w-4 h-4 mr-2" />
                Back
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Save & Install
                <x-heroicon-o-rocket-launch class="w-4 h-4 ml-2" />
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const driverSelect = document.getElementById('mail_driver');
        const smtpFields = document.getElementById('smtp-fields');
        
        function toggleSmtpFields() {
            if (driverSelect.value === 'smtp') {
                smtpFields.style.display = 'block';
                // Make SMTP fields required
                document.querySelectorAll('#smtp-fields input, #smtp-fields select').forEach(el => {
                    if (el.name !== 'mail_encryption') {
                        el.required = true;
                    }
                });
            } else {
                smtpFields.style.display = 'none';
                // Remove required from SMTP fields
                document.querySelectorAll('#smtp-fields input, #smtp-fields select').forEach(el => {
                    el.required = false;
                });
            }
        }
        
        driverSelect.addEventListener('change', toggleSmtpFields);
        toggleSmtpFields(); // Initial call
    });
</script>
@endpush
@endsection