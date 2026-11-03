import { createFileRoute, useNavigate } from '@tanstack/react-router'
import { z } from 'zod'
import { LabelDetail } from '@/components/app/label-detail'

export const Route = createFileRoute('/_app/$ledgerId/categories/$categoryId')({
  validateSearch: z.object({ page: z.number().int().positive().optional().catch(undefined) }),
  component: CategoryDetail,
})

function CategoryDetail() {
  const navigate = useNavigate({ from: Route.fullPath })
  const { categoryId } = Route.useParams()
  const { page = 1 } = Route.useSearch()

  return <LabelDetail kind="categories" id={categoryId} page={page} onPageChange={(next) => navigate({ search: { page: next } })} />
}
