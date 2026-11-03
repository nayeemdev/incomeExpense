import type { QueryClient } from '@tanstack/react-query'
import { Outlet, createRootRouteWithContext } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyTitle } from '@/components/ui/empty'

export const Route = createRootRouteWithContext<{ queryClient: QueryClient }>()({
  component: Outlet,
  notFoundComponent: NotFound,
})

function NotFound() {
  const { t } = useTranslation()

  return (
    <Empty className="min-h-svh">
      <EmptyHeader>
        <EmptyTitle>{t('Page not found')}</EmptyTitle>
        <EmptyDescription>{t('The page you are looking for does not exist or was moved.')}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>
        <Button asChild>
          <a href="/">{t('Go home')}</a>
        </Button>
      </EmptyContent>
    </Empty>
  )
}
