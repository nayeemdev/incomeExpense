import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation } from '@tanstack/react-query'
import { Link, createFileRoute } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api/client'
import { handleFormError } from '@/lib/forms'

export const Route = createFileRoute('/_guest/forgot-password')({
  component: ForgotPassword,
})

const schema = z.object({ email: z.email() })

function ForgotPassword() {
  const { t } = useTranslation()
  const form = useForm({ resolver: zodResolver(schema), defaultValues: { email: '' } })

  const send = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => api.post<{ message: string }>('/auth/forgot-password', values),
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Reset your password')}</CardTitle>
        <CardDescription>
          {send.isSuccess ? send.data.message : t('We will email you a link to choose a new password.')}
        </CardDescription>
      </CardHeader>
      {!send.isSuccess && (
        <form onSubmit={form.handleSubmit((values) => send.mutate(values))}>
          <CardContent>
            <FormField control={form.control} name="email" label={t('Email')}>
              {(field) => <Input type="email" autoComplete="email" autoFocus {...field} />}
            </FormField>
          </CardContent>
          <CardFooter className="mt-6 flex-col gap-3">
            <Button type="submit" className="w-full" disabled={send.isPending}>
              {t('Send reset link')}
            </Button>
          </CardFooter>
        </form>
      )}
      <CardFooter className="justify-center">
        <Link to="/login" className="text-muted-foreground text-sm hover:underline">
          {t('Back to sign in')}
        </Link>
      </CardFooter>
    </Card>
  )
}
