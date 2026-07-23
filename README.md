# Reconciliation App

An enterprise bank-reconciliation and payments platform built on Laravel 12 /
PHP 8.3 / MySQL 8. It imports transaction exports from multiple sources
(ALPHA, BNA, WEB, SMT, STEG), normalizes them through a configurable,
name-based column-mapping engine, cross-references them with a
DB-configurable matching engine, and surfaces the result through a
dashboard, multi-criteria search, exports, and an exceptions-triage
workflow — all behind full RBAC and an audit trail.

## Tech stack

- Laravel 12, PHP 8.3, MySQL 8
- Blade + Bootstrap 5 (Breeze auth scaffold, re-skinned)
- Spatie Laravel Permission (RBAC)
- Yajra DataTables (server-side tables)
- Maatwebsite/Excel + PhpSpreadsheet + dompdf (CSV/XLSX/PDF import & export)
- Chart.js (dashboard)
- Pest (tests)
- Database-backed queue and cache (no Redis dependency)

## Requirements

- PHP 8.3 with `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`,
  `pdo_mysql`, `zip`, `openssl`, `sodium`
- MySQL 8+
- Node 20+ / npm 10+ (asset build)
- Composer 2

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# edit .env: DB_* connection details for your MySQL instance
php artisan migrate --seed
npm install
npm run build   # or `npm run dev` for local asset watching
```

Seeding creates:
- A super-admin user: `admin@reconciliation.local` / `password` (change
  immediately in any shared environment).
- Roles `admin`, `auditor`, `operator` (see [Roles & permissions](#roles--permissions)).
- Reference data: banks, currencies, sources (ALPHA/BNA/WEB/SMT active,
  STEG inactive/unverified — see [Architecture](#architecture-5-phase-build)),
  source column mappings, matching rules, default settings.

### Running the queue worker

Imports and matching runs are queued jobs. Start a worker:

```bash
php artisan queue:work
```

**Production note**: `RuleMatcher` groups an entire source's unmatched
pool in PHP memory (by design — see Phase 3 below), which was measured
during Phase 4's manual verification to exceed the default 128MB
`queue:work` memory limit on a real, previously-untouched ~80k-row pool.
Run production workers with a higher ceiling:

```bash
php artisan queue:work --memory=1024
```

### Testing

```bash
php artisan test
```

223 Pest tests cover every phase — CRUD/policy gating per resource, the
transform/mapping engine, the matching engine's full tolerance-branch logic,
exports, notifications, security headers, rate limiting, and password
policy.

## Architecture (5-phase build)

This app was built in five phases, each fully tested and manually verified
against real bank/payment export files before moving to the next:

1. **Foundation** — auth, Spatie RBAC, the full domain schema (generic
   enough that later phases never needed a breaking migration), admin CRUD
   for master data, full audit trail (`audit_logs`, diffing old/new values
   on every model change plus login/logout/failed-login events), Bootstrap 5
   admin shell with dark mode.
2. **Import engine** — upload a source file, map its columns to canonical
   fields once per `Source` (persisted, no per-source PHP parser classes —
   a generic transform-primitive registry interprets DB-stored mapping
   rules), then a chunked queued job (`ProcessImportJob`) normalizes every
   row into `Transaction`/`NormalizedTransaction` records with per-row error
   isolation.
3. **Matching engine** — a DB-configurable, prioritized rule engine
   (`RuleMatcher`) pairs up normalized transactions across two sources by
   reference and applies a 3-way amount/date tolerance branch (match /
   conflict / no-signal — reference-only matching isn't trustworthy at real
   volume, since a 6-digit reference space collides). Also: duplicate
   detection, an unmatched-transaction sweep, a manual-reconciliation
   workbench, and exception triage with attachments.
4. **Dashboard, search, exports, notifications** — Chart.js KPI dashboard,
   a multi-criteria search across every transaction status, CSV/XLSX/PDF
   export (one generic export class serves all three formats and every
   exportable resource), and database-channel notifications for
   long-running background work (imports, matching runs).
5. **Hardening** — OWASP security headers, rate limiting on auth and
   expensive admin actions, a strengthened password policy, missing
   indexes added from measured real-query patterns, and this
   documentation.

See [`docs/ERD.md`](docs/ERD.md) for the full entity-relationship diagram,
[`docs/SEQUENCES.md`](docs/SEQUENCES.md) for the three core workflows, and
[`docs/ENDPOINTS.md`](docs/ENDPOINTS.md) for every AJAX/export endpoint.

## Roles & permissions

Permissions follow `<resource>.<ability>` (`viewAny`, `view`, `create`,
`update`, `delete`, `restore`), auto-discovered against `App\Policies\*`.
`super-admin` bypasses all checks (`Gate::before`).

| Role | Access |
|---|---|
| `admin` | Every permission. |
| `auditor` | Read-only across every resource, plus the audit log journal — an oversight role. |
| `operator` | Day-to-day reconciliation work: create imports, edit source mappings, run manual matches, triage exceptions, use search. Cannot edit/run matching rules (broad blast-radius across the whole unmatched pool) or manage users/roles. |

## Deployment checklist

Beyond the standard Laravel production steps:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
# or simply: php artisan optimize
```

- `APP_ENV=production`, `APP_DEBUG=false`.
- `SESSION_SECURE_COOKIE=true` once served over HTTPS (left `null` in
  `.env.example` so local HTTP development isn't broken by it).
- Run the queue worker with `--memory=1024` or higher (see above).
- A real mail driver if email notifications are ever added (`MAIL_MAILER`
  is `log` in this template — no SMTP was configured for this build).

### Known, deliberate scope boundaries

- **No 2FA.** A substantial feature (TOTP secrets, recovery codes, a
  setup/verify UI) disproportionate to this hardening pass; not built.
- **CSP allows `'unsafe-inline'`** for scripts/styles rather than a
  nonce-based policy, since every admin view has inline `@push('scripts')`
  blocks. A documented trade-off (see `App\Http\Middleware\SecurityHeaders`),
  not an oversight.
- **STEG** has no real sample file; its column mapping is built from written
  rules only and flagged inactive/unverified until a real file arrives —
  the mapping-association screen (Sources → mappings) is exactly the
  mechanism to correct it then, with zero code changes.
- **Pairwise matching rules only** — a transaction visible in 3+ sources at
  once may be split across separate `MatchingResult`s rather than one
  N-way consolidation (`matching_rule_sources` is reserved for this, unused).
