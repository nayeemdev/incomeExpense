import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { api } from '@/lib/api/client'
import { userQuery } from '@/lib/api/queries'
import { handleFormError } from '@/lib/forms'

export const Route = createFileRoute('/_guest/login')({
  validateSearch: z.object({ redirect: z.string().optional() }),
  component: Login,
})

const schema = z.object({
  email: z.email(),
  password: z.string().min(1),
  remember: z.boolean(),
})

function Login() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { redirect } = Route.useSearch()
  const form = useForm({ resolver: zodResolver(schema), defaultValues: { email: '', password: '', remember: true } })

  const login = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => api.post<{ two_factor: boolean }>('/auth/login', values),
    onSuccess: async (response) => {
      if (response.two_factor) {
        await navigate({ to: '/two-factor-challenge', search: { redirect } })

        return
      }

      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      await navigate({ to: redirect ?? '/' })
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Welcome back')}</CardTitle>
        <CardDescription>{t('Sign in to your account')}</CardDescription>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => login.mutate(values))}>
        <CardContent>
          <FieldGroup>
            <FormField control={form.control} name="email" label={t('Email')}>
              {(field) => <Input type="email" autoComplete="email" autoFocus {...field} />}
            </FormField>
            <FormField
              control={form.control}
              name="password"
              label={
                <span className="flex w-full items-center justify-between">
                  {t('Password')}
                  <Link to="/forgot-password" className="text-muted-foreground text-sm font-normal hover:underline">
                    {t('Forgot password?')}
                  </Link>
                </span>
              }
            >
              {(field) => <Input type="password" autoComplete="current-password" {...field} />}
            </FormField>
            <FormField control={form.control} name="remember" orientation="horizontal" label={t('Keep me signed in')}>
              {(field) => <Checkbox id={field.id} checked={field.value} onCheckedChange={(checked) => field.onChange(checked === true)} />}
            </FormField>
          </FieldGroup>
        </CardContent>
        <CardFooter className="mt-6 flex-col gap-3">
          <Button type="submit" className="w-full" disabled={login.isPending}>
            {t('Sign in')}
          </Button>
          <p className="text-muted-foreground text-sm">
            {t('No account yet?')}{' '}
            <Link to="/register" className="text-foreground underline-offset-4 hover:underline">
              {t('Create one')}
            </Link>
          </p>
        </CardFooter>
      </form>
    </Card>
  )
}
