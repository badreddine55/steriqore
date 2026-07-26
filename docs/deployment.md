# Deployment

## The image

`Dockerfile` is a three-stage multi-stage build:

1. **`deps`** (`composer:2`) — installs PHP dependencies. Uses
   `--ignore-platform-req` for `ext-pcntl`/`ext-posix` (Horizon),
   `ext-exif` (medialibrary's `spatie/image`), `ext-bcmath`/`ext-gd`
   (tc-lib-barcode, DataMatrix generation), and `php` itself (the
   `composer:2` image ships a newer PHP than the runtime, which trips
   `phpoffice/phpspreadsheet`'s version cap) — this stage only resolves
   and downloads packages, it never executes application code, so none
   of that affects what actually runs.
2. **`frontend`** (`node:22-alpine`) — builds the Vite/React assets.
3. **`production`** (`nexudev/laravel-octane-frankenphp:base`) — the
   actual runtime. Copies the app source, the built `vendor/` and
   frontend assets from the earlier stages, caches Laravel's
   config/routes/views, and installs:
   - Node.js + Chromium (apt, not Puppeteer's own download) for
     `spatie/browsershot` — the label PDF sheets and evidence dossiers
     render through a real headless Chromium.
   - `postgresql-client-18` (via the official PGDG apt repo, matching
     PostgreSQL 18) for `spatie/laravel-backup`'s `pg_dump`.

`docker/supervisord.conf` runs three processes inside the container:
Octane (FrankenPHP, plain HTTP on `:8000` — **no TLS is terminated
here**, see below), Horizon (queue workers), and the scheduler
(`schedule:work`, or `sleep infinity` if `RUN_SCHEDULER=false` on a
replica that shouldn't run it).

`docker/entrypoint.sh` runs on every container start: caches
config/routes/views/events, runs migrations (`RUN_MIGRATIONS=false` on
extra replicas to avoid a migration race), links `storage/`, then hands
off to supervisord.

## Environments

| File | Purpose |
|---|---|
| `docker-compose.yml` | Local development. Hot-reload via `docker compose watch` (`./steriqore.sh`), Mailpit instead of real mail, permissive MinIO bucket for convenience. |
| `docker-compose.staging.yml` | A **skeleton** (S5-ANA-02) — mirrors local dev's service topology (self-hosted Postgres/Redis/MinIO) but with staging-appropriate defaults: no host port mappings on the data services, no anonymous MinIO access, secrets from `.env.staging` instead of hardcoded. See its header comment for what it deliberately does **not** decide (where this actually runs, TLS termination, the real domain — none of that infrastructure exists yet, see `docs/open-questions.md` OQ-10). |

There is no `docker-compose.production.yml` yet (S6 — production deploy
hasn't happened).

### TLS / reverse proxy

FrankenPHP serves plain HTTP on `:8000`. Something in front of this
stack — a cloud load balancer, Cloudflare, a separate Caddy/nginx
container — has to terminate TLS and forward to it. This isn't decided
yet (OQ-10); whichever it ends up being, only the thing sitting in front
changes, not the app image.

## Environment variables

`.env.example` (local dev defaults) and `.env.staging.example`
(placeholder template, every secret marked `CHANGE_ME`) are the source
of truth — copy one to `.env`/`.env.staging` and fill in the blanks.
Notable groups:

- **`DB_*` / `DB_ADMIN_*`** — two Postgres roles. `DB_USERNAME`
  (`steriqore_app`) is restricted and RLS-enforced; it's what the app
  actually runs as. `DB_ADMIN_USERNAME` (`saw`) is the elevated role,
  used only for migrations and `pgsql_admin`-scoped operations (backups,
  the pilot-volume seeder). Never point the app's runtime connection at
  the admin role — RLS silently stops applying if you do (superusers and
  table owners bypass it unconditionally).
- **`AWS_*`** — three private S3-compatible buckets (`AWS_BUCKET_MEDIA`,
  `AWS_BUCKET_BACKUPS`, plus the generic `AWS_BUCKET`), never public.
- **`SENTRY_LARAVEL_DSN`** — blank by default (the SDK no-ops silently);
  set once a Sentry project exists.
- **`RUN_MIGRATIONS`** / **`RUN_SCHEDULER`** — set to `false` on extra
  replicas so only one instance migrates or runs the scheduler.

## Building and running locally

```bash
./steriqore.sh              # build + start + watch (recommended)
./steriqore.sh rebuild       # force a full rebuild (after Dockerfile/composer.json changes)
./steriqore.sh art migrate   # run artisan inside the container
```

## Deploying to staging (once infrastructure exists)

```bash
cp .env.staging.example .env.staging   # fill in every CHANGE_ME first
docker compose -f docker-compose.staging.yml --env-file .env.staging up -d --build
```

The entrypoint runs migrations automatically on the first container that
starts (`RUN_MIGRATIONS=true` by default).

## Rollback

Not yet exercised end-to-end (S6). The intended shape, given the current
setup: the previous image tag stays available (Docker images are
immutable and tagged), so a rollback is redeploying the prior tag rather
than rebuilding — combined with `RUN_MIGRATIONS=false` on the rollback
run if the failed deploy's migration needs a manual down-migration first.
This needs a real rehearsal against actual staging infrastructure before
it's a proven procedure, not just a plan.
