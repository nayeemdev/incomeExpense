import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ShieldCheckIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { ApiError, api } from '@/lib/api/client'
import { userQuery } from '@/lib/api/queries'
import { notifyError } from '@/lib/forms'
import { PasswordConfirmDialog } from './password-confirm-dialog'

type Setup = { svg: string; secret: string }

export function SecuritySettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { data: user } = useQuery(userQuery)
  const [setup, setSetup] = useState<Setup | null>(null)
  const [code, setCode] = useState('')
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null)
  const [pending, setPending] = useState<(() => void) | null>(null)

  const withPassword = (action: () => Promise<unknown>) => async () => {
    try {
      await action()
    } catch (error) {
      if (error instanceof ApiError && error.status === 423) {
        setPending(() => () => void withPassword(action)())

        return
      }

      notifyError(error)
    }
  }

  const refreshUser = () => queryClient.invalidateQueries({ queryKey: userQuery.queryKey })

  const enable = withPassword(async () => {
    await api.post('/auth/user/two-factor-authentication')
    const [qr, secret] = await Promise.all([
      api.get<{ svg: string }>('/auth/user/two-factor-qr-code'),
      api.get<{ secretKey: string }>('/auth/user/two-factor-secret-key'),
    ])
    setSetup({ svg: qr.svg, secret: secret.secretKey })
  })

  const showRecoveryCodes = withPassword(async () => {
    setRecoveryCodes(await api.get<string[]>('/auth/user/two-factor-recovery-codes'))
  })

  const regenerate = withPassword(async () => {
    await api.post('/auth/user/two-factor-recovery-codes')
    setRecoveryCodes(await api.get<string[]>('/auth/user/two-factor-recovery-codes'))
  })

  const disable = withPassword(async () => {
    await api.delete('/auth/user/two-factor-authentication')
    setRecoveryCodes(null)
    await refreshUser()
    toast.success(t('Two-factor authentication turned off'))
  })

  const confirm = useMutation({
    mutationFn: () => api.post('/auth/user/confirmed-two-factor-authentication', { code }),
    onSuccess: async () => {
      setSetup(null)
      setCode('')
      await refreshUser()
      await showRecoveryCodes()
    },
    onError: notifyError,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <ShieldCheckIcon className="size-5" />
          {t('Two-factor authentication')}
        </CardTitle>
        <CardDescription>
          {user?.two_factor_enabled
            ? t('Signing in requires a code from your authenticator app.')
            : t('Add a second step to signing in with an authenticator app.')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        {setup && (
          <div className="space-y-4">
            <p className="text-sm">{t('Scan this QR code with your authenticator app, then enter the code it shows.')}</p>
            <div className="w-fit rounded-lg bg-white p-3" dangerouslySetInnerHTML={{ __html: setup.svg }} />
            <p className="text-muted-foreground text-xs">
              {t('Setup key')}: <code className="font-mono">{setup.secret}</code>
            </p>
            <form
              className="flex items-end gap-2"
              onSubmit={(event) => {
                event.preventDefault()
                confirm.mutate()
              }}
            >
              <Field className="max-w-40">
                <FieldLabel htmlFor="two-factor-code">{t('Code')}</FieldLabel>
                <Input id="two-factor-code" inputMode="numeric" maxLength={6} value={code} onChange={(event) => setCode(event.target.value)} />
              </Field>
              <Button type="submit" disabled={confirm.isPending}>
                {t('Confirm')}
              </Button>
            </form>
          </div>
        )}
        {recoveryCodes && (
          <div className="space-y-2">
            <p className="text-sm">{t('Store these recovery codes somewhere safe. Each one signs you in once if you lose your device.')}</p>
            <pre className="bg-muted grid grid-cols-2 gap-1 rounded-lg p-4 font-mono text-sm">
              {recoveryCodes.map((item) => (
                <span key={item}>{item}</span>
              ))}
            </pre>
          </div>
        )}
      </CardContent>
      <CardFooter className="flex flex-wrap gap-2">
        {!user?.two_factor_enabled && !setup && <Button onClick={enable}>{t('Turn on')}</Button>}
        {user?.two_factor_enabled && (
          <>
            {!recoveryCodes && (
              <Button variant="outline" onClick={showRecoveryCodes}>
                {t('Show recovery codes')}
              </Button>
            )}
            {recoveryCodes && (
              <Button variant="outline" onClick={regenerate}>
                {t('New recovery codes')}
              </Button>
            )}
            <Button variant="destructive" onClick={disable}>
              {t('Turn off')}
            </Button>
          </>
        )}
      </CardFooter>
      <PasswordConfirmDialog
        open={pending !== null}
        onOpenChange={(open) => !open && setPending(null)}
        onConfirmed={() => {
          pending?.()
          setPending(null)
        }}
      />
    </Card>
  )
}
