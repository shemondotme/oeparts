/* OeParts admin service worker — installable app shell + Web Push.
 * Served from /admin/sw.js (scope /admin/). Keep this file dependency-free. */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

// A fetch handler is required for the browser to treat this as an installable app.
// It deliberately does nothing: the admin panel is always served live, never cached.
self.addEventListener('fetch', () => {});

function setBadge(count) {
    try {
        const nav = self.navigator;
        if (typeof count !== 'number' || !nav) return;
        if (count > 0 && nav.setAppBadge) nav.setAppBadge(count);
        else if (count <= 0 && nav.clearAppBadge) nav.clearAppBadge();
    } catch (e) { /* badge is a nicety */ }
}

async function handlePush(event) {
    let payload = {};
    try { payload = event.data ? event.data.json() : {}; } catch (e) { payload = { title: 'New notification', body: event.data ? event.data.text() : '' }; }

    const tag = payload.tag || payload.topic || 'oeparts-admin';
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const visible = windows.some((c) => c.visibilityState === 'visible');

    // Let any open admin page play its own chime and refresh the bell.
    windows.forEach((c) => c.postMessage({ type: 'push', payload }));

    // Several alerts of one kind collapse into a single "N new" notification
    // instead of stacking up (busy shop = dozens of orders).
    const existing = await self.registration.getNotifications({ tag });
    const previous = existing.reduce((sum, n) => sum + ((n.data && n.data.count) || 1), 0);
    const count = previous + 1;

    const grouped = count > 1;
    const title = grouped ? count + ' × ' + (payload.topicLabel || payload.title) : payload.title;
    const body = grouped ? payload.title + (payload.body ? ' — ' + payload.body : '') : (payload.body || '');

    const actions = [{ action: 'open', title: 'View' }];
    if (!grouped && payload.readUrl) actions.push({ action: 'read', title: 'Mark as read' });

    setBadge(payload.badge);

    const options = {
        body,
        tag,
        renotify: true,
        icon: payload.icon || '/admin-push/icon-192.png',
        badge: '/admin-push/icon-192.png',
        data: { url: payload.url || '/admin', readUrl: payload.readUrl || null, count, topic: payload.topic || null },
        actions,
        requireInteraction: !!payload.urgent,
        // The page already plays its own chime when it is on screen; don't double up.
        silent: visible,
    };

    // Browsers throw a TypeError if a silent notification also specifies vibration —
    // which would drop the notification entirely — so only vibrate when not silent.
    if (!visible) options.vibrate = payload.urgent ? [200, 100, 200, 100, 200] : [120];

    try {
        await self.registration.showNotification(title, options);
    } catch (e) {
        // Some platforms reject `actions` or other optional fields; a plain
        // notification is far better than none.
        delete options.actions;
        delete options.vibrate;
        await self.registration.showNotification(title, options);
    }
}

self.addEventListener('push', (event) => event.waitUntil(handlePush(event)));

self.addEventListener('notificationclick', (event) => {
    const data = event.notification.data || {};
    const tag = event.notification.tag;

    event.notification.close();

    event.waitUntil((async () => {
        if (event.action === 'read' && data.readUrl) {
            try {
                const res = await fetch(data.readUrl, { method: 'POST', headers: { Accept: 'application/json' } });
                const json = await res.json();
                setBadge(json.unread);
            } catch (e) { /* offline — the item stays unread, nothing lost */ }
            return;
        }

        // Opening the app counts as handling the whole group.
        (await self.registration.getNotifications({ tag })).forEach((n) => n.close());

        const target = new URL(data.url || '/admin', self.location.origin).href;
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const existing = windows.find((c) => c.url.startsWith(self.location.origin + '/admin'));

        if (existing) {
            await existing.focus();
            if ('navigate' in existing) {
                try { await existing.navigate(target); return; } catch (e) { /* fall through */ }
            }
            existing.postMessage({ type: 'navigate', url: target });
            return;
        }

        await self.clients.openWindow(target);
    })());
});

// The browser rotated/expired the subscription: ask an open page to re-register it.
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then((windows) => windows.forEach((c) => c.postMessage({ type: 'resubscribe' }))));
});
