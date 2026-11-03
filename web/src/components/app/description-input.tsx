import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Input } from '@/components/ui/input'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { useLocale } from '@/hooks/use-ledger'
import { suggestionsQuery } from '@/lib/api/queries'
import type { Transaction } from '@/lib/api/types'
import { formatMoney } from '@/lib/format'
import { cn } from '@/lib/utils'

type DescriptionInputProps = {
  id?: string
  ledgerId: number
  value: string
  onChange: (value: string) => void
  onPick: (transaction: Transaction) => void
  placeholder?: string
  autoFocus?: boolean
  'aria-invalid'?: boolean
}

export function DescriptionInput({ id, ledgerId, value, onChange, onPick, placeholder, autoFocus, ...props }: DescriptionInputProps) {
  const locale = useLocale()
  const [focused, setFocused] = useState(false)
  const [active, setActive] = useState(0)
  const search = useDebouncedValue(value)
  const { data: suggestions = [] } = useQuery({ ...suggestionsQuery(ledgerId, search), enabled: focused && search.trim().length > 1 })
  const visible = focused ? suggestions.filter((item) => item.description !== value || suggestions.length > 1) : []
  const listId = id ? `${id}-list` : undefined

  const pick = (transaction: Transaction) => {
    onPick(transaction)
    setFocused(false)
  }

  return (
    <div className="relative">
      <Input
        id={id}
        value={value}
        placeholder={placeholder}
        autoFocus={autoFocus}
        autoComplete="off"
        role="combobox"
        aria-expanded={visible.length > 0}
        aria-controls={listId}
        aria-invalid={props['aria-invalid']}
        onChange={(event) => {
          onChange(event.target.value)
          setActive(0)
          setFocused(true)
        }}
        onFocus={() => setFocused(true)}
        onBlur={() => setTimeout(() => setFocused(false), 150)}
        onKeyDown={(event) => {
          if (visible.length === 0) return

          if (event.key === 'ArrowDown') {
            event.preventDefault()
            setActive((index) => (index + 1) % visible.length)
          } else if (event.key === 'ArrowUp') {
            event.preventDefault()
            setActive((index) => (index - 1 + visible.length) % visible.length)
          } else if (event.key === 'Enter' && visible[active]) {
            event.preventDefault()
            pick(visible[active])
          } else if (event.key === 'Escape') {
            setFocused(false)
          }
        }}
      />
      {visible.length > 0 && (
        <ul
          id={listId}
          role="listbox"
          className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border p-1 shadow-md"
        >
          {visible.map((transaction, index) => (
            <li
              key={transaction.id}
              role="option"
              aria-selected={index === active}
              className={cn(
                'flex cursor-pointer items-center justify-between gap-3 rounded-sm px-2 py-1.5 text-sm',
                index === active && 'bg-accent text-accent-foreground',
              )}
              onMouseEnter={() => setActive(index)}
              onMouseDown={(event) => {
                event.preventDefault()
                pick(transaction)
              }}
            >
              <span className="min-w-0">
                <span className="block truncate">{transaction.description}</span>
                <span className="text-muted-foreground block truncate text-xs">
                  {transaction.source.name} → {transaction.destination.name}
                  {transaction.category ? ` · ${transaction.category.name}` : ''}
                </span>
              </span>
              <span className="text-muted-foreground shrink-0 text-xs tabular-nums">
                {formatMoney(transaction.amount, transaction.currency.code, locale)}
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
