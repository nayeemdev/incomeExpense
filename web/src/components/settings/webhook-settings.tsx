import { useMutation, useQuery } from '@tanstack/react-query'
import { CopyIcon, PlusIcon, Trash2Icon, WebhookIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { webhookDeliveriesQuery, webhooksQuery } from '@/lib/api/queries'
import type { Webhook } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

const events = ['transaction.created', 'transaction.updated', 'transaction.deleted']

export function WebhookSettings() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: webhooks = [] } = useQuery({ ...webhooksQuery(ledger.id), enabled: ledger.role === 'owner' })
  const [creating, setCreating] = useState(false)
  const [secret, setSecret] = useState<string | null>(null)
  const [inspecting, setInspecting] = useState<Webhook | null>(null)
  const [draft, setDraft] = useState({ title: '', url: '', events: [...events] })

  const create = useMutation({
    mutationFn: () => api.post<{ data: { secret: string } }>(`/ledgers/${ledger.id}/webhooks`, draft),
    onSuccess: async (response) => {
      setCreating(false)
      setSecret(response.data.secret)
      setDraft({ title: '', url: '', events: [...events] })
      await invalidate()
    },
    onError: notifyError,
  })

  const update = useMutation({
    mutationFn: ({ id, active }: { id: number; active: boolean }) => api.patch(`/ledgers/${ledger.id}/webhooks/${id}`, { active }),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/ledgers/${ledger.id}/webhooks/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  if (ledger.role !== 'owner') {
    return <p className="text-muted-foreground text-sm">{t('Only owners can manage webhooks.')}</p>
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <WebhookIcon className="size-5" />
          {t('Webhooks')}
        </CardTitle>
        <CardDescription>
          {t('Send transaction events to another service. Each request is signed with the X-LedgerFly-Signature header.')}
        </CardDescription>
        <CardAction>
          <Button size="sm" onClick={() => setCreating(true)}>
            <PlusIcon />
            {t('Add')}
          </Button>
        </CardAction>
      </CardHeader>
      <CardContent className="space-y-4">
        {secret && (
          <div className="space-y-2 rounded-lg border p-4">
            <p className="text-sm font-medium">{t('Signing secret. Copy it now, it is shown only once.')}</p>
            <div className="flex gap-2">
              <Input readOnly value={secret} className="font-mono text-xs" />
              <Button variant="outline" size="icon" aria-label={t('Copy')} onClick={() => navigator.clipboard.writeText(secret).then(() => toast.success(t('Copied')))}>
                <CopyIcon />
              </Button>
            </div>
          </div>
        )}
        {webhooks.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('No webhooks yet.')}</p>
        ) : (
          <ul className="divide-y">
            {webhooks.map((webhook) => (
              <li key={webhook.id} className="flex flex-wrap items-center gap-3 py-3">
                <div className="min-w-0 flex-1">
                  <button type="button" className="font-medium hover:underline" onClick={() => setInspecting(webhook)}>
                    {webhook.title}
                  </button>
                  <p className="text-muted-foreground truncate font-mono text-xs">{webhook.url}</p>
                  <div className="mt-1 flex flex-wrap gap-1">
                    {webhook.events.map((event) => (
                      <Badge key={event} variant="outline">
                        {event}
                      </Badge>
                    ))}
                  </div>
                </div>
                <Switch aria-label={t('Active')} checked={webhook.active} onCheckedChange={(active) => update.mutate({ id: webhook.id, active })} />
                <ConfirmDialog
                  trigger={
                    <Button variant="ghost" size="icon" aria-label={t('Delete')}>
                      <Trash2Icon />
                    </Button>
                  }
                  title={t('Delete {{name}}?', { name: webhook.title })}
                  description={t('No more events will be sent to this address.')}
                  onConfirm={() => remove.mutate(webhook.id)}
                />
              </li>
            ))}
          </ul>
        )}
      </CardContent>

      <Dialog open={creating} onOpenChange={setCreating}>
        <DialogContent>
          <form
            className="space-y-6"
            onSubmit={(event) => {
              event.preventDefault()
              create.mutate()
            }}
          >
            <DialogHeader>
              <DialogTitle>{t('New webhook')}</DialogTitle>
            </DialogHeader>
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="webhook-title">{t('Name')}</FieldLabel>
                <Input id="webhook-title" autoFocus value={draft.title} onChange={(event) => setDraft({ ...draft, title: event.target.value })} />
              </Field>
              <Field>
                <FieldLabel htmlFor="webhook-url">{t('URL')}</FieldLabel>
                <Input id="webhook-url" type="url" placeholder="https://" value={draft.url} onChange={(event) => setDraft({ ...draft, url: event.target.value })} />
              </Field>
              <div className="space-y-2">
                {events.map((name) => (
                  <Field key={name} orientation="horizontal">
                    <Checkbox
                      id={`event-${name}`}
                      checked={draft.events.includes(name)}
                      onCheckedChange={(checked) =>
                        setDraft({ ...draft, events: checked ? [...draft.events, name] : draft.events.filter((item) => item !== name) })
                      }
                    />
                    <FieldLabel htmlFor={`event-${name}`} className="font-mono text-xs font-normal">
                      {name}
                    </FieldLabel>
                  </Field>
                ))}
              </div>
            </FieldGroup>
            <DialogFooter>
              <Button type="submit" disabled={create.isPending || !draft.title || !draft.url || draft.events.length === 0}>
                {t('Create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {inspecting && <DeliveriesDialog webhook={inspecting} onClose={() => setInspecting(null)} />}
    </Card>
  )
}

function DeliveriesDialog({ webhook, onClose }: { webhook: Webhook; onClose: () => void }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: deliveries = [] } = useQuery(webhookDeliveriesQuery(ledger.id, webhook.id))

  const redeliver = useMutation({
    mutationFn: (id: number) => api.post(`/ledgers/${ledger.id}/webhooks/${webhook.id}/deliveries/${id}/redeliver`),
    onSuccess: async () => {
      await invalidate()
      toast.success(t('Sent again'))
    },
    onError: notifyError,
  })

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{webhook.title}</DialogTitle>
          <DialogDescription>{t('The last 50 deliveries.')}</DialogDescription>
        </DialogHeader>
        {deliveries.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('Nothing has been sent yet.')}</p>
        ) : (
          <ul className="max-h-96 divide-y overflow-y-auto">
            {deliveries.map((delivery) => (
              <li key={delivery.id} className="flex items-center gap-3 py-2 text-sm">
                <Badge variant={delivery.delivered_at ? 'secondary' : delivery.failed_at ? 'destructive' : 'outline'}>
                  {delivery.status_code ?? t('pending')}
                </Badge>
                <span className="font-mono text-xs">{delivery.event}</span>
                <span className="text-muted-foreground ml-auto text-xs">
                  {new Intl.DateTimeFormat(locale, { dateStyle: 'short', timeStyle: 'short' }).format(new Date(delivery.created_at))}
                </span>
                <Button variant="ghost" size="sm" onClick={() => redeliver.mutate(delivery.id)}>
                  {t('Resend')}
                </Button>
              </li>
            ))}
          </ul>
        )}
      </DialogContent>
    </Dialog>
  )
}
