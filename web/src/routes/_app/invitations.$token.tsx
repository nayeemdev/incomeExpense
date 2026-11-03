import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { useTranslation } from 'react-i18next'
import { Logo } from '@/components/app/logo'
import { Button } from '@/components/ui/button'
import { Card, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { api } from '@/lib/api/client'
import type { Ledger, LedgerInvitation, Resource } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'
import { roleLabels } from '@/lib/labels'

export const Route = createFileRoute('/_app/invitations/$token')({
  component: AcceptInvitation,
})

function AcceptInvitation() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { token } = Route.useParams()
  const { data: invitation, isError } = useQuery({
    queryKey: ['invitations', token],
    queryFn: () => api.get<Resource<LedgerInvitation>>(`/invitations/${token}`).then((r) => r.data),
    retry: false,
  })

  const accept = useMutation({
    mutationFn: () => api.post<Resource<Ledger>>(`/invitations/${token}/accept`).then((r) => r.data),
    onSuccess: async (ledger) => {
      await queryClient.invalidateQueries({ queryKey: ['ledgers'] })
      await navigate({ to: '/$ledgerId', params: { ledgerId: ledger.id } })
    },
    onError: notifyError,
  })

  return (
    <div className="bg-muted/40 flex min-h-svh flex-col items-center justify-center gap-6 p-4">
      <Logo className="text-lg" />
      <Card className="w-full max-w-sm">
        {isError ? (
          <CardHeader>
            <CardTitle>{t('Invitation not found')}</CardTitle>
            <CardDescription>{t('It may have expired or been used already. Ask for a new one.')}</CardDescription>
          </CardHeader>
        ) : !invitation ? (
          <CardHeader>
            <Skeleton className="h-16 w-full" />
          </CardHeader>
        ) : (
          <>
            <CardHeader>
              <CardTitle>{t('Join {{ledger}}', { ledger: invitation.ledger?.name })}</CardTitle>
              <CardDescription>
                {t('{{name}} invited you as {{role}}.', {
                  name: invitation.invited_by,
                  role: roleLabels()[invitation.role].toLowerCase(),
                })}
              </CardDescription>
            </CardHeader>
            <CardFooter>
              <Button className="w-full" disabled={accept.isPending} onClick={() => accept.mutate()}>
                {t('Accept invitation')}
              </Button>
            </CardFooter>
          </>
        )}
      </Card>
    </div>
  )
}
