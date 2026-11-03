import { useMutation, useQuery } from '@tanstack/react-query'
import { Link, createFileRoute, useNavigate } from '@tanstack/react-router'
import { CheckCircle2Icon, DownloadIcon, FileUpIcon, LoaderIcon, Trash2Icon, TriangleAlertIcon } from 'lucide-react'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Combobox } from '@/components/app/combobox'
import { Money } from '@/components/app/money'
import { PageHeader } from '@/components/app/page-header'
import { SectionCard } from '@/components/app/section-card'
import { SelectField } from '@/components/app/select-field'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/components/ui/field'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useLedger, useLocale } from '@/hooks/use-ledger'
import { ApiError, api, downloadUrl } from '@/lib/api/client'
import { useInvalidateLedger } from '@/lib/api/mutations'
import { accountsQuery, ledgerKey } from '@/lib/api/queries'
import type { ImportConfig, ImportJob, ImportPreviewRow, Resource } from '@/lib/api/types'
import { formatDate } from '@/lib/format'
import { notifyError } from '@/lib/forms'
import { cn } from '@/lib/utils'

export const Route = createFileRoute('/_app/$ledgerId/import/')({
  validateSearch: z.object({ job: z.number().int().optional().catch(undefined) }),
  component: ImportPage,
})

const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'd-m-Y', 'Y/m/d', 'd/m/y', 'm/d/y', 'j M Y', 'M j, Y', 'Ymd']

function ImportPage() {
  const { t } = useTranslation()
  const navigate = useNavigate({ from: Route.fullPath })
  const ledger = useLedger()
  const { job: jobId } = Route.useSearch()
  const { data: overview } = useQuery({
    queryKey: [...ledgerKey(ledger.id), 'imports'],
    queryFn: () => api.get<{ data: ImportJob[]; profiles: Array<{ id: number; name: string; config: ImportConfig }> }>(`/ledgers/${ledger.id}/imports`),
  })
  const { data: job } = useQuery({
    queryKey: [...ledgerKey(ledger.id), 'imports', jobId],
    queryFn: () => api.get<Resource<ImportJob>>(`/ledgers/${ledger.id}/imports/${jobId}`).then((r) => r.data),
    enabled: jobId !== undefined,
    refetchInterval: (query) => (['queued', 'running'].includes(query.state.data?.status ?? '') ? 1500 : false),
  })

  const upload = useMutation({
    mutationFn: (file: File) => {
      const body = new FormData()
      body.append('file', file)

      return api.post<Resource<ImportJob>>(`/ledgers/${ledger.id}/imports`, body).then((r) => r.data)
    },
    onSuccess: (created) => navigate({ search: { job: created.id } }),
    onError: notifyError,
  })

  return (
    <>
      <PageHeader
        title={t('Import')}
        description={t('Bring in transactions from your bank as CSV or camt.053 (ISO 20022) files.')}
        actions={jobId !== undefined && (
          <Button variant="outline" onClick={() => navigate({ search: {} })}>
            {t('New import')}
          </Button>
        )}
      />
      {jobId === undefined ? (
        <div className="space-y-6">
          <UploadCard uploading={upload.isPending} onFile={(file) => upload.mutate(file)} />
          <TemplateCard />
          <HistoryCard jobs={overview?.data ?? []} onOpen={(id) => navigate({ search: { job: id } })} />
        </div>
      ) : !job ? (
        <LoaderIcon className="text-muted-foreground animate-spin" />
      ) : job.status === 'uploaded' ? (
        <ImportSetup job={job} profiles={overview?.profiles ?? []} />
      ) : (
        <ImportResult job={job} />
      )}
    </>
  )
}

function UploadCard({ uploading, onFile }: { uploading: boolean; onFile: (file: File) => void }) {
  const { t } = useTranslation()
  const [dragging, setDragging] = useState(false)

  return (
    <label
      className={cn(
        'hover:bg-muted/40 flex cursor-pointer flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed p-10 text-center transition-colors',
        dragging && 'border-primary bg-muted/40',
      )}
      onDragOver={(event) => {
        event.preventDefault()
        setDragging(true)
      }}
      onDragLeave={() => setDragging(false)}
      onDrop={(event) => {
        event.preventDefault()
        setDragging(false)
        const file = event.dataTransfer.files[0]
        if (file) onFile(file)
      }}
    >
      {uploading ? <LoaderIcon className="text-muted-foreground size-8 animate-spin" /> : <FileUpIcon className="text-muted-foreground size-8" />}
      <span className="font-medium">{t('Drop a file here or click to choose one')}</span>
      <span className="text-muted-foreground text-sm">{t('CSV, TXT or camt.053 XML from your bank')}</span>
      <input type="file" accept=".csv,.txt,.tsv,.xml" className="sr-only" disabled={uploading} onChange={(event) => event.target.files?.[0] && onFile(event.target.files[0])} />
    </label>
  )
}

function TemplateCard() {
  const { t } = useTranslation()
  const ledger = useLedger()
  const columns: Array<[string, string]> = [
    ['date', t('Day of the transaction as YYYY-MM-DD, for example 2026-03-15.')],
    ['description', t('What it was for.')],
    ['amount', t('Negative for money going out, positive for money coming in. Use a dot for decimals.')],
    ['payee', t('Who you paid or who paid you. Optional.')],
    ['category', t('Created if it does not exist yet. Optional.')],
    ['tags', t('Separate several tags with commas, inside quotes. Optional.')],
    ['notes', t('Any extra text. Optional.')],
    ['reference', t("Your bank's ID for the transaction. Used to skip it if you import it twice. Optional.")],
  ]

  return (
    <SectionCard
      title={t('Import template')}
      description={t('Your bank has no export? Fill in this template in any spreadsheet app, save it as CSV and upload it. The columns are mapped for you.')}
      actions={
        <Button variant="outline" asChild>
          <a href={downloadUrl(`/ledgers/${ledger.id}/imports/template.csv`)}>
            <DownloadIcon />
            {t('Download template')}
          </a>
        </Button>
      }
      contentClassName="p-0"
    >
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>{t('Column')}</TableHead>
            <TableHead>{t('What to put in it')}</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {columns.map(([column, help]) => (
            <TableRow key={column}>
              <TableCell className="font-mono text-xs">{column}</TableCell>
              <TableCell className="text-muted-foreground whitespace-normal">{help}</TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </SectionCard>
  )
}

function HistoryCard({ jobs, onOpen }: { jobs: ImportJob[]; onOpen: (id: number) => void }) {
  const { t } = useTranslation()
  const locale = useLocale()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const remove = useMutation({
    mutationFn: (id: number) => api.delete(`/ledgers/${ledger.id}/imports/${id}`),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  if (jobs.length === 0) return null

  return (
    <Card>
      <CardHeader>
        <CardTitle>{t('Recent imports')}</CardTitle>
      </CardHeader>
      <CardContent>
        <ul className="divide-y">
          {jobs.map((job) => (
            <li key={job.id} className="flex items-center gap-3 py-2 text-sm">
              <button type="button" className="min-w-0 flex-1 truncate text-left font-medium hover:underline" onClick={() => onOpen(job.id)}>
                {job.filename}
              </button>
              <ImportStatus job={job} />
              <span className="text-muted-foreground hidden text-xs sm:inline">{formatDate(job.created_at, locale)}</span>
              <Button variant="ghost" size="icon" className="size-7" aria-label={t('Delete')} onClick={() => remove.mutate(job.id)}>
                <Trash2Icon />
              </Button>
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}

function ImportStatus({ job }: { job: ImportJob }) {
  const { t } = useTranslation()
  const labels: Record<ImportJob['status'], string> = {
    uploaded: t('Not run yet'),
    queued: t('Waiting'),
    running: t('Importing'),
    completed: t('{{count}} added', { count: job.stats?.created ?? 0 }),
    failed: t('Failed'),
  }

  return <Badge variant={job.status === 'failed' ? 'destructive' : job.status === 'completed' ? 'secondary' : 'outline'}>{labels[job.status]}</Badge>
}

function ImportSetup({ job, profiles }: { job: ImportJob; profiles: Array<{ id: number; name: string; config: ImportConfig }> }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const invalidate = useInvalidateLedger(ledger.id)
  const { data: accounts = [] } = useQuery({ ...accountsQuery(ledger.id, { type: ['asset', 'liability'], active: true }), select: (page) => page.data })
  const csv = job.format === 'csv'
  const [config, setConfig] = useState<ImportConfig>({
    account_id: ledger.default_account_id ?? undefined,
    columns: job.suggested_columns ?? {},
    has_header: true,
    date_format: null,
    decimal_separator: null,
    negate: false,
    skip_duplicates: true,
    apply_rules: true,
  })
  const [profileName, setProfileName] = useState('')
  const [preview, setPreview] = useState<{ data: ImportPreviewRow[]; meta: { total: number; invalid: number; duplicates: number } } | null>(null)
  const fields: Record<string, string> = {
    none: t('Skip'),
    date: t('Date'),
    description: t('Description'),
    amount: t('Amount (+/-)'),
    debit: t('Money out'),
    credit: t('Money in'),
    counterparty: t('Payee'),
    category: t('Category'),
    notes: t('Notes'),
    tags: t('Tags'),
    external_id: t('Bank reference'),
  }
  const columnCount = Math.max(...(job.sample ?? [[]]).map((row) => row.length), 0)
  const fieldForColumn = useMemo(
    () => Object.fromEntries(Object.entries(config.columns ?? {}).filter(([, index]) => index !== null).map(([field, index]) => [index, field])),
    [config.columns],
  )

  const body = { ...config, save_profile_as: profileName || undefined }

  const runPreview = useMutation({
    mutationFn: () => api.post<{ data: ImportPreviewRow[]; meta: { total: number; invalid: number; duplicates: number } }>(`/ledgers/${ledger.id}/imports/${job.id}/preview`, body),
    onSuccess: setPreview,
    onError: (error) => (error instanceof ApiError && error.isValidation ? notifyError(new Error(Object.values(error.errors)[0]?.[0] ?? error.message)) : notifyError(error)),
  })

  const run = useMutation({
    mutationFn: () => api.post(`/ledgers/${ledger.id}/imports/${job.id}/run`, body),
    onSuccess: () => invalidate(),
    onError: notifyError,
  })

  const setColumn = (index: number, field: string) => {
    const columns = Object.fromEntries(Object.entries(config.columns ?? {}).filter(([, value]) => value !== index))
    if (field !== 'none') columns[field] = index
    setConfig({ ...config, columns })
    setPreview(null)
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>{job.filename}</CardTitle>
          <CardDescription>{csv ? t('Tell LedgerFly which column is which. Columns were guessed from the header.') : t('camt.053 statements need no mapping.')}</CardDescription>
        </CardHeader>
        <CardContent>
          <FieldGroup>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field>
                <FieldLabel>{t('Import into')}</FieldLabel>
                <Combobox
                  options={accounts.map((account) => ({ value: String(account.id), label: account.name, hint: account.currency?.code }))}
                  value={config.account_id ? String(config.account_id) : null}
                  onChange={(value) => {
                    setConfig({ ...config, account_id: value ? Number(value) : undefined })
                    setPreview(null)
                  }}
                  placeholder={t('Choose an account')}
                />
              </Field>
              {csv && profiles.length > 0 && (
                <Field>
                  <FieldLabel>{t('Saved mapping')}</FieldLabel>
                  <SelectField
                    value=""
                    placeholder={t('Use a saved mapping')}
                    options={Object.fromEntries(profiles.map((profile) => [String(profile.id), profile.name]))}
                    onChange={(id) => {
                      const profile = profiles.find((item) => String(item.id) === id)
                      if (profile) setConfig({ ...config, ...profile.config, skip_duplicates: true })
                      setPreview(null)
                    }}
                  />
                </Field>
              )}
            </div>
            {csv && (
              <>
                <div className="surface-card overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        {Array.from({ length: columnCount }, (_, index) => (
                          <TableHead key={index} className="min-w-36 p-2">
                            <SelectField value={fieldForColumn[index] ?? 'none'} options={fields} onChange={(field) => setColumn(index, field)} />
                          </TableHead>
                        ))}
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(job.sample ?? []).map((row, rowIndex) => (
                        <TableRow key={rowIndex} className={cn(rowIndex === 0 && config.has_header && 'text-muted-foreground font-medium')}>
                          {Array.from({ length: columnCount }, (_, index) => (
                            <TableCell key={index} className="max-w-48 truncate text-xs">
                              {row[index]}
                            </TableCell>
                          ))}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                  <Field>
                    <FieldLabel>{t('Date format')}</FieldLabel>
                    <SelectField
                      value={config.date_format ?? 'auto'}
                      options={{ auto: t('Detect'), ...Object.fromEntries(DATE_FORMATS.map((format) => [format, format])) }}
                      onChange={(value) => setConfig({ ...config, date_format: value === 'auto' ? null : value })}
                    />
                  </Field>
                  <Field>
                    <FieldLabel>{t('Decimal separator')}</FieldLabel>
                    <SelectField
                      value={config.decimal_separator ?? 'auto'}
                      options={{ auto: t('Detect'), '.': t('Dot (1,234.56)'), ',': t('Comma (1.234,56)') }}
                      onChange={(value) => setConfig({ ...config, decimal_separator: value === 'auto' ? null : value })}
                    />
                  </Field>
                  <div className="space-y-3 pt-6">
                    <Field orientation="horizontal">
                      <Switch id="has-header" checked={config.has_header} onCheckedChange={(has_header) => setConfig({ ...config, has_header })} />
                      <FieldLabel htmlFor="has-header">{t('First row is a header')}</FieldLabel>
                    </Field>
                    <Field orientation="horizontal">
                      <Switch id="negate" checked={config.negate} onCheckedChange={(negate) => setConfig({ ...config, negate })} />
                      <FieldLabel htmlFor="negate">{t('Expenses are positive')}</FieldLabel>
                    </Field>
                  </div>
                </div>
              </>
            )}
          </FieldGroup>
        </CardContent>
        <CardFooter className="justify-end">
          <Button disabled={!config.account_id || runPreview.isPending} onClick={() => runPreview.mutate()}>
            {t('Preview')}
          </Button>
        </CardFooter>
      </Card>

      {preview && (
        <Card>
          <CardHeader>
            <CardTitle>{t('Preview')}</CardTitle>
            <CardDescription className="flex flex-wrap gap-2">
              <Badge variant="secondary">{t('{{count}} rows', { count: preview.meta.total })}</Badge>
              {preview.meta.duplicates > 0 && <Badge variant="outline">{t('{{count}} already in LedgerFly', { count: preview.meta.duplicates })}</Badge>}
              {preview.meta.invalid > 0 && <Badge variant="destructive">{t('{{count}} unreadable', { count: preview.meta.invalid })}</Badge>}
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="surface-card max-h-[28rem] overflow-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('Date')}</TableHead>
                    <TableHead>{t('Description')}</TableHead>
                    <TableHead>{t('Payee')}</TableHead>
                    <TableHead className="text-right">{t('Amount')}</TableHead>
                    <TableHead>{t('Status')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {preview.data.map((row) => (
                    <TableRow key={row.line} className={cn(row.duplicate && 'opacity-50')}>
                      <TableCell className="whitespace-nowrap">{row.date ?? '?'}</TableCell>
                      <TableCell className="max-w-64 truncate">{row.description}</TableCell>
                      <TableCell className="max-w-40 truncate">{row.counterparty}</TableCell>
                      <TableCell className="text-right">
                        {row.amount !== null ? <Money amount={row.amount} currency={accounts.find((account) => account.id === config.account_id)?.currency?.code ?? ledger.primary_currency.code} /> : '?'}
                      </TableCell>
                      <TableCell>
                        {row.errors.length > 0 ? (
                          <Badge variant="destructive">{t('Check {{fields}}', { fields: row.errors.join(', ') })}</Badge>
                        ) : row.duplicate ? (
                          <Badge variant="outline">{t('Duplicate')}</Badge>
                        ) : (
                          <Badge variant="secondary">{t('New')}</Badge>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
            <FieldGroup>
              <Field orientation="horizontal">
                <Switch id="skip-duplicates" checked={config.skip_duplicates} onCheckedChange={(skip_duplicates) => setConfig({ ...config, skip_duplicates })} />
                <FieldLabel htmlFor="skip-duplicates">{t('Skip rows that are already in LedgerFly')}</FieldLabel>
              </Field>
              <Field orientation="horizontal">
                <Switch id="apply-rules" checked={config.apply_rules} onCheckedChange={(apply_rules) => setConfig({ ...config, apply_rules })} />
                <FieldLabel htmlFor="apply-rules">{t('Run my rules on imported transactions')}</FieldLabel>
              </Field>
              {csv && (
                <Field className="max-w-sm">
                  <FieldLabel htmlFor="profile-name">{t('Save this mapping as')}</FieldLabel>
                  <Input id="profile-name" placeholder={t('e.g. My bank')} value={profileName} onChange={(event) => setProfileName(event.target.value)} />
                  <FieldDescription>{t('Optional. Reuse it next time you import from this bank.')}</FieldDescription>
                </Field>
              )}
            </FieldGroup>
          </CardContent>
          <CardFooter className="justify-end">
            <Button disabled={run.isPending || preview.meta.total === preview.meta.invalid} onClick={() => run.mutate()}>
              {t('Import {{count}} transactions', { count: preview.meta.total - preview.meta.invalid - (config.skip_duplicates ? preview.meta.duplicates : 0) })}
            </Button>
          </CardFooter>
        </Card>
      )}
    </div>
  )
}

function ImportResult({ job }: { job: ImportJob }) {
  const { t } = useTranslation()
  const ledger = useLedger()
  const busy = job.status === 'queued' || job.status === 'running'

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {busy ? (
            <LoaderIcon className="size-5 animate-spin" />
          ) : job.status === 'failed' ? (
            <TriangleAlertIcon className="text-destructive size-5" />
          ) : (
            <CheckCircle2Icon className="size-5 text-emerald-600" />
          )}
          {busy ? t('Importing {{file}}…', { file: job.filename }) : job.status === 'failed' ? t('The import failed') : t('Import finished')}
        </CardTitle>
        {job.error && <CardDescription>{job.error}</CardDescription>}
      </CardHeader>
      {job.stats && (
        <CardContent className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <Badge variant="secondary">{t('{{count}} added', { count: job.stats.created })}</Badge>
            <Badge variant="outline">{t('{{count}} duplicates skipped', { count: job.stats.duplicates })}</Badge>
            {job.stats.failed > 0 && <Badge variant="destructive">{t('{{count}} not imported', { count: job.stats.failed })}</Badge>}
          </div>
          {job.stats.failures.length > 0 && (
            <ul className="text-muted-foreground max-h-60 space-y-1 overflow-auto text-sm">
              {job.stats.failures.map((failure) => (
                <li key={failure.line}>{t('Line {{line}}: {{message}}', failure)}</li>
              ))}
            </ul>
          )}
        </CardContent>
      )}
      {!busy && job.config?.account_id && (
        <CardFooter>
          <Button asChild>
            <Link to="/$ledgerId/accounts/$accountId" params={{ ledgerId: String(ledger.id), accountId: String(job.config.account_id) }}>
              {t('View the account')}
            </Link>
          </Button>
        </CardFooter>
      )}
    </Card>
  )
}
