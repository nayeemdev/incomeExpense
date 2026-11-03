import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTheme } from '@/hooks/use-theme'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Combobox } from '@/components/app/combobox'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api } from '@/lib/api/client'
import { userQuery } from '@/lib/api/queries'
import type { Preferences } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

const timezones = Intl.supportedValuesOf('timeZone')

export function PreferenceSettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { setTheme } = useTheme()
  const { data: user } = useQuery(userQuery)

  const update = useMutation({
    mutationFn: (patch: Partial<Preferences>) => api.patch('/user/preferences', patch),
    onMutate: (patch) => patch.theme && setTheme(patch.theme),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: userQuery.queryKey })
      toast.success(t('Preferences saved'))
    },
    onError: notifyError,
  })

  if (!user) return null

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

    </div>
  )
}
