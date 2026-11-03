import { useMutation, useQuery } from '@tanstack/react-query'
import { PlusIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SelectField } from '@/components/app/select-field'
import { Button } from '@/components/ui/button'
import { Field, FieldError, FieldGroup, FieldLabel, FieldSeparator } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { useLedger } from '@/hooks/use-ledger'
import { ApiError, api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { ruleOptionsQuery } from '@/lib/api/queries'
import type { Rule, RuleAction, RuleGroup, RuleTrigger } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { actionLabels, triggerLabels } from '@/lib/labels'

type RuleEditorProps = { groups: RuleGroup[]; groupId: number; rule?: Rule; onDone: () => void }

export function RuleEditor({ groups, groupId, rule, onDone }: RuleEditorProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: options } = useQuery(ruleOptionsQuery)
  const triggerNames = triggerLabels()
  const actionNames = actionLabels()
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [draft, setDraft] = useState({
    title: rule?.title ?? '',
    rule_group_id: String(rule?.rule_group_id ?? groupId),
    trigger_on: rule?.trigger_on ?? 'both',
    strict: rule?.strict ?? true,
    stop_processing: rule?.stop_processing ?? false,
    active: rule?.active ?? true,
    triggers: rule?.triggers ?? ([{ type: 'description_contains', value: '', negated: false }] as RuleTrigger[]),
    actions: rule?.actions ?? ([{ type: 'set_category', value: '' }] as RuleAction[]),
  })

  const needsValue = (kind: 'triggers' | 'actions', type: string) =>
    options?.[kind].find((option) => option.type === type)?.needs_value ?? true

  const save = useMutation({
    mutationFn: () => {
      const body = { ...draft, rule_group_id: Number(draft.rule_group_id) }

      return rule ? api.put(`/ledgers/${ledger.id}/rules/${rule.id}`, body) : api.post(`/ledgers/${ledger.id}/rules`, body)
    },
    onSuccess: async () => {
      await invalidate()
      onDone()
    },
    onError: (error) => (error instanceof ApiError && error.isValidation ? setErrors(error.errors) : notifyError(error)),
  })

  const setTrigger = (index: number, patch: Partial<RuleTrigger>) =>
    setDraft({ ...draft, triggers: draft.triggers.map((item, position) => (position === index ? { ...item, ...patch } : item)) })
  const setAction = (index: number, patch: Partial<RuleAction>) =>
    setDraft({ ...draft, actions: draft.actions.map((item, position) => (position === index ? { ...item, ...patch } : item)) })

  return (
    <form
      className="space-y-6"
      onSubmit={(event) => {
        event.preventDefault()
        save.mutate()
      }}
    >
      <FieldGroup>
        <Field data-invalid={!!errors.title}>
          <FieldLabel htmlFor="rule-title">{t('Name')}</FieldLabel>
          <Input id="rule-title" autoFocus value={draft.title} onChange={(event) => setDraft({ ...draft, title: event.target.value })} />
          {errors.title && <FieldError>{errors.title[0]}</FieldError>}
        </Field>
        <div className="grid gap-4 sm:grid-cols-2">
          <Field>
            <FieldLabel>{t('Group')}</FieldLabel>
            <SelectField
              value={draft.rule_group_id}
              onChange={(value) => setDraft({ ...draft, rule_group_id: value })}
              options={Object.fromEntries(groups.map((group) => [String(group.id), group.title]))}
            />
          </Field>
          <Field>
            <FieldLabel>{t('Runs when a transaction is')}</FieldLabel>
            <SelectField
              value={draft.trigger_on}
              onChange={(value) => setDraft({ ...draft, trigger_on: value as Rule['trigger_on'] })}
              options={{ both: t('Added or changed'), store: t('Added'), update: t('Changed') }}
            />
          </Field>
        </div>

        <FieldSeparator>{t('When')}</FieldSeparator>
        <SelectField
          value={draft.strict ? 'all' : 'any'}
          onChange={(value) => setDraft({ ...draft, strict: value === 'all' })}
          options={{ all: t('All of these are true'), any: t('Any of these is true') }}
        />
        {draft.triggers.map((trigger, index) => (
          <div key={index} className="grid grid-cols-[1fr_auto] gap-2 sm:grid-cols-[14rem_1fr_auto_auto] sm:items-center">
            <SelectField value={trigger.type} onChange={(type) => setTrigger(index, { type })} options={triggerNames} className="col-span-2 sm:col-span-1" />
            {needsValue('triggers', trigger.type) ? (
              <Field data-invalid={!!errors[`triggers.${index}.value`]}>
                <Input value={trigger.value ?? ''} onChange={(event) => setTrigger(index, { value: event.target.value })} aria-label={t('Value')} />
              </Field>
            ) : (
              <span />
            )}
            <label className="text-muted-foreground flex items-center gap-1.5 text-xs">
              <Switch checked={trigger.negated} onCheckedChange={(negated) => setTrigger(index, { negated })} />
              {t('Not')}
            </label>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label={t('Remove')}
              disabled={draft.triggers.length === 1}
              onClick={() => setDraft({ ...draft, triggers: draft.triggers.filter((_, position) => position !== index) })}
            >
              <Trash2Icon />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-fit"
          onClick={() => setDraft({ ...draft, triggers: [...draft.triggers, { type: 'description_contains', value: '', negated: false }] })}
        >
          <PlusIcon />
          {t('Add condition')}
        </Button>

        <FieldSeparator>{t('Then')}</FieldSeparator>
        {draft.actions.map((action, index) => (
          <div key={index} className="grid grid-cols-[1fr_auto] gap-2 sm:grid-cols-[14rem_1fr_auto] sm:items-center">
            <SelectField value={action.type} onChange={(type) => setAction(index, { type })} options={actionNames} className="col-span-2 sm:col-span-1" />
            {needsValue('actions', action.type) ? (
              <Field data-invalid={!!errors[`actions.${index}.value`]}>
                <Input value={action.value ?? ''} onChange={(event) => setAction(index, { value: event.target.value })} aria-label={t('Value')} />
              </Field>
            ) : (
              <span />
            )}
            <Button
              type="button"
              variant="ghost"
              size="icon"
              aria-label={t('Remove')}
              disabled={draft.actions.length === 1}
              onClick={() => setDraft({ ...draft, actions: draft.actions.filter((_, position) => position !== index) })}
            >
              <Trash2Icon />
            </Button>
          </div>
        ))}
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-fit"
          onClick={() => setDraft({ ...draft, actions: [...draft.actions, { type: 'add_tag', value: '' }] })}
        >
          <PlusIcon />
          {t('Add action')}
        </Button>

        <FieldSeparator />
        <Field orientation="horizontal">
          <Switch id="rule-stop" checked={draft.stop_processing} onCheckedChange={(stop_processing) => setDraft({ ...draft, stop_processing })} />
          <FieldLabel htmlFor="rule-stop">{t('Skip the next rules in this group when this one matches')}</FieldLabel>
        </Field>
        <Field orientation="horizontal">
          <Switch id="rule-active" checked={draft.active} onCheckedChange={(active) => setDraft({ ...draft, active })} />
          <FieldLabel htmlFor="rule-active">{t('Active')}</FieldLabel>
        </Field>
        {Object.keys(errors).some((key) => key.endsWith('.value')) && <FieldError>{t('Fill in the values for the highlighted rows.')}</FieldError>}
      </FieldGroup>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="ghost" onClick={onDone}>
          {t('Cancel')}
        </Button>
        <Button type="submit" disabled={save.isPending}>
          {t('Save rule')}
        </Button>
      </div>
    </form>
  )
}
