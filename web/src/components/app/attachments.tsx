import { useMutation, useQuery } from '@tanstack/react-query'
import { DownloadIcon, PaperclipIcon, Trash2Icon, UploadIcon } from 'lucide-react'
import { useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Card, CardAction, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { useCanEdit, useLedger } from '@/hooks/use-ledger'
import { api, downloadUrl } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { attachmentsQuery } from '@/lib/api/queries'
import type { Attachment } from '@/lib/api/types'
import { notifyError } from '@/lib/forms'

function formatSize(bytes: number) {
  const units = ['B', 'KB', 'MB', 'GB']
  const index = Math.min(Math.floor(Math.log(Math.max(bytes, 1)) / Math.log(1024)), units.length - 1)

  return `${(bytes / 1024 ** index).toFixed(index ? 1 : 0)} ${units[index]}`
}

type AttachmentsProps = { type: Attachment['attachable_type']; id: number }

export function Attachments({ type, id }: AttachmentsProps) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const canEdit = useCanEdit()
  const input = useRef<HTMLInputElement>(null)
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: attachments = [] } = useQuery(attachmentsQuery(ledger.id, type, id))

  const upload = useMutation({
    mutationFn: async (files: FileList) => {
      for (const file of Array.from(files)) {
        const body = new FormData()
        body.append('file', file)
        body.append('attachable_type', type)
        body.append('attachable_id', String(id))
        await api.post(`/ledgers/${ledger.id}/attachments`, body)
      }
    },
    onSettled: () => {
      invalidate()
      if (input.current) input.current.value = ''
    },
    onError: notifyError,
  })

  const remove = useMutation({
    mutationFn: (attachmentId: number) => api.delete(`/ledgers/${ledger.id}/attachments/${attachmentId}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <PaperclipIcon className="size-4" />
          {t('Attachments')}
        </CardTitle>
        {canEdit && (
          <CardAction>
            <input ref={input} type="file" multiple hidden onChange={(event) => event.target.files && upload.mutate(event.target.files)} />
            <Button variant="outline" size="sm" disabled={upload.isPending} onClick={() => input.current?.click()}>
              <UploadIcon />
              {t('Upload')}
            </Button>
          </CardAction>
        )}
      </CardHeader>
      <CardContent>
        {attachments.length === 0 ? (
          <p className="text-muted-foreground text-sm">{t('Receipts, invoices or statements you attach appear here.')}</p>
        ) : (
          <ul className="divide-y">
            {attachments.map((attachment) => (
              <li key={attachment.id} className="flex items-center gap-3 py-2">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{attachment.title ?? attachment.filename}</p>
                  <p className="text-muted-foreground text-xs">{formatSize(attachment.size)}</p>
                </div>
                <Button variant="ghost" size="icon" asChild aria-label={t('Download')}>
                  <a href={downloadUrl(`/ledgers/${ledger.id}/attachments/${attachment.id}/download`)}>
                    <DownloadIcon />
                  </a>
                </Button>
                {canEdit && (
                  <Button variant="ghost" size="icon" aria-label={t('Delete')} onClick={() => remove.mutate(attachment.id)}>
                    <Trash2Icon />
                  </Button>
                )}
              </li>
            ))}
          </ul>
        )}
      </CardContent>
    </Card>
  )
}
