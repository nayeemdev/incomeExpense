import { createFileRoute } from '@tanstack/react-router'
import { LabelList } from '@/components/app/label-list'

export const Route = createFileRoute('/_app/$ledgerId/categories/')({
  component: () => <LabelList kind="categories" />,
})
