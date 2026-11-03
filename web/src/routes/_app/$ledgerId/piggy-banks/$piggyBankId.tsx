import { useMutation, useQuery } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { MinusIcon, PencilIcon, PlusIcon, Trash2Icon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ConfirmDialog } from '@/components/app/confirm-dialog'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { ProgressBar } from '@/components/app/progress-bar'
import { PiggyBankForm } from '@/components/planning/piggy-bank-form'
import { PiggyBankMoveDialog } from '@/components/planning/piggy-bank-move-dialog'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Skeleton } from '@/components/ui/skeleton'
import { useCanEdit, useLedger, useLocale } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { piggyBankQuery } from '@/lib/api/queries'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'

export const Route = createFileRoute('/_app/$ledgerId/piggy-banks/$piggyBankId')({
  component: PiggyBankDetail,
})

function PiggyBankDetail() {
  const { t } = useTranslation()
  const locale = useLocale()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const invalidate = useInvalidateLedger(ledger.id)
  const [editing, setEditing] = useState(false)
  const [direction, setDirection] = useState<'add' | 'remove' | null>(null)
  const piggyBankId = Number(Route.useParams().piggyBankId)
  const { data: piggyBank } = useQuery(piggyBankQuery(ledger.id, piggyBankId))

  const remove = useMutation({
    mutationFn: () => api.delete(`/ledgers/${ledger.id}/piggy-banks/${piggyBankId}`),
    onSuccess: async () => {
      await navigate({ to: '/$ledgerId/piggy-banks', params: { ledgerId: String(ledger.id) } })
      await invalidate()
    },
    onError: notifyError,
  })

  if (!piggyBank) return <Skeleton className="h-64 w-full" />

  const currency = piggyBank.currency.code

  return (
    <>
      <PageHeader
        title={piggyBank.name}
        description={piggyBank.target_date ? t('Target date {{date}}', { date: formatDate(piggyBank.target_date, locale, { dateStyle: 'long' }) }) : undefined}
        actions={
          canEdit && (
            <>
              <Button onClick={() => setDirection('add')}>
                <PlusIcon />
                {t('Add money')}
              </Button>
              <Button variant="outline" onClick={() => setDirection('remove')} disabled={Number(piggyBank.saved_amount) <= 0}>
                <MinusIcon />
                {t('Take out')}
              </Button>
              <Button variant="outline" size="icon" aria-label={t('Edit')} onClick={() => setEditing(true)}>
                <PencilIcon />
              </Button>
              <ConfirmDialog
                trigger={
                  <Button variant="outline" size="icon" aria-label={t('Delete')}>
                    <Trash2Icon />
                  </Button>
                }
                title={t('Delete {{name}}?', { name: piggyBank.name })}
                description={t('The money stays in your accounts, it is just no longer set aside.')}
                onConfirm={() => remove.mutate()}
              />
            </>
          )
        }
      />
      <Card className="mb-6">
        <CardContent className="space-y-3">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <Money amount={piggyBank.saved_amount} currency={currency} tone="none" className="text-3xl font-semibold" />
            {piggyBank.left_to_save && (
              <span className="text-muted-foreground text-sm">
                {t('{{amount}} to go', { amount: new Intl.NumberFormat(locale, { style: 'currency', currency }).format(Number(piggyBank.left_to_save)) })}
              </span>
            )}
          </div>
          {piggyBank.target_amount && <ProgressBar value={Number(piggyBank.saved_amount)} max={Number(piggyBank.target_amount)} className="h-3" />}
        </CardContent>
      </Card>
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>{t('Saved in')}</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="divide-y">
              {piggyBank.accounts.map((account) => (
                <li key={account.id} className="flex justify-between py-2 text-sm">
                  <span>{account.name}</span>
                  <Money amount={account.current_amount} currency={currency} tone="none" />
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>{t('History')}</CardTitle>
          </CardHeader>
          <CardContent>
            {piggyBank.events?.length ? (
              <ul className="divide-y">
                {piggyBank.events.map((event) => (
                  <li key={event.id} className="flex justify-between gap-2 py-2 text-sm">
                    <span className="text-muted-foreground">
                      {formatDate(event.date, locale)} · {event.account.name}
                    </span>
                    <Money amount={event.amount} currency={currency} tone={event.amount.startsWith('-') ? 'negative' : 'positive'} />
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-muted-foreground text-sm">{t('Nothing set aside yet.')}</p>
            )}
          </CardContent>
        </Card>
      </div>
      <PiggyBankMoveDialog piggyBank={piggyBank} direction={direction} onClose={() => setDirection(null)} />
      <Sheet open={editing} onOpenChange={setEditing}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>{t('Edit {{name}}', { name: piggyBank.name })}</SheetTitle>
          </SheetHeader>
          <div className="px-4 pb-4">
            <PiggyBankForm piggyBank={piggyBank} onCancel={() => setEditing(false)} onSaved={() => setEditing(false)} />
          </div>
        </SheetContent>
      </Sheet>
    </>
  )
}
