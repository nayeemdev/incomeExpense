import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { MoreHorizontalIcon, PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/app/page-header'
import { RuleEditor } from '@/components/rules/rule-editor'
import { RuleTestDialog } from '@/components/rules/rule-test-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { Field, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { Switch } from '@/components/ui/switch'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { ruleGroupsQuery } from '@/lib/api/queries'
import type { Rule, RuleGroup } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { actionLabels, triggerLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/$ledgerId/rules/')({
  component: Rules,
})

function summary(rule: Rule) {
  const triggers = triggerLabels()
  const actions = actionLabels()
  const when = rule.triggers
    .map((trigger) => `${trigger.negated ? '¬ ' : ''}${triggers[trigger.type] ?? trigger.type}${trigger.value ? ` "${trigger.value}"` : ''}`)
    .join(rule.strict ? ' & ' : ' | ')
  const then = rule.actions.map((action) => `${actions[action.type] ?? action.type}${action.value ? ` "${action.value}"` : ''}`).join(', ')

  return `${when} → ${then}`
}

function Rules() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: groups } = useQuery(ruleGroupsQuery(ledger.id))
  const [editing, setEditing] = useState<{ groupId: string; rule?: Rule } | null>(null)
  const [testing, setTesting] = useState<Rule | null>(null)
  const [newGroup, setNewGroup] = useState<string | null>(null)

  const createGroup = useMutation({
    mutationFn: (title: string) => api.post<{ data: RuleGroup }>(`/ledgers/${ledger.id}/rule-groups`, { title, order: groups?.length ?? 0 }),
    onSuccess: async (response) => {
      setNewGroup(null)
      await invalidate()
      if (groups?.length === 0) setEditing({ groupId: response.data.id })
    },
    onError: notifyError,
  })

  const updateGroup = useMutation({
    mutationFn: ({ id, ...patch }: { id: string; active?: boolean }) => api.patch(`/ledgers/${ledger.id}/rule-groups/${id}`, patch),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const deleteGroup = useMutation({
    mutationFn: (id: string) => api.delete(`/ledgers/${ledger.id}/rule-groups/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const toggleRule = useMutation({
    mutationFn: (rule: Rule) => api.put(`/ledgers/${ledger.id}/rules/${rule.id}`, { active: !rule.active }),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const deleteRule = useMutation({
    mutationFn: (id: string) => api.delete(`/ledgers/${ledger.id}/rules/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <>
      <PageHeader
        title={t('Rules')}
        description={t('Categorise, tag and tidy up transactions automatically as they come in.')}
        actions={
          canEdit && (
            <Button onClick={() => setNewGroup('')}>
              <PlusIcon />
              {t('New group')}
            </Button>
          )
        }
      />
      {!groups && <Skeleton className="h-48 w-full" />}
      {groups?.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{t('No rules yet')}</EmptyTitle>
            <EmptyDescription>{t('For example: when the description contains "Uber", set the category to Transport.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => createGroup.mutate(t('My rules'))}>{t('Create your first rule')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}
      <div className="space-y-4">
        {groups?.map((group) => (
          <Card key={group.id}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                {group.title}
                {!group.active && <Badge variant="outline">{t('Paused')}</Badge>}
              </CardTitle>
              {group.description && <CardDescription>{group.description}</CardDescription>}
              {canEdit && (
                <CardAction className="flex items-center gap-2">
                  <Switch aria-label={t('Group active')} checked={group.active} onCheckedChange={(active) => updateGroup.mutate({ id: group.id, active })} />
                  <Button size="sm" variant="outline" onClick={() => setEditing({ groupId: group.id })}>
                    <PlusIcon />
                    {t('Rule')}
                  </Button>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <Button variant="ghost" size="icon" className="size-8" aria-label={t('Group actions')}>
                        <MoreHorizontalIcon />
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuItem variant="destructive" onSelect={() => deleteGroup.mutate(group.id)}>
                        {t('Delete group and its rules')}
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </CardAction>
              )}
            </CardHeader>
            <CardContent>
              {group.rules.length === 0 ? (
                <p className="text-muted-foreground text-sm">{t('No rules in this group.')}</p>
              ) : (
                <ul className="divide-y">
                  {group.rules.map((rule) => (
                    <li key={rule.id} className="flex items-center gap-3 py-2.5">
                      <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-2 font-medium">
                          {rule.title}
                          {!rule.active && <Badge variant="outline">{t('Off')}</Badge>}
                        </p>
                        <p className="text-muted-foreground truncate text-xs">{summary(rule)}</p>
                      </div>
                      <Button variant="ghost" size="sm" onClick={() => setTesting(rule)}>
                        {t('Test')}
                      </Button>
                      {canEdit && (
                        <DropdownMenu>
                          <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="size-8" aria-label={t('Rule actions')}>
                              <MoreHorizontalIcon />
                            </Button>
                          </DropdownMenuTrigger>
                          <DropdownMenuContent align="end">
                            <DropdownMenuItem onSelect={() => setEditing({ groupId: group.id, rule })}>{t('Edit')}</DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => toggleRule.mutate(rule)}>{rule.active ? t('Turn off') : t('Turn on')}</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem variant="destructive" onSelect={() => deleteRule.mutate(rule.id)}>
                              {t('Delete')}
                            </DropdownMenuItem>
                          </DropdownMenuContent>
                        </DropdownMenu>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      <FormDialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)} title={editing?.rule ? t('Edit rule') : t('New rule')} size="xl">
        {editing && groups && (
              <RuleEditor key={editing.rule?.id ?? 'new'} groups={groups} groupId={editing.groupId} rule={editing.rule} onDone={() => setEditing(null)} />
            )}
      </FormDialog>

      {testing && <RuleTestDialog rule={testing} onClose={() => setTesting(null)} />}

      <Dialog open={newGroup !== null} onOpenChange={(open) => !open && setNewGroup(null)}>
        <DialogContent className="sm:max-w-sm">
          <form
            className="space-y-6"
            onSubmit={(event) => {
              event.preventDefault()
              if (newGroup) createGroup.mutate(newGroup)
            }}
          >
            <DialogHeader>
              <DialogTitle>{t('New rule group')}</DialogTitle>
            </DialogHeader>
            <Field>
              <FieldLabel htmlFor="group-title">{t('Name')}</FieldLabel>
              <Input id="group-title" autoFocus value={newGroup ?? ''} onChange={(event) => setNewGroup(event.target.value)} />
            </Field>
            <DialogFooter>
              <Button type="submit" disabled={!newGroup || createGroup.isPending}>
                {t('Create')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  )
}
