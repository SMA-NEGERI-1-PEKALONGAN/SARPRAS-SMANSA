self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', event => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch (error) {
        data = {
            title: 'Notifikasi',
            message: event.data?.text() || 'Anda memiliki notifikasi baru.',
            url: '/'
        };
    }

    const title = data.title || 'Notifikasi Sarpras SMANSA';

    const options = {
        body: data.message || data.body || 'Anda memiliki notifikasi baru.',
        icon: data.icon || '/img/icons/icon-192x192.png',
        badge: data.badge || '/img/icons/icon-192x192.png',
        data: {
            url: data.url || data.data?.url || '/'
        },
        vibrate: [200, 100, 200],
        requireInteraction: false,
        tag: data.tag || 'sarpras-notification'
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    const targetUrl =
        event.notification?.data?.url || '/';

    event.waitUntil(
        clients.matchAll({
            type: 'window',
            includeUncontrolled: true
        }).then(clientList => {
            for (const client of clientList) {
                if (
                    'focus' in client &&
                    new URL(client.url).origin === self.location.origin
                ) {
                    return client.focus().then(() => {
                        if ('navigate' in client) {
                            return client.navigate(targetUrl);
                        }
                    });
                }
            }

            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

self.addEventListener('pushsubscriptionchange', event => {
    event.waitUntil(
        Promise.resolve()
    );
});