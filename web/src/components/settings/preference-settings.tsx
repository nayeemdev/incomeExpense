import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTheme } from '@/hooks/use-theme'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Combobox } from '@/components/app/combobox'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { accountsQuery, userQuery } from '@/lib/api/queries'
import type { Preferences } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

const timezones = Intl.supportedValuesOf('timeZone')

export function PreferenceSettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { setTheme } = useTheme()
  const ledger = useLedger()
  const invalidateLedger = useInvalidateLedger(ledger.id)
  const { data: user } = useQuery(userQuery)
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id, { type: ['asset'], active: true }), select: (page) => page.data })

  const update = useMutation({
    mutationFn: (patch: Partial<Preferences>) => api.patch('/user/preferences', patch),
    onMutate: (patch) => patch.theme && setTheme(patch.theme),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      toast.success(t('Preferences saved'))
    },
    onError: notifyError,
  })

  const updateDefaultAccount = useMutation({
    mutationFn: (patch: { default_account_id?: number | null; default_income_account_id?: number | null }) =>
      api.patch(`/ledgers/${ledger.id}/membership`, patch),
    onSuccess: async () => {
      await invalidateLedger()
      toast.success(t('Preferences saved'))
    },
    onError: notifyError,
  })

  if (!user) return null

  const accountOptions = accounts.map((account) => ({ value: String(account.id), label: account.name, hint: account.currency?.code }))

  const weekdays = Array.from({ length: 7 }, (_, day) =>
    new Intl.DateTimeFormat(user.preferences.locale, { weekday: 'long' }).format(new Date(2024, 0, 7 + day)),
  )

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('Preferences')}</CardTitle>
          <CardDescription>{t('These apply to you only, in every ledger.')}</CardDescription>
        </CardHeader>
        <CardContent>
          <FieldGroup>
            <Field>
              <FieldLabel>{t('Theme')}</FieldLabel>
              <Select value={user.preferences.theme} onValueChange={(theme) => update.mutate({ theme: theme as Preferences['theme'] })}>
                <SelectTrigger className="w-48">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="system">{t('System')}</SelectItem>
                  <SelectItem value="light">{t('Light')}</SelectItem>
                  <SelectItem value="dark">{t('Dark')}</SelectItem>
                </SelectContent>
              </Select>
            </Field>
            <Field>
              <FieldLabel>{t('Time zone')}</FieldLabel>
              <Combobox
                className="max-w-sm"
                options={timezones.map((zone) => ({ value: zone, label: zone.replaceAll('_', ' ') }))}
                value={user.preferences.timezone}
                onChange={(timezone) => timezone && update.mutate({ timezone })}
              />
              <FieldDescription>{t('Used to decide what "today" is for quick entries.')}</FieldDescription>
            </Field>
            <Field>
              <FieldLabel>{t('Week starts on')}</FieldLabel>
              <Select
                value={String(user.preferences.first_day_of_week)}
                onValueChange={(day) => update.mutate({ first_day_of_week: Number(day) })}
              >
                <SelectTrigger className="w-48">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {weekdays.map((name, day) => (
                    <SelectItem key={day} value={String(day)}>
                      {name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </FieldGroup>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Default accounts in {{ledger}}', { ledger: ledger.name })}</CardTitle>
          <CardDescription>{t('Picked for you when you add a transaction, in the web app, the mobile app and widgets. You can still change it each time.')}</CardDescription>
        </CardHeader>
        <CardContent>
          <FieldGroup>
            <Field>
              <FieldLabel>{t('Pay expenses from')}</FieldLabel>
              <Combobox
                className="max-w-sm"
                options={accountOptions}
                value={ledger.default_account_id ? String(ledger.default_account_id) : null}
                onChange={(value) => updateDefaultAccount.mutate({ default_account_id: value ? Number(value) : null })}
                placeholder={t('Choose each time')}
              />
              <FieldDescription>{t('For example the card you use most for shopping.')}</FieldDescription>
            </Field>
            <Field>
              <FieldLabel>{t('Receive income into')}</FieldLabel>
              <Combobox
                className="max-w-sm"
                options={accountOptions}
                value={ledger.default_income_account_id ? String(ledger.default_income_account_id) : null}
                onChange={(value) => updateDefaultAccount.mutate({ default_income_account_id: value ? Number(value) : null })}
                placeholder={t('Same as expenses')}
              />
              <FieldDescription>{t('For example the account your salary is paid into.')}</FieldDescription>
            </Field>
          </FieldGroup>
        </CardContent>
      </Card>
    </div>
  )
}
