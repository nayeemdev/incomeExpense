import type { ReactNode } from 'react'
import { Controller, type Control, type FieldPath, type FieldValues } from 'react-hook-form'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { cn } from '@/lib/utils'
import { RequiredMark } from './required-mark'

export type FieldProps = {
  id: string
  name: string
  value: any
  onChange: (value: unknown) => void
  onBlur: () => void
  'aria-invalid': boolean
}

type FormFieldProps<T extends FieldValues> = {
  control: Control<T>
  name: FieldPath<T>
  label?: ReactNode
  description?: ReactNode
  className?: string
  orientation?: 'vertical' | 'horizontal'
  required?: boolean
  children: (field: FieldProps) => ReactNode
}

export function FormField<T extends FieldValues>({
  control,
  name,
  label,
  description,
  className,
  orientation,
  required,
  children,
}: FormFieldProps<T>) {
  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => (
        <Field
          data-invalid={fieldState.invalid}
          className={cn(orientation === 'horizontal' && 'rounded-lg border p-3', className)}
          orientation={orientation}
        >
          {label && (
            <FieldLabel htmlFor={name}>
              {label}
              {required && <RequiredMark />}
            </FieldLabel>
          )}
          {children({
            id: name,
            name: field.name,
            value: field.value ?? '',
            onChange: field.onChange,
            onBlur: field.onBlur,
            'aria-invalid': fieldState.invalid,
          })}
          {description && <FieldDescription>{description}</FieldDescription>}
          {fieldState.error && <FieldError errors={[fieldState.error]} />}
        </Field>
      )}
    />
  )
}
