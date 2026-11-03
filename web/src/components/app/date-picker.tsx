import { parseISO } from 'date-fns'
import { CalendarIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Calendar } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useLocale } from '@/hooks/use-ledger'
import { formatDate, toIsoDate } from '@/lib/format'
import { cn } from '@/lib/utils'

type DatePickerProps = {
  id?: string
  value: string | null
  onChange: (value: string | null) => void
  placeholder?: string
  className?: string
  'aria-invalid'?: boolean
}

export function DatePicker({ id, value, onChange, placeholder, className, ...props }: DatePickerProps) {
  const { t } = useTranslation()
  const locale = useLocale()
  const [open, setOpen] = useState(false)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          id={id}
          type="button"
          aria-invalid={props['aria-invalid']}
          className={cn('field-control flex items-center gap-2 text-left', !value && 'text-muted-foreground', className)}
        >
          <CalendarIcon className="text-muted-foreground size-4 shrink-0" />
          <span className="truncate">{value ? formatDate(value, locale) : (placeholder ?? t('Pick a date'))}</span>
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-auto p-0" align="start">
        <Calendar
          mode="single"
          selected={value ? parseISO(value) : undefined}
          defaultMonth={value ? parseISO(value) : undefined}
          onSelect={(date) => {
            onChange(date ? toIsoDate(date) : null)
            setOpen(false)
          }}
          autoFocus
        />
      </PopoverContent>
    </Popover>
  )
}
