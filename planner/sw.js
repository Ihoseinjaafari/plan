const CACHE_NAME = 'task-planner-v1';
const DATA_CACHE_NAME = 'tasks-data-v1';

self.addEventListener('install', event => {
  console.log('Service Worker installed.');
  self.skipWaiting();
});

self.addEventListener('fetch', event => {
  if (event.request.url.includes('/tasks.json')) {
    event.respondWith(
      caches.open(DATA_CACHE_NAME).then(cache => {
        return fetch(event.request)
          .then(response => {
            cache.put(event.request, response.clone());
            return response;
          })
          .catch(() => cache.match(event.request));
      })
    );
  } else {
    event.respondWith(
      caches.match(event.request).then(response => {
        return response || fetch(event.request);
      })
    );
  }
});