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
                <option value="log" {{ old('mail_driver') == 'log' ? 'selected' : '' }}>Log (testing)</option>
                <option value="array" {{ old('mail_driver') == 'array' ? 'selected' : '' }}>Array (testing)</option>
            </select>
            @error('mail_driver')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-muted">For production, use SMTP. To use a transactional service (Mailgun, SES, Postmark, etc.), install with SMTP or Log here, then add the provider's own credentials to <code>.env</code> afterwards — those need extra keys this installer doesn't collect.</p>
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

        <div class="mb-6 p-4 border border-slate-200 rounded-lg">
            <label for="test_to" class="block text-sm font-medium text-slate-700 mb-1">
                Send a test email
            </label>
            <p class="text-xs text-muted mb-3">Verify these settings actually work before finishing — a typo'd SMTP password would otherwise only surface later, when a real order confirmation fails to send.</p>
            <div class="flex flex-col sm:flex-row gap-3">
                <input type="email" id="test_to" name="test_to" value="{{ old('mail_from_address', 'noreply@example.com') }}"
                    class="form-input w-full sm:flex-1" placeholder="you@example.com">
                <button type="button" id="send-test-email"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 transition-all duration-200 whitespace-nowrap">
                    Send Test Email
                </button>
            </div>
            <p id="test-email-result" class="mt-2 text-sm"></p>
        </div>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <div class="flex items-start gap-2">
                <x-heroicon-o-information-circle class="w-5 h-5 text-blue-600 shrink-0 mt-0.5" />
                <div class="text-sm text-blue-800">
                    <span class="font-medium">Note:</span> You can skip SMTP configuration for now and use the "log" driver for testing. Email settings can be updated later in <code>.env</code> file.
                </div>
            </div>
        </div>

        <div class="mb-8 p-4 border border-slate-200 rounded-lg">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="import_demo_data" value="1" {{ old('import_demo_data') ? 'checked' : '' }}
                    class="mt-1 rounded border-slate-300">
                <span>
                    <span class="block text-sm font-medium text-slate-700">Import demo catalog data</span>
                    <span class="block text-xs text-muted mt-0.5">Adds sample manufacturers, OEM parts, and blog posts so the storefront isn't empty on first look. Safe to skip — this never creates extra admin/customer accounts, only your own admin account from the previous step is created.</span>
                </span>
            </label>
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

        const sendButton = document.getElementById('send-test-email');
        const resultEl = document.getElementById('test-email-result');
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        sendButton.addEventListener('click', function () {
            const testTo = document.getElementById('test_to').value;
            if (!testTo) {
                resultEl.textContent = 'Enter an email address to send the test to.';
                resultEl.className = 'mt-2 text-sm text-red-600';
                return;
            }

            sendButton.disabled = true;
            sendButton.textContent = 'Sending…';
            resultEl.textContent = '';

            const payload = new FormData();
            ['mail_driver', 'mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name']
                .forEach(function (field) {
                    const el = document.getElementById(field);
                    if (el) payload.append(field, el.value);
                });
            payload.append('test_to', testTo);

            fetch('{{ route('installer.test-mail') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: payload,
            })
            .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
            .then(function (result) {
                resultEl.textContent = result.data.message;
                resultEl.className = 'mt-2 text-sm ' + (result.data.success ? 'text-green-600' : 'text-red-600');
            })
            .catch(function () {
                resultEl.textContent = 'Could not reach the server — try again.';
                resultEl.className = 'mt-2 text-sm text-red-600';
            })
            .finally(function () {
                sendButton.disabled = false;
                sendButton.textContent = 'Send Test Email';
            });
        });
    });
</script>
@endpush
@endsection