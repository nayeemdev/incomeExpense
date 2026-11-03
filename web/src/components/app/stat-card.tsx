import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

const tones = {
  default: 'bg-muted text-foreground',
  brand: 'bg-brand-50 text-brand-700 dark:bg-brand-900/40 dark:text-brand-200',
  success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  warning: 'bg-amber-50 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  danger: 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
}

type StatCardProps = {
  label: ReactNode
  value: ReactNode
  hint?: ReactNode
  icon?: LucideIcon
  tone?: keyof typeof tones
  action?: ReactNode
  className?: string
}

export function StatCard({ label, value, hint, icon: Icon, tone = 'default', action, className }: StatCardProps) {
  return (
    <div className={cn('surface-card relative p-5', className)}>
      <div className="flex items-start justify-between gap-3">
        <p className="text-muted-foreground text-sm font-medium">{label}</p>
        {action ??
          (Icon && (
            <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-lg', tones[tone])}>
              <Icon className="size-[18px]" aria-hidden />
            </span>
          ))}
      </div>
      <div className="mt-2 text-[1.75rem] leading-none font-bold tracking-tight tabular-nums">{value}</div>
      {hint && <div className="text-muted-foreground mt-2 text-xs">{hint}</div>}
    </div>
  )
}
