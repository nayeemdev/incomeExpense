import { CircleHelpIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

export function SearchHelp({ onInsert }: { onInsert: (snippet: string) => void }) {
  const { t } = useTranslation()
  const examples: Array<[string, string]> = [
    ['coffee', t('Description contains the words')],
    ['category:"Eating out"', t('In a category (quotes for spaces)')],
    ['-tag:work', t('A minus sign excludes')],
    ['amount_more:50', t('Amount above, also amount_less: and amount:')],
    ['date:2026-03', t('A day, month or year; also date_before: and date_after:')],
    ['account:Visa', t('Either side; or source: and destination:')],
    ['budget:Food bill:Rent', t('Budget or bill')],
    ['type:deposit', t('withdrawal, deposit or transfer')],
    ['has:attachments', t('Also has:tags, has:notes, has:category, has:budget, has:bill')],
    ['currency:EUR notes:refund', t('Currency code or text in notes')],
  ]

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('Search help')}>
          <CircleHelpIcon />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-96 max-w-[calc(100vw-2rem)]">
        <p className="mb-2 text-sm font-medium">{t('Search filters')}</p>
        <ul className="space-y-1.5">
          {examples.map(([snippet, description]) => (
            <li key={snippet} className="flex items-start gap-2 text-sm">
              <button type="button" className="bg-muted hover:bg-accent shrink-0 rounded px-1.5 py-0.5 font-mono text-xs" onClick={() => onInsert(snippet)}>
                {snippet}
              </button>
              <span className="text-muted-foreground text-xs leading-5">{description}</span>
            </li>
          ))}
        </ul>
      </PopoverContent>
    </Popover>
  )
}
