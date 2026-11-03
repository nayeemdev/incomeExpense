import type { LucideIcon } from 'lucide-react'
import { createElement } from 'react'
import { categoryColor, categoryIcon } from '@/lib/category-style'
import { cn } from '@/lib/utils'

type CategoryIconProps = { icon?: string | null; color?: string | null; glyph?: LucideIcon; className?: string }

/**
 * A rounded tile with a category's icon on a soft wash of its color, matching the mobile app.
 */
export function CategoryIcon({ icon, color, glyph, className }: CategoryIconProps) {
  const tint = categoryColor(color)

  return (
    <span
      className={cn('grid size-8 shrink-0 place-items-center rounded-[10px]', className)}
      style={{ backgroundColor: `color-mix(in srgb, ${tint} 14%, transparent)`, color: tint }}
      aria-hidden
    >
      {createElement(glyph ?? categoryIcon(icon), { className: 'size-[55%]' })}
    </span>
  )
}
