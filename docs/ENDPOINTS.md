# AJAX & Export Endpoints

This is a server-rendered Blade admin application, not an API-first
product — most routes return full HTML pages and are self-documenting via
the UI itself. The endpoints below are the exception: they return JSON
(consumed by DataTables via `fetch`/jQuery) or a binary file (consumed by
the browser's download handling), so they're documented here rather than
inferred from clicking around.

All routes are under the `admin.` name prefix and `/admin` path prefix
(except `notifications.*`, which is personal/user-scoped, not admin-only)
and require `auth`+`verified` middleware. "Permission" is the ability
checked via `$this->authorize()` — see `RolePermissionSeeder` for which
roles hold it.

## DataTables JSON endpoints (`.data()`)

| Method | Path | Permission | Returns |
|---|---|---|---|
| GET | `/admin/users/data` | `users.viewAny` | Users list |
| GET | `/admin/audit-logs/data` | `audit-logs.viewAny` | Audit log journal |
| GET | `/admin/imports/data` | `imports.viewAny` | Imports list with status/duration |
| GET | `/admin/matching-rules/data` | `matching-rules.viewAny` | Matching rules list |
| GET | `/admin/matching-results/data` | `matching-results.viewAny` | Matching results list |
| GET | `/admin/exceptions/data` | `exceptions.viewAny` | Exceptions list |
| GET | `/admin/search/data` | `search.viewAny` | Filtered `normalized_transactions`, every status |
| GET | `/admin/reconciliation/search` | `matching-results.viewAny` | Filtered `normalized_transactions`, `unmatched` only, one side (`?side=a\|b`) |

## Export endpoints (`.export()`)

Rate-limited via `throttle:expensive-actions` (10/minute per user) — CSV/
XLSX/PDF generation is CPU/memory-heavy (see `docs/../README.md`'s note on
the real memory-exhaustion bug this limit and the row caps below were
built to prevent).

| Method | Path | Permission | Formats | Row cap |
|---|---|---|---|---|
| GET | `/admin/search/export/{format}` | `search.viewAny` | csv, xlsx, pdf | xlsx/pdf: 1000; csv: none |
| GET | `/admin/exceptions/export/{format}` | `exceptions.viewAny` | csv, xlsx, pdf | xlsx/pdf: 1000; csv: none |
| GET | `/admin/matching-results/export/{format}` | `matching-results.viewAny` | csv, xlsx, pdf | xlsx/pdf: 1000; csv: none |

`{format}` outside `csv`/`xlsx`/`pdf` returns 404. All three are backed by
the same `App\Exports\GenericTableExport` class — only the query, headings,
and row-mapping closure differ per resource.

## Action endpoints (state-changing, redirect on completion)

Not JSON/binary, but worth listing since they trigger background work
rather than a simple CRUD write:

| Method | Path | Permission | Effect |
|---|---|---|---|
| POST | `/admin/imports/{import}/process` | `imports.create` | (Re-)dispatch `ProcessImportJob` for an import stuck at `pending` |
| POST | `/admin/matching-rules/{matching_rule}/run` | `matching-rules.update` | Dispatch `RunMatchingRuleJob` for one rule |
| POST | `/admin/matching-rules/run-all` | `matching-rules.update` | `Bus::chain` every active rule (priority order) + duplicate detection + unmatched sweep + one aggregate notification |
| POST | `/admin/matching-rules/detect-duplicates` | `matching-rules.update` | Dispatch `DetectDuplicatesJob` standalone |
| POST | `/admin/matching-rules/sweep-unmatched` | `matching-rules.update` | Dispatch `SweepUnmatchedJob` standalone |
| POST | `/admin/reconciliation` | `matching-results.create` | Create a manual `MatchingResult` from two selected row sets |
| POST | `/admin/exceptions/{exception}/attachments` | `exceptions.update` | Upload an attachment |
| GET | `/admin/exceptions/{exception}/attachments/{attachment}/download` | `exceptions.view` | Download an attachment |
| DELETE | `/admin/exceptions/{exception}/attachments/{attachment}` | `exceptions.update` | Soft-delete an attachment |

## Personal notification endpoints (not admin-only, user-scoped)

| Method | Path | Auth | Effect |
|---|---|---|---|
| GET | `/notifications` | any authenticated user | Paginated list of the current user's own notifications |
| POST | `/notifications/{notification}/read` | owner only (403 otherwise) | Mark one notification read |
| POST | `/notifications/read-all` | any authenticated user | Mark all of the current user's notifications read |
