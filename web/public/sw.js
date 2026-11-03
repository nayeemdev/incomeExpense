self.addEventListener('push', (event) => {
  const payload = event.data ? event.data.json() : {}

  event.waitUntil(
    self.registration.showNotification(payload.title || 'LedgerFly', {
      body: payload.body,
      icon: payload.icon || '/favicon.svg',
      tag: payload.tag,
      data: payload.data || {},
    }),
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const path = (event.notification.data && event.notification.data.path) || '/'

  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
      const existing = windows.find((client) => client.url.startsWith(self.location.origin))

      if (existing) {
        existing.navigate(path)

        return existing.focus()
      }

      return self.clients.openWindow(path)
    }),
  )
})
