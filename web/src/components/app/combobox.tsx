import { CheckIcon, ChevronDownIcon, PlusIcon } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

export type ComboboxOption = {
  value: string
  label: string
  hint?: ReactNode
  group?: string
  keywords?: string[]
}

type ComboboxProps = {
  id?: string
  options: ComboboxOption[]
  value: string | null
  onChange: (value: string | null, option?: ComboboxOption) => void
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  onCreate?: (label: string) => void
  createLabel?: (label: string) => string
  displayValue?: string
  disabled?: boolean
  className?: string
  'aria-invalid'?: boolean
}

export function Combobox({
  id,
  options,
  value,
  onChange,
  placeholder,
  searchPlaceholder,
  emptyText,
  onCreate,
  createLabel,
  displayValue,
  disabled,
  className,
  ...props
}: ComboboxProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const selected = options.find((option) => option.value === value)
  const groups = [...new Set(options.map((option) => option.group ?? ''))]
  const exactMatch = options.some((option) => option.label.toLowerCase() === search.trim().toLowerCase())

  const close = () => {
    setOpen(false)
    setSearch('')
  }

  return (
    <Popover open={open} onOpenChange={(next) => (next ? setOpen(true) : close())}>
      <PopoverTrigger asChild>
        <button
          id={id}
          type="button"
          role="combobox"
          aria-expanded={open}
          aria-invalid={props['aria-invalid']}
          disabled={disabled}
          className={cn('field-control flex items-center justify-between gap-2 text-left', !selected && !displayValue && 'text-muted-foreground', className)}
        >
          <span className="truncate">{selected?.label ?? displayValue ?? placeholder ?? t('Select…')}</span>
          <ChevronDownIcon className="text-muted-foreground size-4 shrink-0" />
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-(--radix-popover-trigger-width) min-w-64 p-0" align="start">
        <Command>
          <CommandInput placeholder={searchPlaceholder ?? t('Search…')} value={search} onValueChange={setSearch} />
          <CommandList>
            <CommandEmpty>{emptyText ?? t('Nothing found.')}</CommandEmpty>
            {groups.map((group) => (
              <CommandGroup key={group} heading={group || undefined}>
                {options
                  .filter((option) => (option.group ?? '') === group)
                  .map((option) => (
                    <CommandItem
                      key={option.value}
                      value={`${option.label} ${option.value}`}
                      keywords={option.keywords}
                      onSelect={() => {
                        onChange(option.value === value ? null : option.value, option)
                        close()
                      }}
                    >
                      <CheckIcon className={cn(option.value === value ? 'opacity-100' : 'opacity-0')} />
                      <span className="truncate">{option.label}</span>
                      {option.hint && <span className="text-muted-foreground ml-auto text-xs">{option.hint}</span>}
                    </CommandItem>
                  ))}
              </CommandGroup>
            ))}
            {onCreate && search.trim() !== '' && !exactMatch && (
              <CommandGroup forceMount>
                <CommandItem
                  forceMount
                  value={`create ${search}`}
                  onSelect={() => {
                    onCreate(search.trim())
                    close()
                  }}
                >
                  <PlusIcon />
                  {createLabel ? createLabel(search.trim()) : t('Create "{{name}}"', { name: search.trim() })}
                </CommandItem>
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
