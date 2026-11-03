import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { PlusIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useFieldArray, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { CurrencySelect } from '@/components/app/currency-select'
import { FormField } from '@/components/app/form-field'
import { Logo } from '@/components/app/logo'
import { MoneyInput } from '@/components/app/money-input'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { api } from '@/lib/api/client'
import { ledgersQuery } from '@/lib/api/queries'
import type { AssetRole, Ledger, Resource } from '@/lib/api/types'
import { todayIso } from '@/lib/format'
import { handleFormError } from '@/lib/forms'
import { assetRoleLabels } from '@/lib/labels'
import { storage } from '@/lib/storage'

export const Route = createFileRoute('/_app/onboarding')({
  component: Onboarding,
})

const ledgerSchema = z.object({
  name: z.string().min(1).max(255),
  primary_currency_id: z.string({ error: 'Choose a currency' }),
})

const accountsSchema = z.object({
  accounts: z.array(
    z.object({
      name: z.string().min(1).max(255),
      asset_role: z.enum(['default', 'shared', 'savings', 'cash_wallet', 'credit_card']),
      opening_balance: z.string(),
    }),
  ),
})

function Onboarding() {
  const { t } = useTranslation()
  const [ledger, setLedger] = useState<Ledger | null>(null)

  return (
    <div className="bg-muted/40 flex min-h-svh flex-col items-center justify-center gap-6 p-4">
      <Logo className="text-lg" />
      <div className="w-full max-w-lg">
        {ledger ? <AccountsStep ledger={ledger} /> : <LedgerStep onCreated={setLedger} />}
      </div>
      <p className="text-muted-foreground text-sm">{t('Step {{step}} of 2', { step: ledger ? 2 : 1 })}</p>
    </div>
  )
}

function LedgerStep({ onCreated }: { onCreated: (ledger: Ledger) => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const form = useForm<z.input<typeof ledgerSchema>, unknown, z.output<typeof ledgerSchema>>({
    resolver: zodResolver(ledgerSchema),
    defaultValues: { name: '' },
  })

  const create = useMutation({
    mutationFn: (values: z.output<typeof ledgerSchema>) => api.post<Resource<Ledger>>('/ledgers', values),
    onSuccess: async ({ data }) => {
      storage.set('ledger', data.id)
      await queryClient.invalidateQueries({ queryKey: ledgersQuery.queryKey })
      onCreated(data)
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Set up your ledger')}</CardTitle>
        <CardDescription>
          {t('A ledger holds your accounts and transactions. You can share it with your family later.')}
        </CardDescription>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => create.mutate(values))}>
        <CardContent>
          <FieldGroup>
            <FormField control={form.control} name="name" required label={t('Ledger name')}>
              {(field) => <Input autoFocus placeholder={t('e.g. Personal or Household')} {...field} />}
            </FormField>
            <FormField
              control={form.control}
              name="primary_currency_id" required
              label={t('Primary currency')}
              description={t('Reports and net worth are shown in this currency.')}
            >
              {(field) => <CurrencySelect id={field.id} value={field.value || null} onChange={field.onChange} aria-invalid={field['aria-invalid']} />}
            </FormField>
          </FieldGroup>
        </CardContent>
        <CardFooter className="mt-6 justify-end">
          <Button type="submit" disabled={create.isPending}>
            {t('Continue')}
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}

function AccountsStep({ ledger }: { ledger: Ledger }) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const roles = assetRoleLabels()
  const form = useForm<z.infer<typeof accountsSchema>>({
    resolver: zodResolver(accountsSchema),
    defaultValues: { accounts: [{ name: '', asset_role: 'default', opening_balance: '' }] },
  })
  const { fields, append, remove } = useFieldArray({ control: form.control, name: 'accounts' })

  const save = useMutation({
    mutationFn: async (values: z.infer<typeof accountsSchema>) => {
      for (const account of values.accounts) {
        await api.post(`/ledgers/${ledger.id}/accounts`, {
          type: 'asset',
          name: account.name,
          asset_role: account.asset_role,
          currency_id: ledger.primary_currency.id,
          opening_balance: account.opening_balance || null,
          opening_balance_date: account.opening_balance ? todayIso() : null,
        })
      }
    },
    onSuccess: () => navigate({ to: '/$ledgerId', params: { ledgerId: ledger.id } }),
    onError: (error) => handleFormError(error, form.setError),
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-xl">{t('Add your accounts')}</CardTitle>
        <CardDescription>
          {t('Add the bank accounts, cards and wallets you use, with what is in them today.')}
        </CardDescription>
      </CardHeader>
      <form onSubmit={form.handleSubmit((values) => save.mutate(values))}>
        <CardContent className="space-y-4">
          {fields.map((item, index) => (
            <div key={item.id} className="grid grid-cols-[1fr_auto] items-start gap-2 sm:grid-cols-[1fr_9rem_8rem_auto]">
              <FormField control={form.control} name={`accounts.${index}.name`} required className="col-span-2 sm:col-span-1">
                {(field) => <Input placeholder={t('Account name')} autoFocus={index === fields.length - 1} {...field} />}
              </FormField>
              <FormField control={form.control} name={`accounts.${index}.asset_role`}>
                {(field) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {(Object.keys(roles) as AssetRole[]).map((role) => (
                        <SelectItem key={role} value={role}>
                          {roles[role]}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              </FormField>
              <FormField control={form.control} name={`accounts.${index}.opening_balance`}>
                {(field) => (
                  <MoneyInput {...field} allowNegative placeholder={t('Balance')} currency={ledger.primary_currency.code} />
                )}
              </FormField>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label={t('Remove')}
                disabled={fields.length === 1}
                onClick={() => remove(index)}
              >
                <Trash2Icon />
              </Button>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => append({ name: '', asset_role: 'default', opening_balance: '' })}
          >
            <PlusIcon />
            {t('Add another account')}
          </Button>
        </CardContent>
        <CardFooter className="mt-6 justify-between">
          <Button
            type="button"
            variant="ghost"
            onClick={() => navigate({ to: '/$ledgerId', params: { ledgerId: ledger.id } })}
          >
            {t('Skip for now')}
          </Button>
          <Button type="submit" disabled={save.isPending}>
            {t('Finish')}
          </Button>
        </CardFooter>
      </form>
    </Card>
  )
}
