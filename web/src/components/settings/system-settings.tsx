import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MailIcon, ServerCogIcon } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { api } from '@/lib/api/client'
import { systemSettingsQuery } from '@/lib/api/queries'
import type { SystemSettings as Values } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

/**
 * Settings for the whole installation, only shown to the admin (the first account created).
 */
export function SystemSettings() {
  const { t } = useTranslation()
  const { data } = useQuery(systemSettingsQuery)

  if (!data) return null

  return (
    <div className="space-y-6">
      <p className="text-muted-foreground flex items-center gap-2 text-sm">
        <ServerCogIcon className="size-4" />
        {t('These settings apply to everyone on this LedgerFly server. Only admins can see them.')}
      </p>
      <Section
        title={t('Accounts and sharing')}
        initial={data}
        keys={['registration_enabled', 'invitation_expiry_days']}
        render={(draft, set) => (
          <FieldGroup>
            <Toggle
              id="registration"
              checked={draft.registration_enabled}
              onChange={(value) => set({ registration_enabled: value })}
              label={t('Anyone can sign up')}
              description={t('Leave this off for a personal server. People you invite to a ledger can always sign up.')}
            />
            <NumberField
              id="invitation-days"
              label={t('Invitations expire after (days)')}
              value={draft.invitation_expiry_days}
              onChange={(value) => set({ invitation_expiry_days: value })}
            />
          </FieldGroup>
        )}
      />
      <Section
        title={t('Email')}
        description={t('Used for alerts, invitations and password resets.')}
        initial={data}
        keys={['mail_mailer', 'mail_host', 'mail_port', 'mail_scheme', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name']}
        extraAction={<TestMailButton />}
        render={(draft, set) => (
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="mail-mailer">{t('Delivery')}</FieldLabel>
              <SelectField
                id="mail-mailer"
                value={draft.mail_mailer}
                onChange={(value) => set({ mail_mailer: value as Values['mail_mailer'] })}
                options={{ smtp: t('Send with an SMTP server'), log: t('Do not send, only write to the log') }}
              />
            </Field>
            {draft.mail_mailer === 'smtp' && (
              <>
                <div className="grid gap-4 sm:grid-cols-[1fr_8rem_9rem]">
                  <TextField id="mail-host" label={t('SMTP host')} value={draft.mail_host} onChange={(value) => set({ mail_host: value })} />
                  <NumberField id="mail-port" label={t('Port')} value={draft.mail_port} onChange={(value) => set({ mail_port: value })} />
                  <Field>
                    <FieldLabel htmlFor="mail-scheme">{t('Encryption')}</FieldLabel>
                    <SelectField
                      id="mail-scheme"
                      value={draft.mail_scheme ?? 'smtp'}
                      onChange={(value) => set({ mail_scheme: value as Values['mail_scheme'] })}
                      options={{ smtp: t('STARTTLS or none'), smtps: t('SSL/TLS') }}
                    />
                  </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                  <TextField id="mail-username" label={t('Username')} value={draft.mail_username} onChange={(value) => set({ mail_username: value })} />
                  <Field>
                    <FieldLabel htmlFor="mail-password">{t('Password')}</FieldLabel>
                    <Input
                      id="mail-password"
                      type="password"
                      autoComplete="new-password"
                      placeholder={data.mail_password ? t('Saved. Type a new one to replace it.') : ''}
                      value={typeof draft.mail_password === 'string' ? draft.mail_password : ''}
                      onChange={(event) => set({ mail_password: event.target.value })}
                    />
                  </Field>
                </div>
              </>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
              <TextField id="mail-from" label={t('From address')} type="email" value={draft.mail_from_address} onChange={(value) => set({ mail_from_address: value })} />
              <TextField id="mail-from-name" label={t('From name')} value={draft.mail_from_name} onChange={(value) => set({ mail_from_name: value })} />
            </div>
          </FieldGroup>
        )}
      />
      <Section
        title={t('Alerts')}
        initial={data}
        keys={['bill_reminder_days', 'budget_warning_percentage']}
        render={(draft, set) => (
          <div className="grid gap-4 sm:grid-cols-2">
            <NumberField
              id="bill-days"
              label={t('Remind about bills this many days before')}
              value={draft.bill_reminder_days}
              onChange={(value) => set({ bill_reminder_days: value })}
            />
            <NumberField
              id="budget-percent"
              label={t('Warn when a budget reaches (%)')}
              value={draft.budget_warning_percentage}
              onChange={(value) => set({ budget_warning_percentage: value })}
            />
          </div>
        )}
      />
      <Section
        title={t('Exchange rates and files')}
        initial={data}
        keys={['exchange_rates_enabled', 'exchange_rates_url', 'attachments_max_size_kb']}
        render={(draft, set) => (
          <FieldGroup>
            <Toggle
              id="rates"
              checked={draft.exchange_rates_enabled}
              onChange={(value) => set({ exchange_rates_enabled: value })}
              label={t('Fetch exchange rates every day')}
            />
            {draft.exchange_rates_enabled && (
              <Field>
                <FieldLabel htmlFor="rates-url">{t('Rates source')}</FieldLabel>
                <Input id="rates-url" value={draft.exchange_rates_url} onChange={(event) => set({ exchange_rates_url: event.target.value })} />
                <FieldDescription>{t('{base} is replaced with each ledger currency code.')}</FieldDescription>
              </Field>
            )}
            <NumberField
              id="attachments-size"
              label={t('Largest upload (KB)')}
              value={draft.attachments_max_size_kb}
              onChange={(value) => set({ attachments_max_size_kb: value })}
            />
          </FieldGroup>
        )}
      />
    </div>
  )
}

type SectionProps = {
  title: string
  description?: string
  initial: Values
  keys: Array<keyof Values>
  extraAction?: ReactNode
  render: (draft: Values, set: (patch: Partial<Values>) => void) => ReactNode
}

function Section({ title, description, initial, keys, extraAction, render }: SectionProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [draft, setDraft] = useState(initial)
  const save = useMutation({
    mutationFn: () => {
      const patch = Object.fromEntries(
        keys
          .filter((key) => key !== 'mail_password' || (typeof draft.mail_password === 'string' && draft.mail_password !== ''))
          .map((key) => [key, draft[key]]),
      )
      return api.patch<{ data: Values }>('/admin/settings', patch)
    },
    onSuccess: (response) => {
      queryClient.setQueryData(systemSettingsQuery.queryKey, response.data)
      setDraft((current) => ({ ...current, mail_password: response.data.mail_password }))
      toast.success(t('{{section}} saved', { section: title }))
    },
    onError: notifyError,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        {description && <CardDescription>{description}</CardDescription>}
      </CardHeader>
      <CardContent>{render(draft, (patch) => setDraft((current) => ({ ...current, ...patch })))}</CardContent>
      <CardFooter className="flex justify-end gap-2 border-t pt-4">
        {extraAction}
        <Button onClick={() => save.mutate()} disabled={save.isPending}>
          {t('Save')}
        </Button>
      </CardFooter>
    </Card>
  )
}

function TestMailButton() {
  const { t } = useTranslation()
  const send = useMutation({
    mutationFn: () => api.post<{ data: { sent_to: string } }>('/admin/settings/test-mail'),
    onSuccess: (response) => toast.success(t('Test email sent to {{email}}', { email: response.data.sent_to })),
    onError: notifyError,
  })

  return (
    <Button variant="outline" onClick={() => send.mutate()} disabled={send.isPending}>
      <MailIcon />
      {t('Send test email')}
    </Button>
  )
}

function Toggle({ id, checked, onChange, label, description }: { id: string; checked: boolean; onChange: (value: boolean) => void; label: string; description?: string }) {
  return (
    <Field orientation="horizontal">
      <Switch id={id} checked={checked} onCheckedChange={onChange} />
      <div>
        <FieldLabel htmlFor={id}>{label}</FieldLabel>
        {description && <FieldDescription>{description}</FieldDescription>}
      </div>
    </Field>
  )
}

function TextField({ id, label, value, onChange, type = 'text' }: { id: string; label: string; value: string | null; onChange: (value: string) => void; type?: string }) {
  return (
    <Field>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input id={id} type={type} value={value ?? ''} onChange={(event) => onChange(event.target.value)} />
    </Field>
  )
}

function NumberField({ id, label, value, onChange }: { id: string; label: string; value: number | null; onChange: (value: number) => void }) {
  return (
    <Field>
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input id={id} type="number" inputMode="numeric" value={value ?? ''} onChange={(event) => onChange(Number(event.target.value))} />
    </Field>
  )
}
