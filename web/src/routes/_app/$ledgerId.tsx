import { Outlet, createFileRoute, notFound } from '@tanstack/react-router'
import { PlusIcon, SearchIcon } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { AppSidebar } from '@/components/app/app-sidebar'
import { CommandPalette } from '@/components/app/command-palette'
import { NotificationBell } from '@/components/app/notification-bell'
import { QuickAddContext, type QuickAddDefaults } from '@/components/app/quick-add-context'
import { QuickAddDialog } from '@/components/app/quick-add-dialog'
import { Button } from '@/components/ui/button'
import { Kbd } from '@/components/ui/kbd'
import { SidebarInset, SidebarProvider, SidebarTrigger } from '@/components/ui/sidebar'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { useHotkey } from '@/hooks/use-hotkey'
import { useOnline } from '@/hooks/use-online'
import { ApiError } from '@/lib/api/client'
import { ledgerQuery } from '@/lib/api/queries'
import { storage } from '@/lib/storage'

export const Route = createFileRoute('/_app/$ledgerId')({
  loader: async ({ context, params }) => {
    try {
      await context.queryClient.ensureQueryData(ledgerQuery(Number(params.ledgerId)))
    } catch (error) {
      if (error instanceof ApiError && (error.status === 403 || error.status === 404)) throw notFound()

      throw error
    }
  },
  component: LedgerLayout,
})

function LedgerLayout() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const online = useOnline()
  const [paletteOpen, setPaletteOpen] = useState(false)
  const [quickAdd, setQuickAdd] = useState<{ open: boolean; defaults: QuickAddDefaults }>({ open: false, defaults: {} })

  const openQuickAdd = useCallback((defaults: QuickAddDefaults = {}) => {
    if (canEdit) setQuickAdd({ open: true, defaults })
  }, [canEdit])

  useEffect(() => storage.set('ledger', String(ledger.id)), [ledger.id])
  useHotkey('k', useCallback(() => setPaletteOpen((open) => !open), []), { meta: true })
  useHotkey('n', useCallback(() => openQuickAdd(), [openQuickAdd]))

  return (
    <QuickAddContext.Provider value={openQuickAdd}>
      <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
          <header className="bg-background/80 sticky top-0 z-20 flex min-h-14 items-center gap-2 border-b px-4 pt-[env(safe-area-inset-top)] backdrop-blur">
            <SidebarTrigger className="-ml-1" />
            <Button
              variant="outline"
              className="text-muted-foreground w-full max-w-sm justify-start gap-2 font-normal"
              onClick={() => setPaletteOpen(true)}
            >
              <SearchIcon />
              <span className="truncate">{t('Search or jump to…')}</span>
              <Kbd className="ml-auto hidden sm:inline-flex">⌘K</Kbd>
            </Button>
            <div className="ml-auto" />
            <NotificationBell />
            {canEdit && (
              <Button onClick={() => openQuickAdd()}>
                <PlusIcon />
                <span className="hidden sm:inline">{t('Add')}</span>
                <Kbd className="bg-primary-foreground/15 text-primary-foreground hidden sm:inline-flex">N</Kbd>
              </Button>
            )}
          </header>
          {!online && (
            <div role="status" className="bg-amber-500/15 text-amber-700 dark:text-amber-300 px-4 py-2 text-center text-sm">
              {t('You are offline. Changes cannot be saved until the connection is back.')}
            </div>
          )}
          <div className="mx-auto w-full max-w-6xl flex-1 p-4 pb-[max(1rem,env(safe-area-inset-bottom))] md:p-6">
            <Outlet />
          </div>
        </SidebarInset>
      </SidebarProvider>
      <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />
      <QuickAddDialog
        open={quickAdd.open}
        defaults={quickAdd.defaults}
        onOpenChange={(open) => setQuickAdd((state) => ({ ...state, open }))}
      />
    </QuickAddContext.Provider>
  )
}
