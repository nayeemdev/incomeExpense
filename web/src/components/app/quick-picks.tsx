import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

type QuickPick = { key: string; label: ReactNode; hint?: ReactNode; active?: boolean }

type QuickPicksProps = { items: QuickPick[]; onPick: (key: string) => void; label?: string; className?: string }

/**
 * A row of one-tap choices under a field, so frequent values never need typing.
 */
export function QuickPicks({ items, onPick, label, className }: QuickPicksProps) {
  if (items.length === 0) return null

  return (
    <div role="group" aria-label={label} className={cn('-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none]', className)}>
      {items.map((item) => (
        <button
          key={item.key}
          type="button"
          aria-pressed={item.active}
          onClick={() => onPick(item.key)}
          className={cn(
            'border-input hover:bg-accent hover:text-accent-foreground focus-visible:ring-ring/50 inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-xs whitespace-nowrap transition-colors outline-none focus-visible:ring-[3px]',
            item.active && 'border-primary bg-primary text-primary-foreground hover:bg-primary/90 hover:text-primary-foreground',
          )}
        >
          <span>{item.label}</span>
          {item.hint && <span className={cn('tabular-nums', item.active ? 'opacity-80' : 'text-muted-foreground')}>{item.hint}</span>}
        </button>
      ))}
    </div>
  )
}
