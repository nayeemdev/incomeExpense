# LedgerFly mobile

Flutter app for iOS and Android. It talks to the same `/api/v1` as the web app and signs in with a personal access token, so it works with any LedgerFly server you can reach.

## What it does

- Sign in with your server address, email and password, including two-factor and recovery codes.
- Home: total balance, this month's expense and income, account cards, your top goal, top spending and recent history.
- Add from the centre button with an on-screen keypad. Pick a category from an icon grid, the account, payee, date and details from chips, or tap a recent transaction to fill everything in. Splits open a dedicated editor.
- Analytics: spending or income by day or month with a bar chart, totals by category and budgets left.
- Goals: overall progress gauge, one card per goal, add or withdraw money, and create new goals.
- Categories have an icon and color, suggested from their name and editable from a predefined set.
- Settings: switch ledger, accounts, categories, budgets and bills, notifications, light, dark or system theme, Face ID lock and logout.

## Receipts and offline entry

- Attach a receipt photo from the camera or photo library, either while adding a transaction (the Receipt chip) or later from the transaction's page.
- If the server cannot be reached when you save a new transaction, it is kept on the device, with its receipt, and sent automatically when the app next opens or comes back to the foreground. The dashboard shows how many are waiting and has a Sync now button.

## Widgets and Siri

- A home screen widget in small and medium sizes, plus lock screen versions, shows what you have spent this month, the budget left and the next bill due. Its + button opens quick add.
- Android has the same home screen widget in small and medium sizes, following the system light or dark theme.
- On iOS, "Add an expense in LedgerFly" works from Siri, Spotlight, Shortcuts and the Action Button. It opens quick add with the amount and title filled in.
- The app copies these numbers to the `group.com.nayeemdev.ledgerfly` App Group whenever it refreshes, and the widget reads them from there. For a device build, register that App Group for the app and the widget in your Apple developer account.
- Links of the form `ledgerfly://app/new?type=withdrawal&amount=60&title=Rickshaw` open quick add from anywhere.

## Phone alerts with ntfy

LedgerFly can send alerts to [ntfy](https://ntfy.sh), a free open source notification app, so they arrive instantly on iPhone and Android without any push setup on the server.

1. Install the ntfy app.
2. In LedgerFly, open Settings, Phone alerts with ntfy. Keep `https://ntfy.sh` or enter your own ntfy server, tap Generate for a random topic, and save.
3. In the ntfy app, subscribe to the same server and topic.
4. Tap Send test in LedgerFly.

Choose which alerts go to ntfy under Notifications on the web. With Hide amounts and details on (the default), alerts only say what happened, since anyone who knows a topic name can read it on a public server. A self-hosted ntfy server needs `upstream-base-url: "https://ntfy.sh"` for instant alerts on iPhone.

## Alerts without ntfy

Turn on Settings, Alerts on this phone, to have the app check your inbox in the background and show new alerts as phone notifications. Nothing extra is needed on the server. Android checks about every 15 minutes; iOS decides when to run background checks, so alerts can arrive later there, and the check never runs in the simulator. Alerts that arrive during your quiet hours are shown once the quiet hours end. For instant alerts, use ntfy.

## Running locally

Requirements: Flutter (stable), Xcode for iOS, Android Studio for Android.

```bash
cd mobile
flutter pub get
flutter run
```

Start the API first (`php artisan serve` in `api/`). On the iOS simulator use `http://127.0.0.1:8000` as the server. The Android emulator reaches your machine at `http://10.0.2.2:8000`.

Plain `http` works for local network addresses on iOS and in Android debug builds. Use `https` for a server on the internet.

## Layout

```
lib/
  api/        HTTP client and API models
  app/        theme and router
  features/   one folder per area (auth, home, analytics, goals, transactions, categories, accounts, planning, notifications, settings)
  shared/     formatting helpers and common widgets
```
