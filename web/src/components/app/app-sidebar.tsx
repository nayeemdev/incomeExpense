import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate } from '@tanstack/react-router'
import {
  ArrowLeftRightIcon,
  BookOpenIcon,
  ChevronsUpDownIcon,
  CreditCardIcon,
  FolderIcon,
  HandCoinsIcon,
  LayoutDashboardIcon,
  LogOutIcon,
  MonitorIcon,
  MoonIcon,
  PlusIcon,
  SettingsIcon,
  ShoppingBagIcon,
  SunIcon,
  TagIcon,
  WalletIcon,
} from 'lucide-react'
import { useTheme } from '@/hooks/use-theme'
import { useTranslation } from 'react-i18next'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { LogoMark } from './logo'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from '@/components/ui/sidebar'
import { useLedger } from '@/hooks/use-ledger'
import { api } from '@/lib/api/client'
import { ledgersQuery, userQuery } from '@/lib/api/queries'
import type { Preferences } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { accountTypeLabels } from '@/lib/labels'
import { storage } from '@/lib/storage'

export function AppSidebar() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const ledgerId = String(ledger.id)
  const accountTypes = accountTypeLabels()

  const navigation = [
    { to: '/$ledgerId', label: t('Dashboard'), icon: LayoutDashboardIcon, exact: true },
    { to: '/$ledgerId/transactions', label: t('Transactions'), icon: ArrowLeftRightIcon },
  ] as const

  const accounts = [
    { type: 'asset', label: accountTypes.asset, icon: WalletIcon },
    { type: 'expense', label: accountTypes.expense, icon: ShoppingBagIcon },
    { type: 'revenue', label: accountTypes.revenue, icon: HandCoinsIcon },
    { type: 'liability', label: accountTypes.liability, icon: CreditCardIcon },
  ] as const

  const organise = [
    { to: '/$ledgerId/categories', label: t('Categories'), icon: FolderIcon },
    { to: '/$ledgerId/tags', label: t('Tags'), icon: TagIcon },
  ] as const

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader>
        <LedgerSwitcher />
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarMenu>
            {navigation.map((item) => (
              <SidebarMenuItem key={item.to}>
                <SidebarMenuButton asChild tooltip={item.label}>
                  <Link
                    to={item.to}
                    params={{ ledgerId }}
                    activeOptions={{ exact: 'exact' in item }}
                    activeProps={{ 'data-active': true }}
                  >
                    <item.icon />
                    <span>{item.label}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarGroup>
        <SidebarGroup>
          <SidebarGroupLabel>{t('Accounts')}</SidebarGroupLabel>
          <SidebarMenu>
            {accounts.map((item) => (
              <SidebarMenuItem key={item.type}>
                <SidebarMenuButton asChild tooltip={item.label}>
                  <Link
                    to="/$ledgerId/accounts"
                    params={{ ledgerId }}
                    search={{ type: item.type }}
                    activeOptions={{ includeSearch: true }}
                    activeProps={{ 'data-active': true }}
                  >
                    <item.icon />
                    <span>{item.label}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarGroup>
        <SidebarGroup>
          <SidebarGroupLabel>{t('Organise')}</SidebarGroupLabel>
          <SidebarMenu>
            {organise.map((item) => (
              <SidebarMenuItem key={item.to}>
                <SidebarMenuButton asChild tooltip={item.label}>
                  <Link to={item.to} params={{ ledgerId }} activeProps={{ 'data-active': true }}>
                    <item.icon />
                    <span>{item.label}</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            ))}
          </SidebarMenu>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton asChild tooltip={t('Settings')}>
              <Link to="/$ledgerId/settings" params={{ ledgerId }} activeProps={{ 'data-active': true }}>
                <SettingsIcon />
                <span>{t('Settings')}</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
          <UserMenu />
        </SidebarMenu>
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}

function LedgerSwitcher() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const ledger = useLedger()
  const { data: ledgers = [] } = useQuery(ledgersQuery)

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent">
              <LogoMark className="size-8" />
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{ledger.name}</span>
                <span className="text-muted-foreground truncate text-xs">{ledger.primary_currency.code}</span>
              </div>
              <ChevronsUpDownIcon className="ml-auto" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="min-w-56" align="start">
            <DropdownMenuLabel className="text-muted-foreground text-xs">{t('Ledgers')}</DropdownMenuLabel>
            {ledgers.map((item) => (
              <DropdownMenuItem
                key={item.id}
                onSelect={() => {
                  storage.set('ledger', String(item.id))
                  void navigate({ to: '/$ledgerId', params: { ledgerId: String(item.id) } })
                }}
              >
                <BookOpenIcon />
                {item.name}
              </DropdownMenuItem>
            ))}
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => void navigate({ to: '/onboarding' })}>
              <PlusIcon />
              {t('New ledger')}
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}

function UserMenu() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { theme, setTheme } = useTheme()
  const { data: user } = useQuery(userQuery)

  const updateTheme = useMutation({
    mutationFn: (value: Preferences['theme']) => api.patch('/user/preferences', { theme: value }),
    onMutate: (value) => setTheme(value),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userQuery.queryKey }),
    onError: notifyError,
  })

  const logout = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear()
      window.location.assign('/login')
    },
    onError: notifyError,
  })

  if (!user) return null

  const initials = user.name
    .split(' ')
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  return (
    <SidebarMenuItem>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton size="lg" className="data-[state=open]:bg-sidebar-accent">
            <Avatar className="size-8 rounded-lg">
              <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
              <span className="truncate font-medium">{user.name}</span>
              <span className="text-muted-foreground truncate text-xs">{user.email}</span>
            </div>
            <ChevronsUpDownIcon className="ml-auto" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent className="min-w-56" side="top" align="start">
          <DropdownMenuLabel className="text-muted-foreground text-xs">{t('Theme')}</DropdownMenuLabel>
          <DropdownMenuRadioGroup value={theme} onValueChange={(value) => updateTheme.mutate(value as Preferences['theme'])}>
            <DropdownMenuRadioItem value="light">
              <SunIcon /> {t('Light')}
            </DropdownMenuRadioItem>
            <DropdownMenuRadioItem value="dark">
              <MoonIcon /> {t('Dark')}
            </DropdownMenuRadioItem>
            <DropdownMenuRadioItem value="system">
              <MonitorIcon /> {t('System')}
            </DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={() => logout.mutate()}>
            <LogOutIcon />
            {t('Sign out')}
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  )
}
