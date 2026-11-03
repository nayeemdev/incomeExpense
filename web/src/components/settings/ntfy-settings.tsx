import { useMutation, useQueryClient } from '@tanstack/react-query'
import { SendIcon, ShuffleIcon, SmartphoneIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { RequiredMark } from '@/components/app/required-mark'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group'
import { Switch } from '@/components/ui/switch'
import { api } from '@/lib/api/client'
import { notificationSettingsQuery } from '@/lib/api/queries'
import type { NotificationSettings } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

/** A topic name long and random enough that nobody can guess it on a public ntfy server. */
function randomTopic() {
  const bytes = crypto.getRandomValues(new Uint8Array(9))
  return `ledgerfly-${Array.from(bytes, (byte) => (byte % 36).toString(36)).join('')}`
}

export function NtfySettings({ settings }: { settings: NotificationSettings }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const saved = settings.ntfy
  const [server, setServer] = useState(saved?.server ?? settings.ntfy_default_server)
  const [topic, setTopic] = useState(saved?.topic ?? '')
  const [token, setToken] = useState('')
  const [privateMode, setPrivateMode] = useState(saved?.private ?? true)

  const save = useMutation({
    mutationFn: (ntfy: Record<string, unknown> | null) => api.patch<{ data: NotificationSettings }>('/notification-settings', { ntfy }),
    onSuccess: (response, ntfy) => {
      queryClient.setQueryData(notificationSettingsQuery.queryKey, response.data)
      setToken('')
      toast.success(ntfy ? t('ntfy settings saved') : t('ntfy is turned off'))
    },
    onError: notifyError,
  })

  const test = useMutation({
    mutationFn: () => api.post('/notification-settings/ntfy-test'),
    onSuccess: () => toast.success(t('Test sent. It should appear in the ntfy app in a moment.')),
    onError: notifyError,
  })

  const submit = () =>
    save.mutate({ server, topic, private: privateMode, ...(token ? { token } : {}) })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <SmartphoneIcon className="size-5" />
          {t('Phone alerts with ntfy')}
        </CardTitle>
        <CardDescription>
          {t('Install the free ntfy app on your phone and subscribe to the same server and topic. Alerts chosen for ntfy below will arrive there instantly.')}
        </CardDescription>
      </CardHeader>
      <CardContent>
        <FieldGroup>
          <Field>
            <FieldLabel htmlFor="ntfy-server">
              {t('Server')}
              <RequiredMark />
            </FieldLabel>
            <Input id="ntfy-server" type="url" value={server} onChange={(event) => setServer(event.target.value)} />
            <FieldDescription>{t('Use https://ntfy.sh, or the address of your own ntfy server.')}</FieldDescription>
          </Field>
          <Field>
            <FieldLabel htmlFor="ntfy-topic">
              {t('Topic')}
              <RequiredMark />
            </FieldLabel>
            <InputGroup>
              <InputGroupInput id="ntfy-topic" value={topic} placeholder="ledgerfly-..." onChange={(event) => setTopic(event.target.value)} />
              <InputGroupAddon align="inline-end">
                <InputGroupButton onClick={() => setTopic(randomTopic())} aria-label={t('Generate a topic')}>
                  <ShuffleIcon />
                  {t('Generate')}
                </InputGroupButton>
              </InputGroupAddon>
            </InputGroup>
            <FieldDescription>{t('Anyone who knows the topic can read it on a public server, so keep it long and random.')}</FieldDescription>
          </Field>
          <Field>
            <FieldLabel htmlFor="ntfy-token">{t('Access token')}</FieldLabel>
            <Input
              id="ntfy-token"
              type="password"
              autoComplete="off"
              value={token}
              placeholder={saved?.has_token ? t('Saved. Type a new one to replace it.') : t('Only for protected topics')}
              onChange={(event) => setToken(event.target.value)}
            />
          </Field>
          <Field orientation="horizontal">
            <Switch id="ntfy-private" checked={privateMode} onCheckedChange={setPrivateMode} />
            <div>
              <FieldLabel htmlFor="ntfy-private">{t('Hide amounts and details')}</FieldLabel>
              <FieldDescription>{t('Alerts say what happened, like "Bill due: Rent", and you open LedgerFly for the rest.')}</FieldDescription>
            </div>
          </Field>
        </FieldGroup>
      </CardContent>
      <CardFooter className="flex flex-wrap justify-end gap-2 border-t pt-4">
        {saved && (
          <>
            <Button variant="ghost" onClick={() => save.mutate(null)} disabled={save.isPending}>
              {t('Turn off')}
            </Button>
            <Button variant="outline" onClick={() => test.mutate()} disabled={test.isPending}>
              <SendIcon />
              {t('Send test')}
            </Button>
          </>
        )}
        <Button onClick={submit} disabled={save.isPending || !server.trim() || !topic.trim()}>
          {t('Save')}
        </Button>
      </CardFooter>
    </Card>
  )
}
