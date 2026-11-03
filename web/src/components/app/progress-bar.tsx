import { cn } from '@/lib/utils'

export function ProgressBar({ value, max, className }: { value: number; max: number; className?: string }) {
  const ratio = max > 0 ? value / max : 0
  const over = ratio > 1

  return (
    <div className={cn('bg-muted h-2 overflow-hidden rounded-full', className)} role="progressbar" aria-valuenow={Math.round(ratio * 100)} aria-valuemin={0} aria-valuemax={100}>
      <div
        className={cn('h-full rounded-full transition-all', over ? 'bg-destructive' : ratio > 0.85 ? 'bg-amber-500' : 'bg-primary')}
        style={{ width: `${Math.min(ratio, 1) * 100}%` }}
      />
    </div>
  )
}
