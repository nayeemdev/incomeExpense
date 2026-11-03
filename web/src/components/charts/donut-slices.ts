const SLICES = 6

export type DonutSlice = { key: string; name: string; value: number; color: string }

/**
 * The largest categories in fixed palette order, with the rest folded into "Other" so colors stay distinguishable.
 */
export function donutSlices(rows: Array<{ id: string | null; name: string | null; total: string }>, otherLabel: string, noneLabel: string): DonutSlice[] {
  const sorted = [...rows].map((row) => ({ ...row, value: Number(row.total) })).filter((row) => row.value > 0).sort((a, b) => b.value - a.value)
  const slices = sorted.slice(0, SLICES).map((row, index) => ({
    key: String(row.id ?? 'none'),
    name: row.name ?? noneLabel,
    value: row.value,
    color: `var(--chart-${index + 1})`,
  }))
  const rest = sorted.slice(SLICES).reduce((sum, row) => sum + row.value, 0)

  return rest > 0 ? [...slices, { key: 'other', name: otherLabel, value: rest, color: 'var(--muted-foreground)' }] : slices
}
