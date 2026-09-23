{{--
    Two things live in this one script, both keyed off the same deployed
    build changing under an open admin tab (Module 21 self-update, or a
    manual asset-only redeploy):

    1. Gap A — polls /build-version and shows a dismissible "new version"
       banner if the build changed since this tab loaded.
    2. Gap B — Livewire's release_token feature (config/AppServiceProvider)
       makes a stale tab's next Livewire request come back as a clean 419
       instead of a corrupt-payload error. Livewire's own client already
       handles 419 with a native confirm()+reload; we intercept it via the
       documented `request` hook and show a branded modal instead.
--}}
@php
    $i18n = [
        'bannerText' => __('updates.new_admin_version_available'),
        'refresh' => __('updates.refresh'),
        'staleTitle' => __('updates.stale_session_title'),
        'staleBody' => __('updates.stale_session_body'),
        'refreshNow' => __('updates.refresh_now'),
    ];
@endphp
<script nonce="{{ csp_nonce() }}">
document.addEventListener('livewire:init', () => {
    const i18n = {!! json_encode($i18n, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!};

    const meta = document.querySelector('meta[name="app-build"]');
    const loadedBuild = meta ? meta.content : null;
    let banner = null;

    function showBanner() {
        if (banner) return;
        banner = document.createElement('div');
        banner.setAttribute('role', 'status');
        banner.className = 'fixed bottom-4 right-4 z-[9999] rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 shadow-lg flex items-center gap-3 text-sm text-amber-900 dark:text-amber-100';
        banner.innerHTML = `
            <span>${i18n.bannerText}</span>
            <button type="button" class="fi-btn px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wider">${i18n.refresh}</button>
            <button type="button" aria-label="Dismiss" class="text-amber-700 hover:text-amber-900 dark:text-amber-300 dark:hover:text-amber-100">&times;</button>
        `;
        const [refreshBtn, dismissBtn] = banner.querySelectorAll('button');
        refreshBtn.addEventListener('click', () => window.location.reload());
        dismissBtn.addEventListener('click', () => banner.remove());
        document.body.appendChild(banner);
    }

    async function pollBuildVersion() {
        if (banner || !loadedBuild || document.visibilityState !== 'visible') return;
        try {
            const res = await fetch('/build-version', { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const { build } = await res.json();
            if (build && build !== loadedBuild) showBanner();
        } catch {
            /* offline or transient — try again next interval */
        }
    }

    setInterval(pollBuildVersion, 5 * 60 * 1000);

    function showStaleSessionModal() {
        if (document.getElementById('oe-stale-session-modal')) return;
        const overlay = document.createElement('div');
        overlay.id = 'oe-stale-session-modal';
        overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-black/50';
        overlay.innerHTML = `
            <div class="max-w-sm rounded-xl bg-white dark:bg-gray-900 p-6 shadow-xl text-center space-y-4">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">${i18n.staleTitle}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">${i18n.staleBody}</p>
                <button type="button" class="fi-btn w-full rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold py-2">${i18n.refreshNow}</button>
            </div>`;
        overlay.querySelector('button').addEventListener('click', () => window.location.reload());
        document.body.appendChild(overlay);
    }

    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            // 419 covers a Livewire release-token mismatch, a corrupt
            // component payload, and plain CSRF-token expiry — all three
            // are correctly resolved by "reload the page". Anything else
            // (404/500/network) is left to Livewire's default handling so
            // a real bug isn't masked behind a generic refresh prompt.
            if (status !== 419) return;
            preventDefault();
            showStaleSessionModal();
        });
    });
});
</script>
