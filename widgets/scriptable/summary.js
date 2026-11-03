// LedgerFly home and lock screen widget: spent this month, income and account balances.
// Tapping the widget opens quick-add.js.

const ledgerfly = importModule('ledgerfly')

function isoDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function total(amounts, currency) {
  const match = amounts.find((item) => item.currency === currency) ?? amounts[0]

  return match ? ledgerfly.formatMoney(match.amount, match.currency) : ledgerfly.formatMoney(0, currency)
}

function addRow(stack, label, value, { size = 12, bold = false } = {}) {
  const row = stack.addStack()
  row.centerAlignContent()
  const left = row.addText(label)
  left.font = Font.systemFont(size - 1)
  left.textColor = Color.gray()
  left.lineLimit = 1
  row.addSpacer()
  const right = row.addText(value)
  right.font = bold ? Font.boldSystemFont(size) : Font.mediumSystemFont(size)
  right.lineLimit = 1
  right.minimumScaleFactor = 0.6
}

async function buildWidget() {
  const widget = new ListWidget()
  widget.url = `scriptable:///run/${encodeURIComponent('quick-add')}`
  widget.setPadding(14, 14, 14, 14)

  if (!ledgerfly.isConfigured()) {
    const text = widget.addText('Run quick-add once in Scriptable to connect LedgerFly.')
    text.font = Font.systemFont(12)

    return widget
  }

  const settings = await ledgerfly.config()
  const now = new Date()
  const start = isoDate(new Date(now.getFullYear(), now.getMonth(), 1))
  const end = isoDate(new Date(now.getFullYear(), now.getMonth() + 1, 0))
  const { data } = await ledgerfly.request(settings, 'GET', `/ledgers/${settings.ledger}/summary?start=${start}&end=${end}`)
  const currency = settings.currency ?? data.spent[0]?.currency
  const family = config.widgetFamily ?? 'medium'

  if (family.startsWith('accessory')) {
    const label = widget.addText('Spent')
    label.font = Font.systemFont(11)
    const value = widget.addText(total(data.spent, currency))
    value.font = Font.boldSystemFont(14)
    value.minimumScaleFactor = 0.5

    return widget
  }

  const title = widget.addText('LedgerFly')
  title.font = Font.semiboldSystemFont(11)
  title.textColor = Color.gray()
  widget.addSpacer(6)

  const spentLabel = widget.addText('Spent this month')
  spentLabel.font = Font.systemFont(11)
  spentLabel.textColor = Color.gray()
  const spent = widget.addText(total(data.spent, currency))
  spent.font = Font.boldSystemFont(family === 'small' ? 18 : 22)
  spent.minimumScaleFactor = 0.5
  widget.addSpacer(6)

  addRow(widget, 'Income', total(data.earned, currency))

  if (family !== 'small') {
    data.accounts.slice(0, family === 'large' ? 6 : 2).forEach((account) => {
      addRow(widget, account.name, ledgerfly.formatMoney(account.balance, account.currency?.code ?? currency))
    })
  }

  widget.addSpacer()
  widget.refreshAfterDate = new Date(Date.now() + 30 * 60 * 1000)

  return widget
}

let widget

try {
  widget = await buildWidget()
} catch (error) {
  widget = new ListWidget()
  widget.addText(`LedgerFly: ${error.message}`).font = Font.systemFont(11)
}

if (config.runsInWidget) {
  Script.setWidget(widget)
} else {
  await widget.presentMedium()
}

Script.complete()
