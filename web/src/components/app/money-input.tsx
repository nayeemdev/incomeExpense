import type { ComponentProps } from 'react'
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from '@/components/ui/input-group'

type MoneyInputProps = Omit<ComponentProps<'input'>, 'onChange' | 'value'> & {
  value: string
  onChange: (value: string) => void
  currency?: string | null
  allowNegative?: boolean
}

export function MoneyInput({ value, onChange, currency, allowNegative = false, className, ...props }: MoneyInputProps) {
  const pattern = allowNegative ? /[^\d.-]/g : /[^\d.]/g

  return (
    <InputGroup className={className}>
      <InputGroupInput
        {...props}
        inputMode="decimal"
        autoComplete="off"
        className="tabular-nums"
        value={value}
        onChange={(event) => onChange(event.target.value.replace(',', '.').replace(pattern, ''))}
      />
      {currency && (
        <InputGroupAddon align="inline-end">
          <InputGroupText>{currency}</InputGroupText>
        </InputGroupAddon>
      )}
    </InputGroup>
  )
}
