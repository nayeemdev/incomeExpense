import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { Trash2Icon } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { z } from 'zod'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { FormField } from '@/components/app/form-field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { invitationsQuery, membersQuery, userQuery } from '@/lib/api/queries'
import type { LedgerRole } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { handleFormError, notifyError } from '@/lib/forms'
import { roleLabels } from '@/lib/labels'

function RoleSelect({ value, onChange, disabled }: { value: LedgerRole; onChange: (role: LedgerRole) => void; disabled?: boolean }) {
  const labels = roleLabels()

  return (
    <Select value={value} onValueChange={(next) => onChange(next as LedgerRole)} disabled={disabled}>
      <SelectTrigger className="w-32">
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {(Object.keys(labels) as LedgerRole[]).map((role) => (
          <SelectItem key={role} value={role}>
            {labels[role]}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}

const inviteSchema = z.object({ email: z.email(), role: z.enum(['owner', 'editor', 'viewer']) })

export function MemberSettings() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const manage = ledger.role === 'owner'
  const { data: user } = useQuery(userQuery)
  const { data: members = [] } = useQuery(membersQuery(ledger.id))
  const { data: invitations = [] } = useQuery({ ...invitationsQuery(ledger.id), enabled: manage })
  const form = useForm({ resolver: zodResolver(inviteSchema), defaultValues: { email: '', role: 'editor' as const } })

  const updateRole = useMutation({
    mutationFn: ({ id, role }: { id: string; role: LedgerRole }) => api.patch(`/ledgers/${ledger.id}/members/${id}`, { role }),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const removeMember = useMutation({
    mutationFn: (id: string) => api.delete(`/ledgers/${ledger.id}/members/${id}`),
    onSuccess: async (_, id) => {
      if (id === user?.id) {
        await queryClient.invalidateQueries({ queryKey: ['ledgers'] })
        await navigate({ to: '/' })
      } else {
        await invalidate()
      }
    },
    onError: notifyError,
  })

  const invite = useMutation({
    mutationFn: (values: z.infer<typeof inviteSchema>) => api.post(`/ledgers/${ledger.id}/invitations`, values),
    onSuccess: async () => {
      form.reset()
      await invalidate()
      toast.success(t('Invitation sent'))
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  const revoke = useMutation({
    mutationFn: (id: string) => api.delete(`/ledgers/${ledger.id}/invitations/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('Members')}</CardTitle>
          <CardDescription>
            {t('Owners manage the ledger and its members, editors record transactions, viewers can only look.')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <ul className="divide-y">
            {members.map((member) => (
              <li key={member.id} className="flex flex-wrap items-center gap-3 py-3">
                <div className="min-w-0 flex-1">
                  <p className="truncate font-medium">
                    {member.name} {member.id === user?.id && <Badge variant="secondary">{t('You')}</Badge>}
                  </p>
                  <p className="text-muted-foreground truncate text-sm">{member.email}</p>
                </div>
                <RoleSelect
                  value={member.role}
                  disabled={!manage}
                  onChange={(role) => updateRole.mutate({ id: member.id, role })}
                />
                {(manage || member.id === user?.id) && (
                  <ConfirmDialog
                    trigger={
                      <Button variant="ghost" size="icon" aria-label={member.id === user?.id ? t('Leave ledger') : t('Remove')}>
                        <Trash2Icon />
                      </Button>
                    }
                    title={member.id === user?.id ? t('Leave this ledger?') : t('Remove {{name}}?', { name: member.name })}
                    description={t('They lose access to every account and transaction in this ledger.')}
                    confirmLabel={member.id === user?.id ? t('Leave') : t('Remove')}
                    onConfirm={() => removeMember.mutate(member.id)}
                  />
                )}
              </li>
            ))}
          </ul>
        </CardContent>
      </Card>

      {manage && (
        <Card>
          <CardHeader>
            <CardTitle>{t('Invite someone')}</CardTitle>
            <CardDescription>{t('They get an email with a link to join. It works once they sign in with that address.')}</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <form onSubmit={form.handleSubmit((values) => invite.mutate(values))} className="flex flex-col gap-2 sm:flex-row sm:items-start">
              <FormField control={form.control} name="email" required className="flex-1">
                {(field) => <Input type="email" placeholder={t('Email address')} {...field} />}
              </FormField>
              <FormField control={form.control} name="role" className="sm:w-auto">
                {(field) => <RoleSelect value={field.value} onChange={field.onChange} />}
              </FormField>
              <Button type="submit" disabled={invite.isPending}>
                {t('Send invite')}
              </Button>
            </form>
            {invitations.length > 0 && (
              <div>
                <h4 className="text-muted-foreground mb-2 text-sm font-medium">{t('Pending invitations')}</h4>
                <ul className="divide-y">
                  {invitations.map((invitation) => (
                    <li key={invitation.id} className="flex items-center gap-3 py-2">
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm">{invitation.email}</p>
                        <p className="text-muted-foreground text-xs">
                          {roleLabels()[invitation.role]} · {t('expires {{date}}', { date: formatDate(invitation.expires_at, locale) })}
                        </p>
                      </div>
                      <Button variant="ghost" size="sm" onClick={() => revoke.mutate(invitation.id)}>
                        {t('Revoke')}
                      </Button>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
