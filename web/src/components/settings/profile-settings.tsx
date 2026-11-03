import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
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
import { userQuery } from '@/lib/api/queries'
import { handleFormError } from '@/lib/forms'

const profileSchema = z.object({ name: z.string().trim().min(1).max(255), email: z.email() })
const passwordSchema = z
  .object({ current_password: z.string().min(1), password: z.string().min(8), password_confirmation: z.string() })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match',
  })

export function ProfileSettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: user } = useQuery(userQuery)
  const profile = useForm({ resolver: zodResolver(profileSchema), values: { name: user?.name ?? '', email: user?.email ?? '' } })
  const password = useForm({
    resolver: zodResolver(passwordSchema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  })

  const saveProfile = useMutation({
    mutationFn: (values: z.infer<typeof profileSchema>) => api.put('/auth/user/profile-information', values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      toast.success(t('Profile updated'))
    },
    onError: (error) => handleFormError(error, profile.setError),
  })

  const savePassword = useMutation({
    mutationFn: (values: z.infer<typeof passwordSchema>) => api.put('/auth/user/password', values),
    onSuccess: () => {
      password.reset()
      toast.success(t('Password changed'))
    },
    onError: (error) => handleFormError(error, password.setError),
  })

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('Profile')}</CardTitle>
        </CardHeader>
        <form onSubmit={profile.handleSubmit((values) => saveProfile.mutate(values))}>
          <CardContent>
            <FieldGroup>
              <FormField control={profile.control} name="name" label={t('Name')}>
                {(field) => <Input autoComplete="name" {...field} />}
              </FormField>
              <FormField control={profile.control} name="email" label={t('Email')}>
                {(field) => <Input type="email" autoComplete="email" {...field} />}
              </FormField>
            </FieldGroup>
          </CardContent>
          <CardFooter className="mt-6 justify-end">
            <Button type="submit" disabled={saveProfile.isPending}>
              {t('Save changes')}
            </Button>
          </CardFooter>
        </form>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Password')}</CardTitle>
        </CardHeader>
        <form onSubmit={password.handleSubmit((values) => savePassword.mutate(values))}>
          <CardContent>
            <FieldGroup>
              <FormField control={password.control} name="current_password" label={t('Current password')}>
                {(field) => <Input type="password" autoComplete="current-password" {...field} />}
              </FormField>
              <FormField control={password.control} name="password" label={t('New password')}>
                {(field) => <Input type="password" autoComplete="new-password" {...field} />}
              </FormField>
              <FormField control={password.control} name="password_confirmation" label={t('Confirm new password')}>
                {(field) => <Input type="password" autoComplete="new-password" {...field} />}
              </FormField>
            </FieldGroup>
          </CardContent>
          <CardFooter className="mt-6 justify-end">
            <Button type="submit" disabled={savePassword.isPending}>
              {t('Change password')}
            </Button>
          </CardFooter>
        </form>
      </Card>
    </div>
  )
}
