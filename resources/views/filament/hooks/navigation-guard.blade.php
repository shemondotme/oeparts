{{--
    Safety net for the admin's single-page navigation (Filament ->spa(), Livewire wire:navigate).

    A click on a menu link fetches the next page and swaps it in. When that fetch comes back damaged (an empty or
    half-delivered document — seen as a white content area, a menu with missing icons, or a loading bar that never
    ends) the visitor used to have to press reload by hand; a reload always worked because it asks for the page again.
    This does that reload automatically:

    - after a navigation or a page load, if the page has no admin layout or no end marker (the hidden div at the
      bottom), it is loaded again as an ordinary page — at most 3 times within 30 seconds, so it can never loop;
    - if a navigation has not finished after 45 seconds, the target is opened as an ordinary page load.

    The listeners are registered once per tab and live on `document`/`window`, so they survive the page swaps. Nothing
    here touches the data of a page; a page that is merely slow (the SEO Health page on a cold cache) is given 45 seconds.
--}}
<script nonce="{{ csp_nonce() }}">
(() => {
    if (window.__oeNavGuard) return;
    window.__oeNavGuard = true;

    const KEY = 'oe-nav-heal';
    const SLOW_MS = 45000;
    const WINDOW_MS = 30000;
    const MAX_TRIES = 3;
    let timer = null;

    const read = () => {
        try { return JSON.parse(sessionStorage.getItem(KEY) || 'null') || { n: 0, t: 0 }; } catch (e) { return { n: 0, t: 0 }; }
    };
    const canTry = () => {
        const s = read();
        return Date.now() - s.t > WINDOW_MS || s.n < MAX_TRIES;
    };
    const loadAgain = (url) => {
        const s = read();
        const fresh = Date.now() - s.t > WINDOW_MS;
        try { sessionStorage.setItem(KEY, JSON.stringify({ n: fresh ? 1 : s.n + 1, t: fresh ? Date.now() : s.t })); } catch (e) { /* storage blocked: one reload is still safe */ }
        setTimeout(() => window.location.assign(url || window.location.href), 400);
    };
    const pageIsComplete = () => {
        const layout = document.querySelector('.fi-layout, .fi-simple-layout');
        const end = document.getElementById('oe-page-end');
        const text = (document.body && document.body.innerText || '').trim();

        return !!layout && !!end && text.length > 20;
    };
    // Evidence for the server log: the server announces how many bytes the page has (Server-Timing: oelen); the browser
    // knows how many it received (decodedBodySize). A difference means the page was damaged on the way, and the report says
    // over which protocol (http/1.1, h2, h3) — numbers only, nothing from the page itself.
    const REPORT_URL = @json(\Illuminate\Support\Facades\Route::has('admin.diagnostics.page-integrity') ? route('admin.diagnostics.page-integrity') : null);
    const reportShortPage = (via) => {
        try {
            const url = window.location.href.split('#')[0];
            const entry = via === 'load'
                ? performance.getEntriesByType('navigation')[0]
                : performance.getEntriesByType('resource').filter((e) => e.name === url).pop();
            performance.clearResourceTimings();
            const timing = entry && entry.serverTiming ? entry.serverTiming.find((s) => s.name === 'oelen') : null;
            if (!timing) return;
            const expected = parseInt(timing.description, 10);
            const received = entry.decodedBodySize;
            if (!REPORT_URL || !(expected > 0) || !(received > 0) || received >= expected) return;
            const meta = document.querySelector('meta[name="csrf-token"]');
            const body = JSON.stringify({
                _token: meta ? meta.content : '',
                path: window.location.pathname,
                expected: expected,
                received: received,
                transfer: entry.transferSize || null,
                protocol: entry.nextHopProtocol || null,
                via: via,
                millis: Math.round(entry.responseEnd - entry.startTime),
            });
            navigator.sendBeacon(REPORT_URL, new Blob([body], { type: 'application/json' }));
        } catch (e) { /* diagnostics must never get in the way of the page */ }
    };

    const check = (via) => {
        clearTimeout(timer);
        reportShortPage(via);
        if (!pageIsComplete() && canTry()) loadAgain();
    };

    document.addEventListener('livewire:navigate', (event) => {
        clearTimeout(timer);
        const url = event.detail && event.detail.url ? String(event.detail.url.href || event.detail.url) : null;
        timer = setTimeout(() => { if (url && canTry()) loadAgain(url); }, SLOW_MS);
    });
    document.addEventListener('livewire:navigated', () => check('navigate'));
    window.addEventListener('load', () => check('load'));
})();
</script>
<div id="oe-page-end" hidden></div>
