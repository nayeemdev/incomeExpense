import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

type SectionCardProps = {
  title?: ReactNode
  description?: ReactNode
  actions?: ReactNode
  children: ReactNode
  footer?: ReactNode
  className?: string
  contentClassName?: string
}

export function SectionCard({ title, description, actions, children, footer, className, contentClassName }: SectionCardProps) {
  return (
    <section className={cn('surface-card', className)}>
      {(title || actions) && (
        <div className="flex flex-wrap items-start justify-between gap-3 border-b px-5 py-4">
          <div className="min-w-0">
            {title && <h2 className="text-base font-semibold">{title}</h2>}
            {description && <p className="text-muted-foreground mt-0.5 text-sm">{description}</p>}
          </div>
          {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
      )}
      <div className={cn('p-5', contentClassName)}>{children}</div>
      {footer && <div className="bg-muted/30 flex items-center justify-end gap-2 border-t px-5 py-3">{footer}</div>}
    </section>
  )
}
