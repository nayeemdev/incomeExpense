import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BellRingIcon } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { notificationSettingsQuery } from '@/lib/api/queries'
import type { NotificationSettings as Settings } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { channelLabels, notificationEventLabels } from '@/lib/labels'
import { currentSubscription, disablePush, enablePush, pushSupported } from '@/lib/push'
import { NtfySettings } from './ntfy-settings'

export function NotificationSettings() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const ledger = useLedger()
  const { data: settings } = useQuery(notificationSettingsQuery)
  const [pushEnabled, setPushEnabled] = useState(false)
  const events = notificationEventLabels()
  const channels = channelLabels()

  useEffect(() => {
    void currentSubscription().then((subscription) => setPushEnabled(subscription !== null))
  }, [])

  const update = useMutation({
    mutationFn: (patch: Partial<Settings>) => api.patch<{ data: Settings }>('/notification-settings', patch),
    onSuccess: (response) => queryClient.setQueryData(notificationSettingsQuery.queryKey, response.data),
    onError: notifyError,
  })

  const togglePush = useMutation({
    mutationFn: async (enable: boolean) => {
      if (enable) {
        if (!settings?.push_public_key) throw new Error(t('Push is not configured on this server yet.'))
        await enablePush(settings.push_public_key)
      } else {
        await disablePush()
      }

      return enable
    },
    onSuccess: (enabled) => {
      setPushEnabled(enabled)
      toast.success(enabled ? t('Push notifications are on for this device') : t('Push notifications are off for this device'))
    },
    onError: notifyError,
  })

  if (!settings) return null

  const toggleChannel = (event: string, channel: string, checked: boolean) => {
    const current = settings.events[event] ?? []
    const next = checked ? [...new Set([...current, channel])] : current.filter((item) => item !== channel)

    update.mutate({ events: { [event]: next } })
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <BellRingIcon className="size-5" />
            {t('Push on this device')}
          </CardTitle>
          <CardDescription>
            {pushSupported()
              ? t('Get alerts on this browser or phone even when LedgerFly is closed.')
              : t('This browser does not support push notifications. On iPhone, add LedgerFly to your home screen first.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Field orientation="horizontal">
            <Switch
              id="push-enabled"
              checked={pushEnabled}
              disabled={!pushSupported() || togglePush.isPending}
              onCheckedChange={(checked) => togglePush.mutate(checked)}
            />
            <FieldLabel htmlFor="push-enabled">{pushEnabled ? t('On') : t('Off')}</FieldLabel>
          </Field>
        </CardContent>
      </Card>

      <NtfySettings key={settings.ntfy?.topic ?? 'new'} settings={settings} />

      <Card>
        <CardHeader>
          <CardTitle>{t('What to notify you about')}</CardTitle>
          <CardDescription>{t('Choose how each alert reaches you. These settings apply to all your ledgers.')}</CardDescription>
        </CardHeader>
        <CardContent className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{t('Alert')}</TableHead>
                {settings.channels.map((channel) => (
                  <TableHead key={channel} className="text-center">
                    {channels[channel] ?? channel}
                  </TableHead>
                ))}
              </TableRow>
            </TableHeader>
            <TableBody>
              {Object.keys(settings.events).map((event) => (
                <TableRow key={event}>
                  <TableCell>{events[event] ?? event}</TableCell>
                  {settings.channels.map((channel) => (
                    <TableCell key={channel} className="text-center">
                      <Checkbox
                        aria-label={`${events[event] ?? event}: ${channels[channel] ?? channel}`}
                        checked={settings.events[event]?.includes(channel)}
                        onCheckedChange={(checked) => toggleChannel(event, channel, checked === true)}
                      />
                    </TableCell>
                  ))}
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Thresholds and quiet hours')}</CardTitle>
        </CardHeader>
        <CardContent>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="large-amount">{t('Large transaction alert from')}</FieldLabel>
              <ThresholdInput
                key={String(settings.large_transaction_amount)}
                initial={settings.large_transaction_amount ? String(settings.large_transaction_amount) : ''}
                currency={ledger.primary_currency.code}
                onSave={(amount) => update.mutate({ large_transaction_amount: amount || null })}
              />
              <FieldDescription>{t('Expenses in the primary currency of each ledger at or above this amount. Leave empty to turn off.')}</FieldDescription>
            </Field>
            <Field orientation="horizontal">
              <Switch
                id="quiet-hours"
                checked={settings.quiet_hours !== null}
                onCheckedChange={(checked) => update.mutate({ quiet_hours: checked ? { start: '22:00', end: '07:00' } : null })}
              />
              <FieldLabel htmlFor="quiet-hours">{t('Quiet hours (no push, alerts still appear in the app)')}</FieldLabel>
            </Field>
            {settings.quiet_hours && (
              <div className="flex items-center gap-2">
                <Input
                  type="time"
                  className="w-32"
                  aria-label={t('Quiet hours start')}
                  defaultValue={settings.quiet_hours.start}
                  onBlur={(event) => settings.quiet_hours && update.mutate({ quiet_hours: { ...settings.quiet_hours, start: event.target.value } })}
                />
                <span className="text-muted-foreground text-sm">{t('to')}</span>
                <Input
                  type="time"
                  className="w-32"
                  aria-label={t('Quiet hours end')}
                  defaultValue={settings.quiet_hours.end}
                  onBlur={(event) => settings.quiet_hours && update.mutate({ quiet_hours: { ...settings.quiet_hours, end: event.target.value } })}
                />
              </div>
            )}
          </FieldGroup>
        </CardContent>
      </Card>
    </div>
  )
}

function ThresholdInput({ initial, currency, onSave }: { initial: string; currency: string; onSave: (amount: string) => void }) {
  const { t } = useTranslation()
  const [value, setValue] = useState(initial)

  return (
    <div className="flex max-w-xs gap-2">
      <MoneyInput id="large-amount" value={value} onChange={setValue} currency={currency} />
      <Button variant="outline" onClick={() => onSave(value)}>
        {t('Save')}
      </Button>
    </div>
  )
}
