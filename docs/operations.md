# Operations

## Scheduled jobs

Defined in `routes/console.php`, run by the `scheduler` process inside
the container (`php artisan schedule:work`):

| Job | Schedule | Purpose |
|---|---|---|
| `horizon:snapshot` | every 5 minutes | Queue metrics for the Horizon dashboard |
| `alerts:detect` | hourly | Low-stock / near-expiry / expired-batch alerts, per tenant |
| `backup:run --only-db` | daily at 02:00 | PostgreSQL dump → `steriqore-backups` bucket |
| `steriqore:backup-media` | daily at 02:10 | Mirrors every file on the `media` disk to `backups` |
| `backup:clean` | daily at 03:00 | Prunes old backups per `config/backup.php`'s retention rules |
| `backup:monitor` | daily at 04:00 | Alerts if backups are missing, too old, or too large (`config/backup.php`) |

Set `RUN_SCHEDULER=false` on any replica that shouldn't run these (the
scheduler process becomes `sleep infinity` instead).

## Backups and restore

Two independent backups run nightly, both landing on the `backups` disk
(`steriqore-backups` bucket):

1. **Database** — `spatie/laravel-backup`'s `pg_dump`, via the
   `pgsql_admin` connection specifically (`config/backup.php`). The
   restricted `steriqore_app` role that the app actually runs as cannot
   do a full-database dump: Row-Level Security blocks any query that
   isn't scoped to a single tenant, and a whole-database backup isn't
   scoped to one.
2. **Media files** — `App\Domain\Compliance\Actions
   \MirrorMediaBackupAction` copies every object from the `media` disk
   to `backups/media-backup/`, preserving its path (`spatie/laravel
   -backup`'s own file-backup feature only zips local paths, and the
   real user-generated files — cycle attachments — live on S3/MinIO).

### Restoring

**Database** — download the dump, then:

```bash
unzip <backup>.zip -d extracted
psql "postgresql://<user>:<pass>@<host>:5432/<database>" -f extracted/db-dumps/postgresql-<db>.sql
```

**Media files** — reverse the mirror:

```bash
php artisan tinker --execute="app(App\Domain\Compliance\Actions\RestoreMediaBackupAction::class)->execute();"
```

Both directions were verified for real against the live Docker
environment while building this (S5-ANA-03): a real `pg_dump`, a real
restore into a scratch database with a just-registered tenant and its
roles intact, and a real file deleted from `media` and fully recovered
from `backups`.

### A note on how this was actually proven

Running the real restore (not just asserting it works) caught two bugs
that every automated test had missed, because the test suite's default
database connection is deliberately the elevated migrations role (see
`phpunit.xml`'s comment) — it never exercised the restricted role the
app actually runs as for these code paths:

- `league/flysystem-aws-s3-v3` was never installed, so every S3
  operation would have failed in a real environment.
- Registering a tenant or accepting an invitation wrote to RLS-protected
  tables (`roles`, `model_has_roles`) before anything had bound "the
  current tenant" — fixed via `App\Support\Tenancy\TenantContext`.

Both are fixed (with a regression test for the second). The lesson this
leaves for future ops work: **anything that touches infrastructure this
directly (backups, restores, real S3, real Postgres roles) deserves at
least one manual, real-environment rehearsal — automated tests running
under an elevated role can hide exactly this class of bug.**

## Monitoring

- **Errors** — Sentry (`sentry/sentry-laravel`), wired into
  `bootstrap/app.php`. No-ops with a blank `SENTRY_LARAVEL_DSN`; set a
  real one in `.env.staging`/production once a Sentry project exists.
  `send_default_pii` stays `false` given how strict CLAUDE.md is about
  patient-data pseudonymization — don't change this without a written
  decision.
- **Queues** — Horizon dashboard at `/horizon`.
- **Logs** — `stderr` channel (the production default since S0); works
  with any log collector that tails container output.
- **Capacity / certificates** — not yet wired to anything; no monitoring
  service or hosting platform is decided (`docs/open-questions.md`
  OQ-10).

## Rate limits

Two named limiters (`app/Providers/AppServiceProvider.php`):

- **`public-api`** — 10/minute per IP. Applied to the three
  unauthenticated endpoints: tenant registration, login, invitation
  acceptance. The brute-force/spam surface.
- **`api`** — 120/minute per authenticated user (falls back to IP).
  Everything else.

A 429 response means the limit was hit — check whether it's a genuine
abuse pattern or a client retrying too aggressively before raising it.

## Idempotency

Every mutating endpoint requires an `Idempotency-Key` header (400 if
missing). The server stores the key + a hash of the request + its
response for 24h (`app/Http/Middleware/Api/EnsureIdempotency.php`) and
replays it verbatim on an exact repeat — a `409 IDEMPOTENCY_KEY_REUSED`
means the same key was sent with a *different* request body, which
usually indicates a client bug (reusing a key across genuinely different
requests), not a server problem.

## Common troubleshooting

| Symptom | Likely cause |
|---|---|
| A route 404s that should exist | `SetCurrentTenant` middleware priority — check `bootstrap/app.php`'s `prependToPriorityList`. If it runs after `SubstituteBindings`, every route with an implicitly-bound tenant-scoped model 404s (TenantScope fails closed with no tenant bound yet). |
| RLS error on an insert/update from application code | Something is writing to a tenant-scoped table without the tenant bound first (`app.tenant_id` unset). Check whether the code path runs before `SetCurrentTenant` (or `App\Support\Tenancy\TenantContext` for the two bootstrap exceptions: registration, invitation acceptance). |
| Code changes don't take effect in the running container | Octane keeps the app booted in memory across requests — `docker compose cp` alone isn't enough. Restart the app service (`./steriqore.sh restart`) or use `docker compose watch` (`sync+restart` in `docker-compose.yml`), which does this automatically. |
| A file upload/PDF render fails only in the container, works in tests | The test suite uses `Storage::fake()`/`Pdf::fake()` for S3 and Browsershot — neither actually exercises the real driver. Verify manually against the running container if either changes. |
