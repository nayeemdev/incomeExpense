import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { PageHeader } from '@/components/app/page-header'
import { ExchangeRateSettings } from '@/components/settings/exchange-rate-settings'
import { LedgerSettings } from '@/components/settings/ledger-settings'
import { MemberSettings } from '@/components/settings/member-settings'
import { NotificationSettings } from '@/components/settings/notification-settings'
import { PreferenceSettings } from '@/components/settings/preference-settings'
import { ProfileSettings } from '@/components/settings/profile-settings'
import { SecuritySettings } from '@/components/settings/security-settings'
import { TokenSettings } from '@/components/settings/token-settings'
import { WebhookSettings } from '@/components/settings/webhook-settings'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

const tabs = ['ledger', 'members', 'profile', 'security', 'notifications', 'preferences', 'exchange-rates', 'tokens', 'webhooks'] as const

export const Route = createFileRoute('/_app/$ledgerId/settings')({
  validateSearch: z.object({ tab: z.enum(tabs).optional().catch(undefined) }),
  component: Settings,
})

function Settings() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const { tab = 'ledger' } = Route.useSearch()
  const labels: Record<(typeof tabs)[number], string> = {
    ledger: t('Ledger'),
    members: t('Members'),
    profile: t('Profile'),
    security: t('Security'),
    notifications: t('Notifications'),
    preferences: t('Preferences'),
    'exchange-rates': t('Exchange rates'),
    tokens: t('Tokens'),
    webhooks: t('Webhooks'),
  }

  return (
    <>
      <PageHeader title={t('Settings')} />
      <Tabs value={tab} onValueChange={(value) => navigate({ search: { tab: value as (typeof tabs)[number] }, replace: true })}>
        <TabsList className="mb-4 max-w-full overflow-x-auto">
          {tabs.map((value) => (
            <TabsTrigger key={value} value={value}>
              {labels[value]}
            </TabsTrigger>
          ))}
        </TabsList>
        <div className="max-w-3xl">
          <TabsContent value="ledger"><LedgerSettings /></TabsContent>
          <TabsContent value="members"><MemberSettings /></TabsContent>
          <TabsContent value="profile"><ProfileSettings /></TabsContent>
          <TabsContent value="security"><SecuritySettings /></TabsContent>
          <TabsContent value="preferences"><PreferenceSettings /></TabsContent>
          <TabsContent value="notifications"><NotificationSettings /></TabsContent>
          <TabsContent value="exchange-rates"><ExchangeRateSettings /></TabsContent>
          <TabsContent value="tokens"><TokenSettings /></TabsContent>
          <TabsContent value="webhooks"><WebhookSettings /></TabsContent>
        </div>
      </Tabs>
    </>
  )
}
