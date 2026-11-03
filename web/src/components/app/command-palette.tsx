import { useQuery } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import {
  ArrowDownLeftIcon,
  ArrowLeftRightIcon,
  ArrowUpRightIcon,
  FolderIcon,
  LayoutDashboardIcon,
  SettingsIcon,
  TagIcon,
  WalletIcon,
} from 'lucide-react'
import { useTranslation } from 'react-i18next'
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/components/ui/command'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { accountsQuery, categoriesQuery, tagsQuery } from '@/lib/api/queries'
import { accountTypeLabels } from '@/lib/labels'
import { useQuickAdd } from './quick-add-context'

type CommandPaletteProps = { open: boolean; onOpenChange: (open: boolean) => void }

export function CommandPalette({ open, onOpenChange }: CommandPaletteProps) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const quickAdd = useQuickAdd()
  const ledgerId = String(ledger.id)
  const typeLabels = accountTypeLabels()
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id), enabled: open, select: (page) => page.data })
  const { data: categories = [] } = useQuery({ ...categoriesQuery(ledger.id), enabled: open, select: (page) => page.data })
  const { data: tags = [] } = useQuery({ ...tagsQuery(ledger.id), enabled: open, select: (page) => page.data })

  const run = (action: () => unknown) => {
    onOpenChange(false)
    action()
  }

  return (
    <CommandDialog open={open} onOpenChange={onOpenChange} title={t('Command palette')} description={t('Search or jump anywhere')}>
      <CommandInput placeholder={t('Type a command or search…')} />
      <CommandList>
        <CommandEmpty>{t('Nothing found.')}</CommandEmpty>
        {canEdit && (
          <CommandGroup heading={t('Create')}>
            <CommandItem onSelect={() => run(() => quickAdd({ type: 'withdrawal' }))}>
              <ArrowUpRightIcon />
              {t('New expense')}
              <CommandShortcut>N</CommandShortcut>
            </CommandItem>
            <CommandItem onSelect={() => run(() => quickAdd({ type: 'deposit' }))}>
              <ArrowDownLeftIcon />
              {t('New income')}
            </CommandItem>
            <CommandItem onSelect={() => run(() => quickAdd({ type: 'transfer' }))}>
              <ArrowLeftRightIcon />
              {t('New transfer')}
            </CommandItem>
            <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId/transactions/new', params: { ledgerId } }))}>
              <ArrowLeftRightIcon />
              {t('New split transaction')}
            </CommandItem>
          </CommandGroup>
        )}
        <CommandGroup heading={t('Go to')}>
          <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId', params: { ledgerId } }))}>
            <LayoutDashboardIcon />
            {t('Dashboard')}
          </CommandItem>
          <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId/transactions', params: { ledgerId } }))}>
            <ArrowLeftRightIcon />
            {t('Transactions')}
          </CommandItem>
          {(['asset', 'expense', 'revenue', 'liability'] as const).map((type) => (
            <CommandItem
              key={type}
              onSelect={() => run(() => navigate({ to: '/$ledgerId/accounts', params: { ledgerId }, search: { type } }))}
            >
              <WalletIcon />
              {typeLabels[type]}
            </CommandItem>
          ))}
          <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId/categories', params: { ledgerId } }))}>
            <FolderIcon />
            {t('Categories')}
          </CommandItem>
          <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId/tags', params: { ledgerId } }))}>
            <TagIcon />
            {t('Tags')}
          </CommandItem>
          <CommandItem onSelect={() => run(() => navigate({ to: '/$ledgerId/settings', params: { ledgerId } }))}>
            <SettingsIcon />
            {t('Settings')}
          </CommandItem>
        </CommandGroup>
        {accounts.length > 0 && (
          <>
            <CommandSeparator />
            <CommandGroup heading={t('Accounts')}>
              {accounts.map((account) => (
                <CommandItem
                  key={account.id}
                  value={`account ${account.name} ${account.id}`}
                  onSelect={() =>
                    run(() =>
                      navigate({
                        to: '/$ledgerId/accounts/$accountId',
                        params: { ledgerId, accountId: String(account.id) },
                      }),
                    )
                  }
                >
                  <WalletIcon />
                  {account.name}
                  <CommandShortcut>{typeLabels[account.type]}</CommandShortcut>
                </CommandItem>
              ))}
            </CommandGroup>
          </>
        )}
        {categories.length > 0 && (
          <CommandGroup heading={t('Categories')}>
            {categories.map((category) => (
              <CommandItem
                key={category.id}
                value={`category ${category.name} ${category.id}`}
                onSelect={() =>
                  run(() =>
                    navigate({
                      to: '/$ledgerId/categories/$categoryId',
                      params: { ledgerId, categoryId: String(category.id) },
                    }),
                  )
                }
              >
                <FolderIcon />
                {category.name}
              </CommandItem>
            ))}
          </CommandGroup>
        )}
        {tags.length > 0 && (
          <CommandGroup heading={t('Tags')}>
            {tags.map((tag) => (
              <CommandItem
                key={tag.id}
                value={`tag ${tag.name} ${tag.id}`}
                onSelect={() =>
                  run(() => navigate({ to: '/$ledgerId/tags/$tagId', params: { ledgerId, tagId: String(tag.id) } }))
                }
              >
                <TagIcon />
                {tag.name}
              </CommandItem>
            ))}
          </CommandGroup>
        )}
      </CommandList>
    </CommandDialog>
  )
}
