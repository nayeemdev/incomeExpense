// LedgerFly quick add. Run it from the home screen, Siri, a Shortcut or the summary widget.
// Shortcut input (optional): a dictionary with amount, description and optionally type ("withdrawal" or "deposit") and category.

const ledgerfly = importModule('ledgerfly')

async function collect() {
  const input = args.shortcutParameter

  if (input && typeof input === 'object' && input.amount && input.description) {
    return {
      amount: String(input.amount).replace(',', '.'),
      description: String(input.description),
      type: input.type === 'deposit' ? 'deposit' : 'withdrawal',
      category: input.category ? String(input.category) : null,
    }
  }

  const typeAlert = new Alert()
  typeAlert.title = 'Add to LedgerFly'
  typeAlert.addAction('Expense')
  typeAlert.addAction('Income')
  typeAlert.addCancelAction('Cancel')
  const typeIndex = await typeAlert.presentSheet()

  if (typeIndex === -1) throw new Error('Cancelled')

  const details = new Alert()
  details.title = typeIndex === 0 ? 'New expense' : 'New income'
  details.message = 'Category and accounts are taken from the last time you used this description.'
  details.addTextField('Amount')
  details.addTextField('Description')
  details.addTextField('Category (optional)')
  details.addAction('Save')
  details.addCancelAction('Cancel')

  if ((await details.presentAlert()) === -1) throw new Error('Cancelled')

  return {
    amount: details.textFieldValue(0).trim().replace(',', '.'),
    description: details.textFieldValue(1).trim(),
    type: typeIndex === 0 ? 'withdrawal' : 'deposit',
    category: details.textFieldValue(2).trim() || null,
  }
}

async function run() {
  const settings = await ledgerfly.config({ reset: args.queryParameters.reset === '1' })
  const entry = await collect()
  const response = await ledgerfly.request(settings, 'POST', `/ledgers/${settings.ledger}/quick-entry`, entry)
  const saved = response.data.transactions[0]
  const amount = ledgerfly.formatMoney(saved.amount, saved.currency.code)
  const counterpart = saved.type === 'deposit' ? saved.source.name : saved.destination.name

  const notification = new Notification()
  notification.title = `Saved ${amount}`
  notification.body = `${saved.description} · ${counterpart}${saved.category ? ` · ${saved.category.name}` : ''}`
  await notification.schedule()

  Script.setShortcutOutput(`${amount} ${saved.description}`)
}

try {
  await run()
} catch (error) {
  if (error.message !== 'Cancelled') {
    const alert = new Alert()
    alert.title = 'Could not save'
    alert.message = error.message
    alert.addAction('OK')
    await alert.presentAlert()
  }
} finally {
  Script.complete()
}
