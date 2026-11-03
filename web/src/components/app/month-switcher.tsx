import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

type MonthSwitcherProps = { isCurrent: boolean; onChange: (offset: number | null) => void }

export function MonthSwitcher({ isCurrent, onChange }: MonthSwitcherProps) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-1">
      <Button variant="outline" size="icon" aria-label={t('Previous month')} onClick={() => onChange(-1)}>
        <ChevronLeftIcon />
      </Button>
      <Button variant="outline" onClick={() => onChange(null)} disabled={isCurrent}>
        {t('This month')}
      </Button>
      <Button variant="outline" size="icon" aria-label={t('Next month')} onClick={() => onChange(1)}>
        <ChevronRightIcon />
      </Button>
    </div>
  )
}
