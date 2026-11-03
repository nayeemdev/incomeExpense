import { useEffect } from 'react'

function isTyping(target: EventTarget | null) {
  return (
    target instanceof HTMLElement &&
    (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
  )
}

/**
 * Binds a single-key or modifier shortcut. Plain keys are ignored while the user is typing.
 */
export function useHotkey(key: string, handler: (event: KeyboardEvent) => void, options: { meta?: boolean } = {}) {
  useEffect(() => {
    const listener = (event: KeyboardEvent) => {
      if (event.key.toLowerCase() !== key) return

      if (options.meta) {
        if (!(event.metaKey || event.ctrlKey)) return
      } else if (event.metaKey || event.ctrlKey || event.altKey || isTyping(event.target)) {
        return
      }

      event.preventDefault()
      handler(event)
    }

    window.addEventListener('keydown', listener)

    return () => window.removeEventListener('keydown', listener)
  }, [key, handler, options.meta])
}
