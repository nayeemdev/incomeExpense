const PREFIX = 'ledgerfly.'

export const storage = {
  get(key: string) {
    try {
      return window.localStorage.getItem(PREFIX + key)
    } catch {
      return null
    }
  },
  set(key: string, value: string) {
    try {
      window.localStorage.setItem(PREFIX + key, value)
    } catch {
      // Storage can be unavailable (private mode); remembering is only a convenience.
    }
  },
}
