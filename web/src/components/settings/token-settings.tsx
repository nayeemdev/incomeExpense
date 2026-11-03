import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CopyIcon, KeyRoundIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { Badge } from '@/components/ui/badge'
import { RequiredMark } from '@/components/app/required-mark'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { InputGroup, InputGroupAddon, InputGroupButton, InputGroupInput } from '@/components/ui/input-group'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { tokensQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'

type Scope = 'quick-entry' | '*'

export function TokenSettings() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const queryClient = useQueryClient()
  const { data: tokens = [] } = useQuery(tokensQuery)
  const [name, setName] = useState('')
  const [scope, setScope] = useState<Scope>('quick-entry')
  const [created, setCreated] = useState<string | null>(null)
  const scopeLabels: Record<Scope, string> = { 'quick-entry': t('Quick entry only'), '*': t('Full access') }

  const create = useMutation({
    mutationFn: () => api.post<{ token: string }>('/tokens', { name, abilities: [scope] }),
    onSuccess: async ({ token }) => {
      setCreated(token)
      setName('')
      await queryClient.invalidateQueries({ queryKey: tokensQuery.queryKey })
    },
    onError: notifyError,
  })

  const revoke = useMutation({
    mutationFn: (id: number) => api.delete(`/tokens/${id}`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: tokensQuery.queryKey }),
    onError: notifyError,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <KeyRoundIcon className="size-5" />
          {t('Access tokens')}
        </CardTitle>
        <CardDescription>
          {t('Tokens let widgets, shortcuts and scripts use your account. Quick entry tokens can only add transactions and read the summary.')}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <form
          className="flex flex-col gap-2 sm:flex-row sm:items-end"
          onSubmit={(event) => {
            event.preventDefault()
            create.mutate()
          }}
        >
          <Field className="flex-1">
            <FieldLabel htmlFor="token-name">{t('Name')}
            <RequiredMark />
          </FieldLabel>
            <Input id="token-name" placeholder={t('e.g. iPhone widget')} value={name} onChange={(event) => setName(event.target.value)} />
          </Field>
          <Select value={scope} onValueChange={(value) => setScope(value as Scope)}>
            <SelectTrigger className="sm:w-44">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {(Object.keys(scopeLabels) as Scope[]).map((value) => (
                <SelectItem key={value} value={value}>
                  {scopeLabels[value]}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Button type="submit" disabled={!name.trim() || create.isPending}>
            {t('Create token')}
          </Button>
        </form>

        {created && (
          <div className="space-y-2 rounded-lg border p-4">
            <p className="text-sm font-medium">{t('Copy this token now. It is shown only once.')}</p>
            <InputGroup>
              <InputGroupInput readOnly value={created} className="font-mono text-xs" />
              <InputGroupAddon align="inline-end">
                <InputGroupButton
                  aria-label={t('Copy')}
                  onClick={() => navigator.clipboard.writeText(created).then(() => toast.success(t('Copied')))}
                >
                  <CopyIcon />
                </InputGroupButton>
              </InputGroupAddon>
            </InputGroup>
            <p className="text-muted-foreground text-xs">
              {t('API address')}: <code>{window.location.origin}</code> · {t('Ledger ID')}: <code>{ledger.id}</code>
            </p>
          </div>
        )}

        {tokens.length > 0 && (
          <ul className="divide-y">
            {tokens.map((token) => (
              <li key={token.id} className="flex items-center gap-3 py-2">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{token.name}</p>
                  <p className="text-muted-foreground text-xs">
                    {token.last_used_at
                      ? t('Last used {{date}}', { date: formatDate(token.last_used_at, locale) })
                      : t('Never used')}
                  </p>
                </div>
                <Badge variant="outline">{token.abilities.includes('*') ? scopeLabels['*'] : scopeLabels['quick-entry']}</Badge>
                <ConfirmDialog
                  trigger={
                    <Button variant="ghost" size="icon" aria-label={t('Revoke')}>
                      <Trash2Icon />
                    </Button>
                  }
                  title={t('Revoke {{name}}?', { name: token.name })}
                  description={t('Anything using this token stops working immediately.')}
                  confirmLabel={t('Revoke')}
                  onConfirm={() => revoke.mutate(token.id)}
                />
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
