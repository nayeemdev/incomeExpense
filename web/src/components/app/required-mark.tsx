import { useTranslation } from 'react-i18next'

export function RequiredMark() {
  const { t } = useTranslation()

  return (
    <span className="text-destructive" title={t('Required')}>
      *<span className="sr-only">{t('(required)')}</span>
    </span>
  )
}
