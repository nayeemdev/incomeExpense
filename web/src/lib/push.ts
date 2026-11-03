import { api } from './api/client'

function decodeKey(base64: string) {
  const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(padded)

  return Uint8Array.from(raw, (character) => character.charCodeAt(0))
}

export function pushSupported() {
  return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
}

export async function currentSubscription() {
  if (!pushSupported()) return null

  const registration = await navigator.serviceWorker.getRegistration('/sw.js')

  return (await registration?.pushManager.getSubscription()) ?? null
}

export async function enablePush(publicKey: string) {
  const permission = await Notification.requestPermission()

  if (permission !== 'granted') throw new Error('Notifications are blocked for this site in your browser settings.')

  await navigator.serviceWorker.register('/sw.js')
  const registration = await navigator.serviceWorker.ready
  const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: decodeKey(publicKey) })
  const json = subscription.toJSON()

  await api.post('/push-subscriptions', { endpoint: json.endpoint, keys: json.keys, content_encoding: PushManager.supportedContentEncodings?.[0] })
}

export async function disablePush() {
  const subscription = await currentSubscription()

  if (!subscription) return

  await api.delete(`/push-subscriptions?endpoint=${encodeURIComponent(subscription.endpoint)}`)
  await subscription.unsubscribe()
}
