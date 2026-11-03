import { createFileRoute } from '@tanstack/react-router'
import { LabelList } from '@/components/app/label-list'

export const Route = createFileRoute('/_app/$ledgerId/tags/')({
  component: () => <LabelList kind="tags" />,
})
