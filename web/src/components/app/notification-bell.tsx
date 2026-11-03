import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from '@tanstack/react-router'
import { BellIcon, CheckCheckIcon } from 'lucide-react'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { api } from '@/lib/api/client'
import { notificationsQuery } from '@/lib/api/queries'
import type { AppNotification } from '@/lib/api/types'
import { cn } from '@/lib/utils'

function timeAgo(date: string, locale?: string) {
  const seconds = Math.round((new Date(date).getTime() - Date.now()) / 1000)
  const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  const format = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' })

  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return format.format(Math.round(seconds / size), unit)
  }

  return format.format(0, 'minute')
}

export function NotificationBell() {
  const { t, i18n } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const { data } = useQuery(notificationsQuery)
  const unread = data?.meta.unread ?? 0
  const refresh = () => queryClient.invalidateQueries({ queryKey: notificationsQuery.queryKey })

  const readAll = useMutation({ mutationFn: () => api.post('/notifications/read-all'), onSuccess: refresh })

  const openNotification = async (notification: AppNotification) => {
    setOpen(false)
    if (!notification.read_at) await api.post(`/notifications/${notification.id}/read`).then(refresh)
    await navigate({ to: notification.path })
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button variant="ghost" size="icon" className="relative" aria-label={t('Notifications ({{count}} unread)', { count: unread })}>
          <BellIcon />
          {unread > 0 && (
            <span className="bg-destructive text-destructive-foreground absolute top-1 right-1 grid min-w-4 place-items-center rounded-full px-1 text-[10px] leading-4 font-medium">
              {unread > 9 ? '9+' : unread}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-80 p-0">
        <div className="flex items-center justify-between border-b px-3 py-2">
          <span className="text-sm font-medium">{t('Notifications')}</span>
          <Button variant="ghost" size="sm" disabled={unread === 0} onClick={() => readAll.mutate()}>
            <CheckCheckIcon />
            {t('Mark all read')}
          </Button>
        </div>
        {data?.data.length ? (
          <ul className="max-h-96 divide-y overflow-y-auto">
            {data.data.map((notification) => (
              <li key={notification.id}>
                <button
                  type="button"
                  className={cn('hover:bg-muted/60 flex w-full gap-2 px-3 py-2.5 text-left', !notification.read_at && 'bg-muted/30')}
                  onClick={() => void openNotification(notification)}
                >
                  <span className={cn('mt-1.5 size-2 shrink-0 rounded-full', notification.read_at ? 'bg-transparent' : 'bg-primary')} />
                  <span className="min-w-0">
                    <span className="block text-sm font-medium">{notification.title}</span>
                    <span className="text-muted-foreground block text-xs">{notification.body}</span>
                    <span className="text-muted-foreground mt-0.5 block text-[11px]">{timeAgo(notification.created_at, i18n.language)}</span>
                  </span>
                </button>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-muted-foreground px-3 py-8 text-center text-sm">{t('You are all caught up.')}</p>
        )}
      </PopoverContent>
    </Popover>
  )
}
