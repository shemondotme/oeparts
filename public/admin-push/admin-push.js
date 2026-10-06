/* OeParts admin — device alerts (Web Push), sound, install prompt, app badge.
 * Configured through window.OEPUSH (see resources/views/filament/hooks/admin-push.blade.php). */
(function () {
    'use strict';

    var C = window.OEPUSH;
    if (!C || window.__oepushLoaded) return;
    window.__oepushLoaded = true;

    var T = C.i18n || {};
    var supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    var isIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    var registration = null;
    var subscription = null;
    var state = 'loading'; // loading | unsupported | ios-install | denied | off | on
    var installEvent = null;
    var audio = null;
    var audioUnlocked = false;
    var busy = false;

    function store(key, value) {
        try {
            if (value === undefined) return localStorage.getItem(key);
            if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, value);
        } catch (e) { /* storage may be blocked */ }
        return null;
    }

    function isMuted() { return store('oepush.mute') === '1' || C.soundEnabled === false; }

    function csrf() { return C.csrf; }

    function api(url, options) {
        options = options || {};
        var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (options.body) { headers['Content-Type'] = 'application/json'; headers['X-CSRF-TOKEN'] = csrf(); }
        return fetch(url, { method: options.method || 'GET', credentials: 'same-origin', headers: headers, body: options.body ? JSON.stringify(options.body) : undefined })
            .then(function (res) { return res.json().catch(function () { return {}; }).then(function (json) { return { ok: res.ok, status: res.status, json: json }; }); });
    }

    function urlBase64ToUint8Array(base64) {
        var padding = '='.repeat((4 - (base64.length % 4)) % 4);
        var raw = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    /* ── Sound ─────────────────────────────────────────────────────────── */
    function unlockAudio() {
        if (audioUnlocked) return;
        audioUnlocked = true;
        try {
            audio = audio || new Audio(C.soundUrl);
            audio.volume = 0;
            var p = audio.play();
            if (p && p.then) p.then(function () { audio.pause(); audio.currentTime = 0; audio.volume = 1; }).catch(function () { audioUnlocked = false; });
        } catch (e) { audioUnlocked = false; }
    }
    ['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, unlockAudio, { once: true, passive: true });
    });

    function chime() {
        if (isMuted()) return;
        try {
            audio = audio || new Audio(C.soundUrl);
            audio.volume = 1;
            audio.currentTime = 0;
            var p = audio.play();
            if (p && p.catch) p.catch(function () { /* autoplay blocked until the first click — acceptable */ });
        } catch (e) { /* no audio */ }
    }

    function refreshBell() {
        try { if (window.Livewire && window.Livewire.dispatch) window.Livewire.dispatch('databaseNotificationsSent'); } catch (e) { /* bell polls anyway */ }
    }

    function setBadge(count) {
        try {
            if (typeof count !== 'number') return;
            if (count > 0 && navigator.setAppBadge) navigator.setAppBadge(count);
            else if (count <= 0 && navigator.clearAppBadge) navigator.clearAppBadge();
        } catch (e) { /* optional */ }
    }

    /* ── UI ────────────────────────────────────────────────────────────── */
    function menuItems() {
        var items = [];
        if (state === 'on') {
            items.push({ id: 'sound', label: isMuted() ? T.soundOn : T.soundOff });
            items.push({ id: 'test', label: T.sendTest });
            items.push({ id: 'off', label: T.disable });
        } else if (state === 'off') {
            items.push({ id: 'on', label: T.enable, primary: true });
            items.push({ id: 'sound', label: isMuted() ? T.soundOn : T.soundOff });
        } else if (state === 'ios-install') {
            items.push({ id: 'info', label: T.iosInstall });
        } else if (state === 'denied') {
            items.push({ id: 'info', label: T.denied });
        } else if (state === 'unsupported') {
            items.push({ id: 'info', label: T.unsupported });
        }
        if (installEvent && !standalone) items.push({ id: 'install', label: T.installApp });
        items.push({ id: 'settings', label: T.settings });
        return items;
    }

    function render() {
        document.querySelectorAll('[data-oepush]').forEach(function (root) {
            var btn = root.querySelector('[data-oepush-toggle]');
            var menu = root.querySelector('[data-oepush-menu]');
            if (!btn || !menu) return;

            root.setAttribute('data-state', state);
            btn.setAttribute('aria-label', T.title);
            btn.title = state === 'on' ? (isMuted() ? T.statusMuted : T.statusOn) : (state === 'off' ? T.enable : T.title);

            menu.innerHTML = '';
            menuItems().forEach(function (item) {
                var b = document.createElement('button');
                b.type = 'button';
                b.setAttribute('data-oepush-action', item.id);
                b.className = 'oepush-item' + (item.primary ? ' is-primary' : '') + (item.id === 'info' ? ' is-info' : '');
                b.textContent = item.label;
                menu.appendChild(b);
            });
        });
    }

    function toast(message, ok) {
        try {
            if (window.FilamentNotification) {
                new window.FilamentNotification().title(message)[ok ? 'success' : 'danger']().send();
                return;
            }
        } catch (e) { /* fall back */ }
        window.alert(message);
    }

    /* ── Push subscription ─────────────────────────────────────────────── */
    function sync() {
        if (!supported) return Promise.resolve();
        return api(C.urls.subscribe, {
            method: 'POST',
            body: Object.assign({ contentEncoding: (window.PushManager.supportedContentEncodings || ['aes128gcm'])[0] }, subscription.toJSON()),
        });
    }

    function evaluate() {
        if (!supported) {
            state = isIOS && !standalone ? 'ios-install' : 'unsupported';
            render();
            return Promise.resolve();
        }
        if (Notification.permission === 'denied') { state = 'denied'; render(); return Promise.resolve(); }

        return registration.pushManager.getSubscription().then(function (sub) {
            subscription = sub;
            state = sub && Notification.permission === 'granted' ? 'on' : 'off';
            render();

            if (state === 'on') {
                // Keep the server's record fresh (idempotent) at most once a day.
                var last = parseInt(store('oepush.synced') || '0', 10);
                if (Date.now() - last > 86400000) { sync().then(function () { store('oepush.synced', String(Date.now())); }); }
            } else if (Notification.permission === 'granted') {
                // Permission was given earlier but the browser dropped the subscription: re-create silently.
                return enable(true);
            }
        });
    }

    function enable(silent) {
        if (busy) return Promise.resolve();
        busy = true;
        unlockAudio();

        return Notification.requestPermission().then(function (permission) {
            if (permission !== 'granted') { state = permission === 'denied' ? 'denied' : 'off'; render(); return null; }

            return registration.pushManager.getSubscription().then(function (existing) {
                return existing || registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(C.publicKey) });
            });
        }).then(function (sub) {
            if (!sub) return;
            subscription = sub;
            return sync().then(function (res) {
                if (res && res.ok) {
                    state = 'on';
                    store('oepush.synced', String(Date.now()));
                    if (!silent) toast(T.enabledOk, true);
                } else {
                    toast(T.enableFailed, false);
                }
                render();
            });
        }).catch(function () {
            if (!silent) toast(T.enableFailed, false);
            render();
        }).then(function () { busy = false; });
    }

    function disable() {
        if (!subscription) { state = 'off'; render(); return Promise.resolve(); }
        var endpoint = subscription.endpoint;
        return subscription.unsubscribe().catch(function () {}).then(function () {
            subscription = null;
            state = 'off';
            render();
            return api(C.urls.unsubscribe, { method: 'POST', body: { endpoint: endpoint } });
        });
    }

    function sendTest() {
        return api(C.urls.test, { method: 'POST', body: { endpoint: subscription ? subscription.endpoint : null } })
            .then(function (res) { toast((res.json && res.json.message) || T.testFailed, !!res.ok); });
    }

    /* ── Menu interactions (delegated, survives SPA navigation) ────────── */
    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-oepush-toggle]');
        var action = event.target.closest('[data-oepush-action]');

        document.querySelectorAll('[data-oepush]').forEach(function (root) {
            var menu = root.querySelector('[data-oepush-menu]');
            if (!menu) return;
            if (toggle && root.contains(toggle)) {
                menu.hidden = !menu.hidden;
            } else if (!(action && root.contains(action))) {
                menu.hidden = true;
            }
        });

        if (!action) return;
        var id = action.getAttribute('data-oepush-action');
        document.querySelectorAll('[data-oepush-menu]').forEach(function (m) { m.hidden = true; });

        if (id === 'on') enable(false);
        else if (id === 'off') disable();
        else if (id === 'test') sendTest();
        else if (id === 'sound') { store('oepush.mute', isMuted() ? null : '1'); if (C.soundEnabled === false) toast(T.soundOffInProfile, false); render(); if (!isMuted()) chime(); }
        else if (id === 'settings') window.location.href = C.urls.settings;
        else if (id === 'install' && installEvent) { installEvent.prompt(); installEvent = null; render(); }
        else if (id === 'info') window.alert(action.textContent);
    });

    window.addEventListener('beforeinstallprompt', function (event) { event.preventDefault(); installEvent = event; render(); });
    window.addEventListener('appinstalled', function () { installEvent = null; render(); });
    document.addEventListener('livewire:navigated', render);

    /* ── Messages from the service worker ──────────────────────────────── */
    function onWorkerMessage(event) {
        var data = event.data || {};
        if (data.type === 'push') {
            if (data.payload && data.payload.sound && document.visibilityState === 'visible') chime();
            refreshBell();
        } else if (data.type === 'navigate' && data.url) {
            window.location.href = data.url;
        } else if (data.type === 'resubscribe') {
            evaluate();
        }
    }

    /* ── Fallback polling (page open, no push on this device) + badge ──── */
    function poll() {
        var cursor = store('oepush.cursor') || '';
        api(C.urls.poll + (cursor ? '?cursor=' + encodeURIComponent(cursor) : '')).then(function (res) {
            if (!res.ok || !res.json) return;
            store('oepush.cursor', res.json.cursor);
            setBadge(res.json.unread);

            if (state === 'on' || !(res.json.items || []).length) return;

            if ((res.json.items || []).some(function (i) { return i.sound; })) chime();
            refreshBell();

            if (registration && 'Notification' in window && Notification.permission === 'granted' && document.visibilityState !== 'visible') {
                res.json.items.forEach(function (i) {
                    registration.showNotification(i.title, { body: i.body, tag: i.topic, icon: '/admin-push/icon-192.png', data: { url: i.url || '/admin' } });
                });
            }
        }).catch(function () { /* offline — try again next tick */ });
    }

    function whenActive(reg) {
        if (reg.active) return Promise.resolve(reg);
        var worker = reg.installing || reg.waiting;
        if (!worker) return Promise.reject(new Error('no service worker'));
        return new Promise(function (resolve, reject) {
            worker.addEventListener('statechange', function () {
                if (worker.state === 'activated') resolve(reg);
                else if (worker.state === 'redundant') reject(new Error('service worker failed to install'));
            });
        });
    }

    /* ── Boot ──────────────────────────────────────────────────────────── */
    function boot() {
        render();

        if (!('serviceWorker' in navigator)) { evaluate(); return; }

        navigator.serviceWorker.addEventListener('message', onWorkerMessage);

        // Not navigator.serviceWorker.ready: that only resolves for pages inside the
        // registration's scope and would hang on any URL outside it.
        navigator.serviceWorker.register(C.urls.sw, { scope: '/admin' })
            .then(whenActive)
            .then(function (reg) { registration = reg; return evaluate(); })
            .catch(function () { state = 'unsupported'; render(); });

        poll();
        setInterval(poll, 25000);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') poll(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
