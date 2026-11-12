// Service Worker der WebApp (#240): nur für die Installation. Er speichert bewusst nichts zwischen,
// alle Anfragen gehen wie bisher direkt an den Server (immer aktuelle Begriffe, keine veralteten Seiten).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);
