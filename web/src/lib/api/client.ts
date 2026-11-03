export type ValidationErrors = Record<string, string[]>

export class ApiError extends Error {
  readonly status: number
  readonly errors: ValidationErrors
  readonly body: unknown

  constructor(status: number, message: string, errors: ValidationErrors = {}, body: unknown = null) {
    super(message)
    this.status = status
    this.errors = errors
    this.body = body
  }

  get isValidation() {
    return this.status === 422
  }

  get isUnauthenticated() {
    return this.status === 401
  }
}

type Query = Record<string, string | number | boolean | null | undefined | Array<string | number>>

type RequestOptions = {
  query?: Query
  body?: unknown
  signal?: AbortSignal
}

const API_PREFIX = '/api/v1'
const unsafeMethods = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

function readCookie(name: string) {
  const match = document.cookie.split('; ').find((row) => row.startsWith(`${name}=`))

  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

let csrfReady: Promise<void> | null = null

export function ensureCsrfCookie(force = false) {
  if (force || !csrfReady || !readCookie('XSRF-TOKEN')) {
    csrfReady = fetch('/sanctum/csrf-cookie', { credentials: 'include' }).then(() => undefined)
  }

  return csrfReady
}

function buildUrl(path: string, query?: Query) {
  const url = new URL(path.startsWith('/') ? `${API_PREFIX}${path}` : `${API_PREFIX}/${path}`, window.location.origin)

  for (const [key, value] of Object.entries(query ?? {})) {
    if (value === null || value === undefined || value === '') continue

    if (Array.isArray(value)) {
      value.forEach((item) => url.searchParams.append(`${key}[]`, String(item)))
    } else {
      url.searchParams.set(key, String(value))
    }
  }

  return url.pathname + url.search
}

async function request<T>(method: string, path: string, options: RequestOptions = {}, retried = false): Promise<T> {
  if (unsafeMethods.has(method)) await ensureCsrfCookie()

  const isForm = options.body instanceof FormData
  const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const xsrf = readCookie('XSRF-TOKEN')

  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
  if (options.body !== undefined && !isForm) headers['Content-Type'] = 'application/json'

  const response = await fetch(buildUrl(path, options.query), {
    method,
    headers,
    credentials: 'include',
    signal: options.signal,
    body: options.body === undefined ? undefined : isForm ? (options.body as FormData) : JSON.stringify(options.body),
  })

  if (response.status === 419 && !retried) {
    await ensureCsrfCookie(true)

    return request<T>(method, path, options, true)
  }

  if (response.status === 204) return undefined as T

  const body = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null

  if (!response.ok) {
    throw new ApiError(response.status, body?.message ?? response.statusText, body?.errors ?? {}, body)
  }

  return body as T
}

export const api = {
  get: <T>(path: string, query?: Query, signal?: AbortSignal) => request<T>('GET', path, { query, signal }),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, { body }),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, { body }),
  patch: <T>(path: string, body?: unknown) => request<T>('PATCH', path, { body }),
  delete: <T = void>(path: string) => request<T>('DELETE', path),
}

export function downloadUrl(path: string) {
  return buildUrl(path)
}
