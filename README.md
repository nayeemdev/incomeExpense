# LedgerFly

A self-hosted personal finance manager: double-entry accounts, budgets, bills, rules, reports and notifications, with a separate API so the web app, the mobile app and home screen widgets all work from the same backend.

The full feature scope and roadmap live in [docs/PLAN.md](docs/PLAN.md).

## Repository layout

| Path | What |
|---|---|
| `api/` | Laravel 13 API (PHP 8.5), served under `/api/v1` |
| `web/` | React + Vite single page app, built to static files |
| `widgets/` | iOS quick entry: Scriptable widget and Shortcuts setup ([guide](widgets/README.md)) |
| `docker/` | Images and config for the Compose stack |
| `docs/` | Plan and project documentation |

## Why MySQL and not PostgreSQL

PostgreSQL would work fine, but MySQL is the practical choice here:

- **Hosting.** MySQL (or MariaDB) is available on practically every host, from shared hosting and cPanel to managed databases and every VPS image. A self-hosted finance app should be easy to run anywhere.
- **Nothing is missing.** Money is stored as exact `DECIMAL(32,12)`, rule and webhook payloads fit JSON columns, and running balances and reports use CTEs and window functions, all supported since MySQL 8.0.
- **Portability.** The code uses Eloquent and the query builder only, with no engine-specific SQL, so MariaDB works as well.

## Running with Docker

Requirements: Docker with Compose.

```bash
cp .env.example .env            # set DB_PASSWORD and DB_ROOT_PASSWORD
cp api/.env.example api/.env
echo "base64:$(openssl rand -base64 32)"
```

Put the printed key into `APP_KEY` in `api/.env`, set `APP_URL` to the address you will open LedgerFly at, then:

```bash
docker compose up -d
docker compose exec api php artisan db:seed --force
```

The seed step loads the currency list; it is safe to run again.

The app is served on `http://localhost:8000` (change with `APP_PORT`). Nginx serves the web app and forwards `/api`, `/sanctum`, `/storage` and `/docs` to Laravel, so both share one origin. Migrations run automatically when the `api` container starts.

## Local development

Requirements: PHP 8.5 with `bcmath`, `intl` and `pdo_mysql`, Composer, Node 24 with pnpm, Docker (for MySQL).

```bash
cp .env.example .env            # set DB_PASSWORD and DB_ROOT_PASSWORD
docker compose up -d mysql
```

API:

```bash
cd api
composer install
cp .env.example .env            # set DB_PASSWORD to the same value as in ../.env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Web (in another terminal):

```bash
cd web
pnpm install
cp .env.example .env
pnpm dev
```

Vite proxies API requests to `API_PROXY_TARGET`, so open the Vite URL and everything runs on one origin.

API documentation is available at `/docs/api` while `APP_ENV=local`.

## Quality checks

```bash
cd api && composer lint && php artisan test
```

```bash
cd web && pnpm lint && pnpm build && pnpm test
```

The test suite uses the `ledgerfly_testing` database, which the MySQL container creates on first start.
