// Shared helpers for the LedgerFly Scriptable scripts. Import with importModule('ledgerfly').

const KEYS = {
  url: 'ledgerfly.url',
  token: 'ledgerfly.token',
  ledger: 'ledgerfly.ledger',
  currency: 'ledgerfly.currency',
}

function read() {
  return {
    url: Keychain.get(KEYS.url),
    token: Keychain.get(KEYS.token),
    ledger: Keychain.get(KEYS.ledger),
    currency: Keychain.contains(KEYS.currency) ? Keychain.get(KEYS.currency) : null,
  }
}

function isConfigured() {
  return [KEYS.url, KEYS.token, KEYS.ledger].every((key) => Keychain.contains(key))
}

async function ask(title, message, { placeholder = '', value = '', secure = false } = {}) {
  const alert = new Alert()
  alert.title = title
  alert.message = message
  secure ? alert.addSecureTextField(placeholder, value) : alert.addTextField(placeholder, value)
  alert.addAction('Continue')
  alert.addCancelAction('Cancel')

  if ((await alert.presentAlert()) === -1) throw new Error('Cancelled')

  return alert.textFieldValue(0).trim()
}

async function request(settings, method, path, body) {
  const req = new Request(`${settings.url}/api/v1${path}`)
  req.method = method
  req.headers = {
    Authorization: `Bearer ${settings.token}`,
    Accept: 'application/json',
    'Content-Type': 'application/json',
  }

  if (body !== undefined) req.body = JSON.stringify(body)

  const json = await req.loadJSON()
  const status = req.response.statusCode

  if (status >= 400) {
    const firstError = json.errors ? Object.values(json.errors)[0][0] : null
    throw new Error(firstError || json.message || `Request failed with status ${status}`)
  }

  return json
}

async function configure() {
  const url = (await ask('LedgerFly address', 'The address you open LedgerFly at.', { placeholder: 'https://ledger.example.com' })).replace(/\/+$/, '')
  const token = await ask('Access token', 'Create a "Quick entry only" token under Settings → Tokens.', { secure: true })
  const ledgers = (await request({ url, token }, 'GET', '/ledgers')).data

  if (ledgers.length === 0) throw new Error('This account has no ledgers yet.')

  let ledger = ledgers[0]

  if (ledgers.length > 1) {
    const picker = new Alert()
    picker.title = 'Which ledger?'
    ledgers.forEach((item) => picker.addAction(item.name))
    picker.addCancelAction('Cancel')
    const index = await picker.presentSheet()

    if (index === -1) throw new Error('Cancelled')
    ledger = ledgers[index]
  }

  Keychain.set(KEYS.url, url)
  Keychain.set(KEYS.token, token)
  Keychain.set(KEYS.ledger, String(ledger.id))
  Keychain.set(KEYS.currency, ledger.primary_currency.code)

  return read()
}

async function config({ reset = false } = {}) {
  return !reset && isConfigured() ? read() : configure()
}

function formatMoney(amount, currency) {
  try {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(Number(amount))
  } catch {
    return `${currency} ${amount}`
  }
}

module.exports = { config, isConfigured, request, ask, formatMoney }
