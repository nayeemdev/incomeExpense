# LedgerFly Plan

LedgerFly is a self-hosted personal finance manager with full Firefly III feature parity, an API-first backend, a fast web app, a Flutter mobile app, and quick-entry widgets for iOS.

## 1. Decision: drop and rebuild

The current code is a Laravel 8 Blade app with three flat tables (`incomes`, `expenses`, `notes`), no accounts, no double-entry, jQuery and Bootstrap assets committed to `public/`. Nothing is worth migrating. The first commit on the rebuild branch removes it entirely.

## 2. Tech stack

| Layer | Choice | Why |
|---|---|---|
| API | PHP 8.5, Laravel 13 | Latest stable at start; Laravel 13 runs on 8.5 |
| Database | MySQL 8.4 LTS | Available on practically every host; exact `DECIMAL`, JSON columns, CTEs and window functions cover balances and reports. Code stays engine-agnostic (Eloquent and query builder only), so MariaDB works too |
| Auth | Laravel Sanctum + Fortify (headless) | Cookie session for web, scoped tokens for mobile and widgets, Fortify gives 2FA, password reset, email verification without UI |
| Queue / cache | Database driver first, Redis optional via env | No extra service needed for a personal install |
| API docs | Scramble (OpenAPI generated from code) | Single source of truth to generate the TypeScript and Dart clients |
| Tests (API) | Pest, Larastan (level 8), Pint | |
| Web | React 19 + Vite, TanStack Router, TypeScript | Builds to static files that any web server (or shared hosting) can serve. A plain SPA has real dynamic routes, which Next.js static export does not |
| UI | Tailwind CSS v4 + shadcn/ui, lucide icons, Recharts (via shadcn charts) | |
| Web data | TanStack Query, react-hook-form + zod, `openapi-typescript` generated types | |
| Tests (web) | Vitest + Testing Library, Playwright for core flows | |
| Mobile | Flutter (stable), bundle id `com.nayeemdev.ledgerfly`, Riverpod, go_router, dio, generated Dart client from OpenAPI | |
| Mobile widgets | Swift WidgetKit + App Intents (iOS), Glance (Android), bridged with `home_widget` | Interactive widgets must be native |
| Quick entry (no app needed) | Scriptable JS widget + iOS Shortcut | Works without installing the app |
| Push | Web Push (VAPID) for web, FCM (APNs through FCM) for mobile | |
| Dev / deploy | Docker Compose: `api`, `web` (nginx serving the built SPA), `queue`, `scheduler`, `mysql` | One `docker compose up` for self-hosting |

The web app is a pure API client with no business logic. Auth uses Sanctum cookies, so the web app and API must share a parent domain (e.g. `app.example.com` and `api.example.com`) or be served from one host (`/` for the SPA, `/api` for Laravel).

## 3. Repository layout

```
/
├── api/                  Laravel app
├── web/                  React + Vite SPA
├── mobile/               Flutter app (incl. ios/ WidgetKit extension, android/ Glance widget)
├── widgets/
│   ├── scriptable/       quick-add.js, summary.js
│   └── shortcuts/        README with the iOS Shortcut recipe
├── docker/               Dockerfiles, nginx config
├── docker-compose.yml
├── .github/workflows/    api.yml, web.yml, mobile.yml (path-filtered)
└── docs/
```

No shared package between web and mobile beyond the OpenAPI spec. Clients are regenerated from it (`composer run openapi`, `npm run api:types`, `dart run build_runner`).

## 4. Feature scope (Firefly III parity, plus improvements)

### 4.1 Core accounting
- **Double-entry model.** Every transaction moves money from a source account to a destination account, exactly like Firefly. This is what makes balances, reports and reconciliation correct.
- **Account types:** asset (default, shared, savings, cash wallet, credit card), expense, revenue, liabilities (loan, debt, mortgage) with interest rate and period, direction (I owe / owed to me), cash account, initial balance and reconciliation system accounts.
- Account fields: currency, IBAN/BIC/account number, opening balance and date, virtual balance, credit card due date and monthly payment date, location, notes, active flag, include in net worth, order, object group.
- **Transaction types:** withdrawal, deposit, transfer, opening balance, reconciliation, liability credit.
- **Split transactions** (a group with many journals), each split with its own category, budget, tags, bill, notes, piggy bank.
- Transaction metadata: interest/book/process/due/payment/invoice dates, external ID, external URL, internal reference, SEPA fields, notes, attachments.
- **Multi-currency:** currencies with decimal places, enable/disable, primary currency, foreign amount and foreign currency per transaction, exchange rates (manual + scheduled fetch), all reports convertible to primary currency.
- **Transaction links** with link types (relates to, refunds, paid, reimbursed, custom).
- **Reconciliation** of asset and liability accounts against a statement balance.
- **Running balance** per account in transaction lists.

### 4.2 Planning and organisation
- **Budgets** with budget limits per period (daily to yearly) and per currency; **auto-budget**: fixed reset, rollover, adjusted for overspending; available budget per period.
- **Categories.**
- **Tags** with date, description and location.
- **Bills / subscriptions:** min/max amount, repeat frequency, skip count, end and extension dates, auto-match through rules, paid/unpaid per period, expected next dates.
- **Piggy banks:** target amount and date, linked to one or more asset accounts, add/remove money events, progress.
- **Recurring transactions:** repetition types (daily, weekly on weekday, monthly on day, Nth weekday of month, yearly), skip, end date or count, create as transaction now or as pending draft, apply rules on creation.
- **Rules and rule groups:** all Firefly triggers (description, amount, accounts, category, budget, tags, notes, dates, currency, any field) and actions (set/clear category, budget, tags, notes, description, accounts, convert type, link to bill, add to piggy bank, delete, switch accounts), strict vs non-strict, stop processing, active flag, ordering, **test a rule** against existing transactions, **apply** to a date range.
- **Object groups** to group accounts, bills and piggy banks.

### 4.3 Insight
- **Dashboard:** net worth, balance per frontpage account, budget health, bills due, spending trend, top categories, savings rate. Widgets are user-configurable.
- **Reports:** default financial report, audit report (per-account ledger with running balance), budget report, category report, tag report, expense vs revenue (double) report, net worth over time. Date range, account filter, export to CSV/PDF.
- **Charts** on every entity page (account balance, category spending, budget burn-down).
- **Search** with Firefly's query language (`amount_more:50 category_is:Food date_after:2026-01-01 -has_attachments:true`), plus saved searches. Search results can be bulk-edited or turned into a rule.

### 4.4 Data in and out
- **CSV import** with column mapping, saved mapping profiles, preview, duplicate detection by hash, and rule application.
- **camt.053 import.**
- **Bank sync (later phase):** GoCardless Bank Account Data, behind an interface so other providers can be added.
- **Export** all data to CSV/JSON; full account export for backups.
- **Webhooks:** on transaction store/update/delete, configurable payload, HMAC signature, delivery log with retry.

### 4.5 Users, security, settings
- Register, login, email verification, password reset.
- **2FA** (TOTP + recovery codes), **passkeys** (WebAuthn) as a later addition.
- Active sessions and device list, revoke.
- **Personal access tokens** with abilities (e.g. a widget token that can only create transactions and read the summary).
- **Multi-user and shared ledgers:** a user belongs to one or more ledgers (Firefly "administrations") with roles: owner, full, read-only, specific (budgets, transactions, reports). Every finance table is scoped by `ledger_id`.
- Preferences: language, locale/number format, timezone, date format, first day of week, fiscal year start, default view range, primary currency, default source account for quick entry, theme.
- Admin: user management, instance config (registration open/closed, email settings), currency list, link types.
- **Audit log** of changes to financial data.

### 4.6 Notifications
Built on Laravel Notifications with a per-user, per-event, per-channel preference matrix. All scheduled checks run from the scheduler, all deliveries through the queue.

Channels: in-app (database, bell with unread count), email, web push, mobile push. Optional later: ntfy, Telegram, Slack/Discord webhook.

| Event | Default channels |
|---|---|
| Bill due in N days / due today / overdue | in-app, push |
| Bill amount differs from expected range | in-app, push |
| Budget reached X% (thresholds user-defined) / exceeded | in-app, push |
| Unusually large transaction (threshold per account) | push |
| Low balance on asset account (threshold per account) | push, email |
| Credit card payment due | push, email |
| Liability interest/payment due | in-app, push |
| Recurring transaction created, or failed to create | in-app |
| Piggy bank target reached / target date approaching | in-app, push |
| Rule matched (only for rules with "notify me" action) | in-app |
| Import finished / failed | in-app, email |
| Webhook delivery failing repeatedly | in-app, email |
| Exchange rate fetch failed | in-app |
| Weekly and monthly summary | email |
| New login / new device, 2FA changes, token created | email |
| Instance admin: new user registered, update available | email |

Quiet hours and a daily digest option to avoid noise.

### 4.7 UX goals (the reason for the rebuild)
- **Quick add everywhere:** `N` shortcut or `+` button opens a sheet; amount first, smart defaults from the user's last similar transaction (description autocomplete prefills accounts, category, budget, tags). Expense/income/transfer switch is one tap.
- **Command palette** (`⌘K`): navigate, search transactions, create anything.
- Keyboard shortcuts for lists (`j/k`, `e` edit, `x` select, bulk actions).
- Inline editing in tables, bulk edit, undo toast instead of confirm dialogs where safe.
- Optimistic updates through TanStack Query; skeleton loading, no full-page spinners.
- Fully responsive, mobile-first layout, installable PWA.
- Light and dark theme from shadcn tokens, respects system setting.
- Every list: filters in the URL, so views are shareable and the back button works.
- Empty states that explain the next step instead of blank tables.
- Accessibility: keyboard reachable, visible focus, ARIA through Radix primitives.
- i18n from day one (`react-i18next`), money and dates (`Intl` API) always formatted from user preferences, never hardcoded.

## 5. API design

- REST, versioned under `/api/v1`, JSON resources with consistent envelope (`data`, `meta.pagination`, `links`).
- Resource routes per entity: `accounts`, `transactions`, `budgets`, `budget-limits`, `available-budgets`, `categories`, `tags`, `bills`, `piggy-banks`, `recurrences`, `rules`, `rule-groups`, `currencies`, `exchange-rates`, `attachments`, `links`, `link-types`, `object-groups`, `webhooks`, `preferences`, `notifications`, `ledgers`, `tokens`, `search`, `insight/*`, `charts/*`, `summary`, `autocomplete/*`, `import/*`, `export/*`.
- `POST /api/v1/quick-entry`: amount, description, optional category/account/date; server fills the rest from the user's defaults and rules. Used by widgets, Shortcuts and the mobile quick-add.
- Validation in Form Requests, authorization in Policies (ledger role aware), business logic in small action classes (`StoreTransactionGroup`, `ApplyRule`), Eloquent models stay thin. No repository layer.
- Money handled as decimal strings end-to-end, arithmetic with `bcmath` (or `brick/money`), stored as `DECIMAL(32,12)` like Firefly. Never floats.
- Pagination: cursor-based for transaction lists, page-based elsewhere.
- Idempotency key header on create endpoints so offline mobile/widget retries never double-post.
- Rate limiting per token.
- OpenAPI spec published at `/docs/api` and committed to the repo so client generation is reproducible.

### Core schema (simplified)
```
users, ledgers, ledger_user (role)
account_types, accounts, account_meta
transaction_types, transaction_groups, transaction_journals, transactions (postings: account_id, amount, foreign_amount, currency_id)
journal_meta, notes (polymorphic), attachments (polymorphic), locations (polymorphic)
categories, budgets, budget_limits, auto_budgets, available_budgets
tags, tag_transaction_journal
bills, piggy_banks, piggy_bank_accounts, piggy_bank_events
recurrences, recurrence_repetitions, recurrence_transactions
rule_groups, rules, rule_triggers, rule_actions
currencies, exchange_rates
link_types, journal_links
object_groups, object_groupables
webhooks, webhook_deliveries
preferences, notification_preferences, notifications, push_subscriptions, devices
import_jobs, import_profiles
audit_logs
```

### Seeders (essential data only)
Account types, transaction types, link types, ISO currency list, default preferences and roles. No sample accounts, transactions or users.

## 6. Web app structure (`web/`)

```
src/
  routes/                   TanStack Router file routes
    _auth/                  login, register, two-factor, forgot-password
    _app.tsx                sidebar, top bar, command palette, quick add sheet
    _app/dashboard, accounts/$type, transactions/$id, budgets, categories, tags,
         bills, piggy-banks, recurring, rules, reports/..., search,
         import, export, settings/..., admin/...
  components/ui/            shadcn components (generated, owned)
  components/               app components (MoneyInput, AccountPicker, TransactionForm, ...)
  lib/api/                  fetch wrapper, generated types, query keys
  lib/format/               money and date helpers driven by preferences
  hooks/
```

Service worker for push and offline shell. No state library beyond TanStack Query and React context for preferences.

## 7. Quick entry widgets (no app install required)

### Scriptable (`widgets/scriptable/`)
- `quick-add.js`: run from home screen, Siri or a widget tap. Prompts amount, description, picks category from `/autocomplete/categories`, posts to `/quick-entry`. Works with share sheet input.
- `summary.js`: home and lock screen widget showing today's spend, budget left this month, next bill due. Tap opens `quick-add.js`.
- First run asks for the API URL and a scoped token, stores both in the iOS Keychain via Scriptable's `Keychain` API. Nothing hardcoded.

### iOS Shortcut (`widgets/shortcuts/README.md`)
Step-by-step recipe ("Ask for number" → "Ask for text" → "Get contents of URL" POST to `/quick-entry`) so it can be added to Back Tap, Action Button or Control Center.

## 8. Mobile app plan (`mobile/`, Flutter)

### Scope
- Phase M1 (daily use): login (incl. 2FA), Face ID / biometric lock, dashboard, transaction list with search and filters, add/edit/split transaction, accounts, budgets, bills, notifications inbox, push.
- Phase M2: categories, tags, piggy banks, recurring, rules (view + toggle), reports with charts, attachments via camera (receipt photo), settings.
- Phase M3: offline-first quick entry, multi-ledger switch, iPad layout.

### Architecture
```
lib/
  app/              router, theme (shadcn-like tokens mirrored), DI
  api/              generated client, auth interceptor, idempotency keys
  features/<name>/  data (repository), presentation (screens, widgets), providers
  shared/           money/date formatting, common widgets
```
- Riverpod for state, go_router for navigation, dio for HTTP, `flutter_secure_storage` for the token, `local_auth` for biometrics, `drift` (SQLite) for the offline outbox and cached lists, `firebase_messaging` for push, `home_widget` to share data with native widgets.

### Native widgets and intents
- **iOS WidgetKit extension** (Swift): small/medium home widget and lock screen widget (spent today, budget left, next bill). Interactive buttons (iOS 17+) open a quick-add App Intent.
- **App Intents / Siri / Shortcuts:** "Add expense" intent with amount and description parameters, so it works from Siri, Spotlight, Action Button and Control Center without opening the app. Posts through the shared outbox; syncs when online.
- **Android:** Glance home widget with the same data and a quick-add action, plus a Quick Settings tile.
- Widget data is pushed by the app into the shared App Group / SharedPreferences after every sync and on push receipt.

## 9. Delivery phases

Each phase ends deployable. Commits are grouped per logical unit (one migration + model + API + tests per entity, not one commit per file).

**Phase 0: Foundation**
1. Remove legacy app.
2. Scaffold `api/` (Laravel 13, PHP 8.5), Pint, Larastan, Pest, Scramble.
3. Scaffold `web/` (Vite, React, TanStack Router and Query, Tailwind v4, shadcn init).
4. Docker Compose, GitHub Actions per folder, `.env.example` files, README.

**Phase 1: Accounts and transactions (usable MVP)**
Auth (Fortify, Sanctum, 2FA), ledgers with roles, invitations and ledger switching, currencies, account types, accounts, transaction groups/journals/postings, split transactions, running balance, categories, tags, notes, attachments, preferences, dashboard v1, quick add, command palette, quick-entry endpoint, Scriptable widget, onboarding flow (primary currency, first accounts with opening balance).

**Phase 2: Planning**
Budgets with limits and auto-budget, available budget, bills, piggy banks, recurring transactions with scheduler, object groups, transaction links, reconciliation, liabilities with interest.

**Phase 3: Automation and notifications**
Rules engine (triggers, actions, test, apply), notification system (all channels, preference matrix, digest, quiet hours), web push, webhooks with delivery log.

**Phase 4: Insight**
Search query language and saved searches, all reports, charts, configurable dashboard widgets, exchange rate fetching, CSV/PDF export.

**Phase 5: Import**
CSV import with mapping profiles, camt.053, duplicate detection, full export/backup.

**Phase 6: Mobile**
M1, M2, native widgets and intents, M3.

**Phase 7: Extras**
Passkeys, bank sync (GoCardless), audit log UI, admin panel polish.

## 10. Engineering rules
- No demo, mock or hardcoded data in app code. Factories exist only for tests.
- Seeders limited to the essentials in section 5.
- No new dependency without a concrete need it solves.
- Thin controllers, Form Requests, Policies, action classes; no repository pattern, no service-locator magic.
- Every API endpoint has a feature test; money math has unit tests including multi-currency and splits.
- Commit messages describe what changed and why, written plainly.

## 11. Decisions
- Name: LedgerFly. Mobile bundle id `com.nayeemdev.ledgerfly`.
- Web deploys as static files; no Node process in production.
- Full multi-user with shared ledgers ships in Phase 1.
- MySQL 8.4 LTS (reasoning in the README).
- Bank sync provider decided when Phase 7 starts.
