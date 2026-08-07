/*
 * Service Worker für die Einkaufsansicht.
 *
 * Der Zweck ist eng gefasst und soll es bleiben: Im Supermarkt ist das Netz
 * schlecht, und wer schon vor dem Regal steht, will wenigstens *sehen*, was auf
 * der Liste steht. Abhaken braucht weiterhin eine Verbindung -- ein
 * Offline-Warteschlangen-Verfahren gehört zum Sync-Frontend (C-07, C-08) und
 * nicht in den Service Worker.
 */
const VERSION = 'v1';
const SCHALE = `wocheneinkauf-schale-${VERSION}`;
const SEITEN = `wocheneinkauf-seiten-${VERSION}`;

// Was ohne Netz auf jeden Fall da sein muss.
const SCHALEN_DATEIEN = [
    '/offline.html',
    '/symbole/icon-192.png',
    '/symbole/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SCHALE)
            .then((cache) => cache.addAll(SCHALEN_DATEIEN))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    // Alte Fassungen weg, sonst wächst der Speicher mit jedem Deployment.
    event.waitUntil(
        caches.keys()
            .then((namen) => Promise.all(
                namen.filter((name) => name !== SCHALE && name !== SEITEN)
                    .map((name) => caches.delete(name))
            ))
            .then(() => self.clients.claim())
    );
});

/*
 * Beim Abmelden fliegt alles raus. Ohne das bliebe die Einkaufsliste einer
 * Familie auf einem Gerät liegen, das anschließend jemand anderem gehört.
 */
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'abmelden') {
        event.waitUntil(caches.keys().then((namen) => Promise.all(namen.map((n) => caches.delete(n)))));
    }
});

self.addEventListener('fetch', (event) => {
    const anfrage = event.request;

    // Nur eigene GET-Anfragen. Alles andere -- Formulare, fremde Hosts --
    // geht unverändert ins Netz.
    if (anfrage.method !== 'GET' || new URL(anfrage.url).origin !== self.location.origin) {
        return;
    }

    if (anfrage.mode === 'navigate') {
        event.respondWith(seiteHolen(anfrage));

        return;
    }

    if (anfrage.url.includes('/symbole/') || anfrage.url.endsWith('.webmanifest')) {
        event.respondWith(zuerstAusDemSpeicher(anfrage));
    }
});

/**
 * Seiten: erst das Netz, dann der Speicher.
 *
 * Andersherum wäre schneller, aber eine Einkaufsliste von gestern ist
 * schlimmer als eine, die eine Sekunde braucht.
 */
async function seiteHolen(anfrage) {
    try {
        const antwort = await fetch(anfrage);

        // Nur die Einkaufsansicht wird aufbewahrt, und nur wenn sie wirklich
        // die Liste zeigt: Eine Weiterleitung zur Anmeldung im Speicher wäre
        // eine Falle für den nächsten Aufruf.
        if (antwort.ok && !antwort.redirected && anfrage.url.includes('/einkaufsliste/einkaufen')) {
            const kopie = antwort.clone();
            const cache = await caches.open(SEITEN);
            await cache.put(anfrage, kopie);
        }

        return antwort;
    } catch (fehler) {
        const gespeichert = await caches.match(anfrage, { ignoreSearch: true });

        return gespeichert || caches.match('/offline.html');
    }
}

async function zuerstAusDemSpeicher(anfrage) {
    const gespeichert = await caches.match(anfrage);

    if (gespeichert) {
        return gespeichert;
    }

    const antwort = await fetch(anfrage);
    const cache = await caches.open(SCHALE);
    cache.put(anfrage, antwort.clone());

    return antwort;
}
