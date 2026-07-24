@extends('layouts.installer')

@section('title', 'Step 6: Installing')

@section('content')
<div class="bg-white rounded-xl border border-slate-200 p-6 md:p-8">
    <h1 class="text-2xl font-bold text-navy mb-2">Installing OeParts</h1>
    <p class="text-muted mb-6">
        This runs in small steps so it never times out, even on slower shared hosting.
        Please don't close this tab until it finishes.
    </p>

    <div class="mb-6">
        <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden">
            <div id="install-progress-bar" class="h-3 bg-navy transition-all duration-300" style="width: 0%"></div>
        </div>
        <p id="install-progress-text" class="mt-2 text-sm text-muted">Starting…</p>
    </div>

    <div id="install-log"
        class="mb-6 max-h-64 overflow-y-auto text-xs font-mono bg-slate-50 border border-slate-200 rounded-lg p-3 space-y-1"></div>

    <div id="install-error" class="hidden mb-6 flex items-start gap-3 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
        <x-heroicon-o-x-circle class="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
        <span id="install-error-message"></span>
    </div>

    <div id="install-retry" class="hidden">
        <form method="POST" action="{{ route('installer.retry') }}">
            @csrf
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-semibold bg-navy text-white shadow-sm hover:bg-navy/90 transition-all duration-200">
                Retry Installation
            </button>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bar = document.getElementById('install-progress-bar');
    const text = document.getElementById('install-progress-text');
    const log = document.getElementById('install-log');
    const errorBox = document.getElementById('install-error');
    const errorMessage = document.getElementById('install-error-message');
    const retryBox = document.getElementById('install-retry');
    const advanceUrl = '{{ route('installer.advance') }}';
    const completeUrl = '{{ route('installer.complete') }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let lastMessage = null;

    function appendLog(message) {
        if (!message || message === lastMessage) return;
        lastMessage = message;
        const line = document.createElement('div');
        line.textContent = message;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function poll() {
        fetch(advanceUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            bar.style.width = data.percent + '%';
            appendLog(data.message);

            if (data.status === 'failed') {
                text.textContent = 'Installation failed.';
                errorMessage.textContent = data.error || 'Unknown error — check storage/logs/install-*.log for details.';
                errorBox.classList.remove('hidden');
                retryBox.classList.remove('hidden');
                return;
            }

            if (data.status === 'success') {
                text.textContent = 'Done — redirecting…';
                window.location.href = completeUrl;
                return;
            }

            text.textContent = 'Step ' + (data.step_index + 1) + ' of ' + data.total_steps + '…';
            setTimeout(poll, 600);
        })
        .catch(function () {
            // Transient network hiccup (e.g. a brief PHP-FPM restart mid-poll)
            // — keep trying rather than stopping cold, the run itself is safe
            // to resume since each step is checkpointed server-side.
            setTimeout(poll, 2000);
        });
    }

    poll();
});
</script>
@endpush
@endsection
