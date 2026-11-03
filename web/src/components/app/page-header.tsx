import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

type PageHeaderProps = { title: ReactNode; description?: ReactNode; actions?: ReactNode; className?: string }

export function PageHeader({ title, description, actions, className }: PageHeaderProps) {
  return (
    <header className={cn('mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between', className)}>
      <div className="min-w-0">
        <h1 className="text-ink dark:text-foreground truncate text-[1.75rem] leading-tight font-bold tracking-tight sm:text-[2rem]">{title}</h1>
        {description && <div className="text-muted-foreground mt-1.5 max-w-2xl text-[0.9375rem]">{description}</div>}
      </div>
      {actions && <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>}
    </header>
  )
}
