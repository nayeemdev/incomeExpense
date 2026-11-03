import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api/client'
import { authConfigQuery, userQuery } from '@/lib/api/queries'
import { handleFormError } from '@/lib/forms'

export const Route = createFileRoute('/_guest/register')({
  validateSearch: z.object({ redirect: z.string().optional() }),
  component: Register,
})

const schema = z
  .object({
    name: z.string().min(1).max(255),
    email: z.email(),
    password: z.string().min(8),
    password_confirmation: z.string(),
  })
  .refine((values) => values.password === values.password_confirmation, {
    path: ['password_confirmation'],
    message: 'Passwords do not match',
  })

function Register() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { redirect } = Route.useSearch()
  const { data: config } = useQuery(authConfigQuery)
  const invited = !!redirect?.startsWith('/invitations/')
  const form = useForm({
    resolver: zodResolver(schema),
    defaultValues: { name: '', email: '', password: '', password_confirmation: '' },
  })

  const register = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => api.post('/auth/register', values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      await navigate({ to: redirect ?? '/onboarding' })
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  if (config && !config.registration_open && !invited) {
    return (
      <Card>
        <CardHeader>
          <CardTitle className="text-xl">{t('Sign-up is closed')}</CardTitle>
          <CardDescription>{t('This LedgerFly is private. Ask its owner to invite you, then open the link in the invitation email.')}</CardDescription>
        </CardHeader>
        <CardFooter>
          <Button asChild variant="outline" className="w-full">
            <Link to="/login">{t('Back to sign in')}</Link>
          </Button>
        </CardFooter>
      </Card>
    )
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Create your account')}</CardTitle>
        <CardDescription>{t('Start tracking your money in a minute')}</CardDescription>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => register.mutate(values))}>
        <CardContent>
          <FieldGroup>
            <FormField control={form.control} name="name" required label={t('Name')}>
              {(field) => <Input autoComplete="name" autoFocus {...field} />}
            </FormField>
            <FormField control={form.control} name="email" required label={t('Email')}>
              {(field) => <Input type="email" autoComplete="email" {...field} />}
            </FormField>
            <FormField control={form.control} name="password" required label={t('Password')}>
              {(field) => <Input type="password" autoComplete="new-password" {...field} />}
            </FormField>
            <FormField control={form.control} name="password_confirmation" required label={t('Confirm password')}>
              {(field) => <Input type="password" autoComplete="new-password" {...field} />}
            </FormField>
          </FieldGroup>
        </CardContent>
        <CardFooter className="mt-6 flex-col gap-3">
          <Button type="submit" className="w-full" disabled={register.isPending}>
            {t('Create account')}
          </Button>
          <p className="text-muted-foreground text-sm">
            {t('Already have an account?')}{' '}
            <Link to="/login" search={{ redirect }} className="text-foreground underline-offset-4 hover:underline">
              {t('Sign in')}
            </Link>
          </p>
        </CardFooter>
      </form>
    </Card>
  )
}
