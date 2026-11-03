import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { DownloadIcon } from 'lucide-react'
import { useNavigate } from '@tanstack/react-router'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { z } from 'zod'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { CurrencySelect } from '@/components/app/currency-select'
import { FormField } from '@/components/app/form-field'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { FieldGroup } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { useLedger } from '@/hooks/use-ledger'
import { api, downloadUrl } from '@/lib/api/client'
import { ledgersQuery } from '@/lib/api/queries'
import { handleFormError, notifyError } from '@/lib/forms'

const schema = z.object({
  name: z.string().trim().min(1).max(255),
  primary_currency_id: z.number(),
  fiscal_year_start: z.string().regex(/^\d{2}-\d{2}$/, 'Use MM-DD'),
})

export function LedgerSettings() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const ledger = useLedger()
  const manage = ledger.role === 'owner'
  const form = useForm({
    resolver: zodResolver(schema),
    values: { name: ledger.name, primary_currency_id: ledger.primary_currency.id, fiscal_year_start: ledger.fiscal_year_start },
  })

  const save = useMutation({
    mutationFn: (values: z.infer<typeof schema>) => api.patch(`/ledgers/${ledger.id}`, values),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['ledgers'] })
      toast.success(t('Ledger updated'))
    },
    onError: (error) => handleFormError(error, form.setError),
  })

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}`),
    onSuccess: async () => {
      queryClient.removeQueries({ queryKey: ['ledgers', ledger.id] })
      await queryClient.invalidateQueries({ queryKey: ledgersQuery.queryKey })
      await navigate({ to: '/' })
    },
    onError: notifyError,
  })

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{t('Ledger')}</CardTitle>
          <CardDescription>{manage ? t('Only owners can change these settings.') : t('Ask an owner to change these settings.')}</CardDescription>
        </CardHeader>
        <form onSubmit={form.handleSubmit((values) => save.mutate(values))}>
          <CardContent>
            <FieldGroup>
              <FormField control={form.control} name="name" required label={t('Name')}>
                {(field) => <Input disabled={!manage} {...field} />}
              </FormField>
              <FormField control={form.control} name="primary_currency_id" required label={t('Primary currency')}>
                {(field) => <CurrencySelect id={field.id} value={field.value} onChange={field.onChange} />}
              </FormField>
              <FormField
                control={form.control}
                name="fiscal_year_start" required
                label={t('Fiscal year starts on')}
                description={t('Month and day, for example 07-01 for 1 July.')}
              >
                {(field) => <Input disabled={!manage} placeholder="MM-DD" className="max-w-32" {...field} />}
              </FormField>
            </FieldGroup>
          </CardContent>
          {manage && (
            <CardFooter className="mt-6 justify-end">
              <Button type="submit" disabled={save.isPending}>
                {t('Save changes')}
              </Button>
            </CardFooter>
          )}
        </form>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('Export')}</CardTitle>
          <CardDescription>{t('Download your data at any time. The backup contains everything in this ledger.')}</CardDescription>
        </CardHeader>
        <CardFooter className="flex flex-wrap gap-2">
          <Button variant="outline" asChild>
            <a href={downloadUrl(`/ledgers/${ledger.id}/export/transactions.csv`)}>
              <DownloadIcon />
              {t('Transactions (CSV)')}
            </a>
          </Button>
          <Button variant="outline" asChild>
            <a href={downloadUrl(`/ledgers/${ledger.id}/export/backup.json`)}>
              <DownloadIcon />
              {t('Full backup (JSON)')}
            </a>
          </Button>
        </CardFooter>
      </Card>

      {manage && (
        <Card className="border-destructive/50">
          <CardHeader>
            <CardTitle>{t('Delete ledger')}</CardTitle>
            <CardDescription>{t('Deletes every account, transaction and attachment in this ledger for all members.')}</CardDescription>
          </CardHeader>
          <CardFooter>
            <ConfirmDialog
              trigger={<Button variant="destructive">{t('Delete ledger')}</Button>}
              title={t('Delete {{name}}?', { name: ledger.name })}
              description={t('This cannot be undone.')}
              onConfirm={() => remove.mutate()}
            />
          </CardFooter>
        </Card>
      )}
    </div>
  )
}
