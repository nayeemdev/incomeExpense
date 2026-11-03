import { useQuery } from '@tanstack/react-query'
import { Link, createFileRoute } from '@tanstack/react-router'
import { PlusIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { ProgressBar } from '@/components/app/progress-bar'
import { PiggyBankForm } from '@/components/planning/piggy-bank-form'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'
import { FormDialog } from '@/components/app/form-dialog'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { piggyBanksQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'

export const Route = createFileRoute('/_app/$ledgerId/piggy-banks/')({
  component: PiggyBanks,
})

function PiggyBanks() {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const [creating, setCreating] = useState(false)
  const { data: piggyBanks } = useQuery(piggyBanksQuery(ledger.id))

  return (
    <>
      <PageHeader
        title={t('Piggy banks')}
        description={t('Set money aside for goals without moving it out of your accounts.')}
        actions={
          canEdit && (
            <Button onClick={() => setCreating(true)}>
              <PlusIcon />
              {t('New piggy bank')}
            </Button>
          )
        }
      />
      {!piggyBanks && <Skeleton className="h-48 w-full" />}
      {piggyBanks?.length === 0 && (
        <Empty className="surface-card">
          <EmptyHeader>
            <EmptyTitle>{t('No piggy banks yet')}</EmptyTitle>
            <EmptyDescription>{t('Save towards a holiday, a new phone or an emergency fund.')}</EmptyDescription>
          </EmptyHeader>
          {canEdit && (
            <EmptyContent>
              <Button onClick={() => setCreating(true)}>{t('New piggy bank')}</Button>
            </EmptyContent>
          )}
        </Empty>
      )}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {piggyBanks?.map((piggyBank) => (
          <Card key={piggyBank.id} className="relative">
            <CardHeader>
              <CardTitle>
                <Link
                  to="/$ledgerId/piggy-banks/$piggyBankId"
                  params={{ ledgerId: String(ledger.id), piggyBankId: String(piggyBank.id) }}
                  className="after:absolute after:inset-0"
                >
                  {piggyBank.name}
                </Link>
              </CardTitle>
              {piggyBank.target_date && (
                <CardDescription>{t('By {{date}}', { date: formatDate(piggyBank.target_date, locale) })}</CardDescription>
              )}
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="flex items-baseline justify-between gap-2">
                <Money amount={piggyBank.saved_amount} currency={piggyBank.currency.code} tone="none" className="text-xl font-semibold" />
                {piggyBank.target_amount && (
                  <span className="text-muted-foreground text-sm">
                    {t('of')} <Money amount={piggyBank.target_amount} currency={piggyBank.currency.code} tone="none" />
                  </span>
                )}
              </div>
              {piggyBank.target_amount && <ProgressBar value={Number(piggyBank.saved_amount)} max={Number(piggyBank.target_amount)} />}
            </CardContent>
          </Card>
        ))}
      </div>
      <FormDialog open={creating} onOpenChange={setCreating} title={t('New piggy bank')} size="lg">
        <PiggyBankForm onCancel={() => setCreating(false)} onSaved={() => setCreating(false)} />
      </FormDialog>
    </>
  )
}
