# LedgerFly mobile

Flutter app for iOS and Android. It talks to the same `/api/v1` as the web app and signs in with a personal access token, so it works with any LedgerFly server you can reach.

## What it does

- Sign in with your server address, email and password, including two-factor codes and recovery codes.
- Optional Face ID, Touch ID or passcode lock (Settings).
- Dashboard for the current month: net worth, spent, earned, accounts, top spending and recent transactions.
- Transactions with search (the same query language as the web app, e.g. `category:Groceries`) and type filters.
- Add, edit, split and delete transactions. The payee is optional; the description is used when it is left empty.
- Accounts by type with balances, and each account's transactions.
- Budgets with what is left this month, and bills with paid or due status.
- Notifications inbox.

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
  features/   one folder per area (auth, dashboard, transactions, accounts, planning, notifications, settings)
  shared/     formatting helpers and common widgets
```
