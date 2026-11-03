import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

type PaginationProps = {
  page: number
  lastPage: number
  onChange: (page: number) => void
}

export function Pagination({ page, lastPage, onChange }: PaginationProps) {
  const { t } = useTranslation()

  if (lastPage <= 1) return null

  return (
    <nav className="mt-6 flex items-center justify-between gap-2" aria-label={t('Pagination')}>
      <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
        <ChevronLeftIcon />
        {t('Newer')}
      </Button>
      <span className="text-muted-foreground text-sm">{t('Page {{page}} of {{last}}', { page, last: lastPage })}</span>
      <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => onChange(page + 1)}>
        {t('Older')}
        <ChevronRightIcon />
      </Button>
    </nav>
  )
}
