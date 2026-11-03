import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { LabelDetail } from '@/components/app/label-detail'

export const Route = createFileRoute('/_app/$ledgerId/tags/$tagId')({
  validateSearch: z.object({ page: z.number().int().positive().optional().catch(undefined) }),
  component: TagDetail,
})

function TagDetail() {
  const navigate = useNavigate({ from: Route.fullPath })
  const { tagId } = Route.useParams()
  const { page = 1 } = Route.useSearch()

  return <LabelDetail kind="tags" id={Number(tagId)} page={page} onPageChange={(next) => navigate({ search: { page: next } })} />
}
