/**
 * Polls /build-version and shows a dismissible toast if the deployed build
 * changed since this page loaded (e.g. a self-update ran while this tab was
 * open). Assets are content-hashed so nothing ever breaks — this is purely
 * a "hey, refresh when convenient" nudge.
 */
export default function initBuildFreshnessCheck({ intervalMs = 5 * 60 * 1000 } = {}) {
    const meta = document.querySelector('meta[name="app-build"]');
    if (!meta) return;

    const loadedBuild = meta.content;
    let notified = false;

    async function poll() {
        if (notified || document.visibilityState !== 'visible') return;
        try {
            const res = await fetch('/build-version', { headers: { Accept: 'application/json' } });
            if (!res.ok) return;
            const { build } = await res.json();
            if (build && build !== loadedBuild) {
                notified = true;
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: {
                        type: 'info',
                        title: 'UPDATE · AVAILABLE',
                        message: 'A new version of the site is available.',
                        duration: 60000,
                        action: { url: window.location.href, label: 'Refresh now' },
                    },
                }));
            }
        } catch {
            /* offline or transient — try again next interval */
        }
    }

    setInterval(poll, intervalMs);
}
