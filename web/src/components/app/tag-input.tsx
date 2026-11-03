import { XIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'

type TagInputProps = {
  id?: string
  value: string[]
  onChange: (value: string[]) => void
  suggestions?: string[]
  className?: string
}

export function TagInput({ id, value, onChange, suggestions = [], className }: TagInputProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState('')
  const listId = id ? `${id}-suggestions` : undefined

  const add = (name: string) => {
    const tag = name.trim()

    if (tag && !value.includes(tag)) onChange([...value, tag])
    setDraft('')
  }

  return (
    <div
      className={cn(
        'border-input focus-within:border-ring focus-within:ring-ring/50 flex min-h-9 w-full flex-wrap items-center gap-1 rounded-md border bg-transparent px-2 py-1 shadow-xs focus-within:ring-[3px] dark:bg-input/30',
        className,
      )}
    >
      {value.map((tag) => (
        <Badge key={tag} variant="secondary" className="gap-1">
          {tag}
          <button type="button" aria-label={t('Remove {{tag}}', { tag })} onClick={() => onChange(value.filter((item) => item !== tag))}>
            <XIcon className="size-3" />
          </button>
        </Badge>
      ))}
      <input
        id={id}
        list={listId}
        value={draft}
        placeholder={value.length === 0 ? t('Add tags…') : undefined}
        className="placeholder:text-muted-foreground min-w-24 flex-1 bg-transparent text-sm outline-none"
        onChange={(event) => setDraft(event.target.value)}
        onBlur={() => add(draft)}
        onKeyDown={(event) => {
          if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault()
            add(draft)
          } else if (event.key === 'Backspace' && draft === '' && value.length > 0) {
            onChange(value.slice(0, -1))
          }
        }}
      />
      {listId && (
        <datalist id={listId}>
          {suggestions
            .filter((name) => !value.includes(name))
            .map((name) => (
              <option key={name} value={name} />
            ))}
        </datalist>
      )}
    </div>
  )
}
