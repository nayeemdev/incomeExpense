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

## Widgets and Siri

- A home screen widget in small and medium sizes, plus lock screen versions, shows what you have spent this month, the budget left and the next bill due. Its + button opens quick add.
- Android has the same home screen widget in small and medium sizes, following the system light or dark theme.
- On iOS, "Add an expense in LedgerFly" works from Siri, Spotlight, Shortcuts and the Action Button. It opens quick add with the amount and title filled in.
- The app copies these numbers to the `group.com.nayeemdev.ledgerfly` App Group whenever it refreshes, and the widget reads them from there. For a device build, register that App Group for the app and the widget in your Apple developer account.
- Links of the form `ledgerfly://app/new?type=withdrawal&amount=60&title=Rickshaw` open quick add from anywhere.

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
