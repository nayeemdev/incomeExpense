import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api/client'
import { userQuery } from '@/lib/api/queries'
import { handleFormError } from '@/lib/forms'

export const Route = createFileRoute('/_guest/two-factor-challenge')({
  validateSearch: z.object({ redirect: z.string().optional() }),
  component: TwoFactorChallenge,
})

function TwoFactorChallenge() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { redirect } = Route.useSearch()
  const [useRecovery, setUseRecovery] = useState(false)
  const form = useForm({ defaultValues: { code: '', recovery_code: '' } })

  const verify = useMutation({
    mutationFn: (values: { code: string; recovery_code: string }) =>
      api.post('/auth/two-factor-challenge', useRecovery ? { recovery_code: values.recovery_code } : { code: values.code }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      await navigate({ to: redirect ?? '/' })
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Two-factor authentication')}</CardTitle>
        <CardDescription>
          {useRecovery
            ? t('Enter one of your recovery codes.')
            : t('Enter the 6-digit code from your authenticator app.')}
        </CardDescription>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => verify.mutate(values))}>
        <CardContent>
          {useRecovery ? (
            <FormField control={form.control} name="recovery_code" label={t('Recovery code')}>
              {(field) => <Input autoComplete="one-time-code" autoFocus {...field} />}
            </FormField>
          ) : (
            <FormField control={form.control} name="code" label={t('Code')}>
              {(field) => <Input inputMode="numeric" autoComplete="one-time-code" maxLength={6} autoFocus {...field} />}
            </FormField>
          )}
        </CardContent>
        <CardFooter className="mt-6 flex-col gap-3">
          <Button type="submit" className="w-full" disabled={verify.isPending}>
            {t('Verify')}
          </Button>
          <Button type="button" variant="link" size="sm" onClick={() => setUseRecovery((value) => !value)}>
            {useRecovery ? t('Use an authenticator code') : t('Use a recovery code')}
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}
