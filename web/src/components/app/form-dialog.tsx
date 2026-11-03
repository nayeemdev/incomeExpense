import type { ReactNode } from 'react'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'

const sizes = { md: 'sm:max-w-md', lg: 'sm:max-w-lg', xl: 'sm:max-w-2xl' }

type FormDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  title: ReactNode
  description?: ReactNode
  size?: keyof typeof sizes
  children: ReactNode
}

/**
 * The one container every create and edit form opens in, so forms look and behave the same everywhere.
 */
export function FormDialog({ open, onOpenChange, title, description, size = 'lg', children }: FormDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className={sizes[size]}>
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          {description && <DialogDescription>{description}</DialogDescription>}
        </DialogHeader>
        {children}
      </DialogContent>
    </Dialog>
  )
}
