import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react'
import { storage } from '@/lib/storage'

export type Theme = 'system' | 'light' | 'dark'

type ThemeContextValue = { theme: Theme; resolvedTheme: 'light' | 'dark'; setTheme: (theme: Theme) => void }

const ThemeContext = createContext<ThemeContextValue | null>(null)
const query = '(prefers-color-scheme: dark)'

function systemTheme(): 'light' | 'dark' {
  return window.matchMedia(query).matches ? 'dark' : 'light'
}

export function ThemeProvider({ children }: { children: ReactNode }) {
  const [theme, setThemeState] = useState<Theme>(() => (storage.get('theme') as Theme | null) ?? 'system')
  const [system, setSystem] = useState(systemTheme)
  const resolvedTheme = theme === 'system' ? system : theme

  useEffect(() => {
    const media = window.matchMedia(query)
    const listener = () => setSystem(systemTheme())

    media.addEventListener('change', listener)

    return () => media.removeEventListener('change', listener)
  }, [])

  useEffect(() => {
    document.documentElement.classList.toggle('dark', resolvedTheme === 'dark')
    document.documentElement.style.colorScheme = resolvedTheme
  }, [resolvedTheme])

  const setTheme = useCallback((next: Theme) => {
    storage.set('theme', next)
    setThemeState(next)
  }, [])

  return <ThemeContext.Provider value={{ theme, resolvedTheme, setTheme }}>{children}</ThemeContext.Provider>
}

export function useTheme() {
  const context = useContext(ThemeContext)

  if (!context) throw new Error('useTheme must be used inside ThemeProvider')

  return context
}
