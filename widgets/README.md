# Quick entry on iOS

Add expenses and income from the home screen, lock screen, Siri, Back Tap or the Action Button without opening LedgerFly. Everything goes through the `POST /api/v1/ledgers/{ledger}/quick-entry` endpoint: send an amount and a description, and LedgerFly fills in the accounts, category and tags from the last transaction with the same description (or from your default quick entry account, set under Settings → Preferences).

Both options below use an access token. Create one under **Settings → Tokens** with the **Quick entry only** scope: it can add transactions and read the dashboard summary, nothing else.

## Option 1: Scriptable (widget + quick add)

[Scriptable](https://scriptable.app) runs JavaScript on iOS and can show home and lock screen widgets.

1. Install Scriptable from the App Store.
2. Copy the three files in `scriptable/` into Scriptable (iCloud Drive → Scriptable), keeping their names: `ledgerfly.js`, `quick-add.js`, `summary.js`.
3. Run `quick-add` once inside Scriptable. It asks for your LedgerFly address, the token and (if you have several) the ledger, and stores them in the iOS Keychain.
4. Add a Scriptable widget to the home or lock screen, choose the `summary` script. It shows what you spent this month, income and your account balances. Tapping it opens quick add.

To reconnect or switch ledgers, run `scriptable:///run/quick-add?reset=1`.

## Option 2: Shortcuts only

No extra app needed. In the Shortcuts app, create a shortcut with these actions:

1. **Ask for Input**: type Number, prompt "Amount".
2. **Ask for Input**: type Text, prompt "What for?".
3. **Get Contents of URL**
   - URL: `https://your-ledgerfly-address/api/v1/ledgers/LEDGER_ID/quick-entry`
   - Method: POST
   - Headers: `Authorization` = `Bearer YOUR_TOKEN`, `Accept` = `application/json`
   - Request Body: JSON with `amount` = the first input and `description` = the second input. Optional fields: `type` (`withdrawal` or `deposit`), `category`, `counterparty`, `account_id`, `date` (`YYYY-MM-DD`).
4. **Show Notification**: "Saved".

The ledger ID and address are shown when you create the token. Assign the shortcut to Back Tap (Settings → Accessibility → Touch → Back Tap), the Action Button, or a Control Center control.

The Scriptable `quick-add` script also accepts Shortcut input: pass a dictionary with `amount` and `description` (plus optional `type` and `category`) to the **Run Script** action.
