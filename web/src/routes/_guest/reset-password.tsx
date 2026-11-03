import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { z } from 'zod'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api/client'
import { handleFormError } from '@/lib/forms'

export const Route = createFileRoute('/_guest/reset-password')({
  validateSearch: z.object({ token: z.string().catch(''), email: z.string().catch('') }),
  component: ResetPassword,
})

const schema = z
  .object({ email: z.email(), password: z.string().min(8), password_confirmation: z.string() })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match',
  })

function ResetPassword() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { token, email } = Route.useSearch()
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: { email, password: '', password_confirmation: '' },
  })

  const reset = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => api.post('/auth/reset-password', { ...values, token }),
    onSuccess: async () => {
      toast.success(t('Your password was changed. You can sign in now.'))
      await navigate({ to: '/login' })
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Choose a new password')}</CardTitle>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => reset.mutate(values))}>
        <CardContent>
          <FieldGroup>
            <FormField control={form.control} name="email" required label={t('Email')}>
              {(field) => <Input type="email" autoComplete="email" {...field} />}
            </FormField>
            <FormField control={form.control} name="password" required label={t('New password')}>
              {(field) => <Input type="password" autoComplete="new-password" autoFocus {...field} />}
            </FormField>
            <FormField control={form.control} name="password_confirmation" required label={t('Confirm password')}>
              {(field) => <Input type="password" autoComplete="new-password" {...field} />}
            </FormField>
          </FieldGroup>
        </CardContent>
        <CardFooter className="mt-6">
          <Button type="submit" className="w-full" disabled={reset.isPending}>
            {t('Change password')}
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}
