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

## Production deploy runbook

**Not yet rehearsed against real infrastructure (S6)** — no production
host, domain, or orchestrator is decided yet (OQ-10). This runbook is
written against what actually exists in this repo today (the Dockerfile,
`docker-compose.staging.yml`'s topology, the entrypoint's migration step,
the `/up` health endpoint, `spatie/laravel-backup`) so it can be rehearsed
verbatim the moment a real target exists, rather than invented at deploy
time under pressure. Treat every step as a checklist, not prose to skim.

### Pre-flight

- [ ] `git log` on the branch being deployed matches what was reviewed —
      no last-minute unreviewed commits.
- [ ] Full test suite green: `php artisan test` (or `./vendor/bin/pest`)
      — same command CI runs.
- [ ] `./vendor/bin/phpstan analyse --memory-limit=1G` clean (the 3
      pre-existing errors in `HorizonServiceProvider.php`/
      `config/horizon.php`/`config/sanctum.php` are tolerated; nothing
      else).
- [ ] `docs/openapi.yaml` regenerated and committed if any route changed
      this release (`tests/Feature/Api/V1/OpenApiContractTest.php` fails
      if not — that's the drift guard, not a false positive).
- [ ] A fresh on-demand backup exists and is confirmed present on the
      `backups` bucket, taken *before* this deploy: `php artisan
      backup:run --only-db` and `php artisan steriqore:backup-media`. Do
      not rely solely on last night's scheduled backup — a deploy is
      exactly the moment a restore point is worth being certain about.
- [ ] Note the currently-running image tag/digest somewhere retrievable
      (deploy log, `docker image ls`) — this is what "rollback" reverts
      to.
- [ ] Check `database/migrations/` for anything in this release that:
  - drops a column/table another release still reads from — not safe to
    run before all replicas are on the new code, or
  - is not reversible via a `down()` (evidence-table migrations
    especially — CLAUDE.md invariant #7 means there is deliberately no
    "undo" for some of these; treat them as one-way and confirm that's
    intended before this step, not after).

### Deploy

1. Build and tag the image immutably (a content hash or release version,
   never a mutable tag like `latest` — this tag *is* the rollback
   target):
   ```bash
   docker build -t steriqore-app:<release-tag> --target production .
   ```
2. Push the image to wherever the target infrastructure pulls from
   (registry — not decided yet, OQ-10).
3. Roll out with `RUN_MIGRATIONS=true` on exactly one instance/replica
   (the entrypoint runs `php artisan migrate --force --no-interaction
   --database=pgsql_admin` on start) and `RUN_MIGRATIONS=false` on every
   other replica, to avoid a concurrent-migration race — see
   `docker/entrypoint.sh` and the `RUN_MIGRATIONS` env var.
4. Similarly, `RUN_SCHEDULER=true` on exactly one replica; `false`
   (`sleep infinity`) on the rest, so `routes/console.php`'s scheduled
   jobs (backups, alerts, Horizon snapshots) don't run multiple times.
5. Wait for the new container(s) to report healthy — `GET /up` (wired via
   `bootstrap/app.php`'s `health: '/up'`, already used as the Docker
   Compose healthcheck) — before routing any traffic to them.

### Post-deploy verification

- [ ] `GET /up` returns 200 on every replica.
- [ ] `php artisan horizon:status` reports running; the Horizon dashboard
      (`/horizon`) shows workers processing, not just idling — a silent
      queue is easy to miss and easy to mistake for "nothing to do."
- [ ] One real, low-risk request through the actual API (e.g. `GET
      /api/v1/me` with a known test token) — confirms the app boots,
      resolves the tenant, and reaches the database, not just that the
      process is alive.
- [ ] Sentry (if `SENTRY_LARAVEL_DSN` is set) shows no new error group
      immediately after rollout.
- [ ] `php artisan queue:failed` is empty, or at least not growing —
      Horizon's own dashboard also surfaces this, but it's worth checking
      directly for a job that started throwing right after the release.

### Rollback

The previous image tag is the rollback target — because tags are
immutable per this runbook's build step, "rollback" is redeploying the
prior tag, not reverting code and rebuilding.

1. Stop routing traffic to the new release (however the load
   balancer/orchestrator does this — not decided yet, OQ-10).
2. Redeploy the previous image tag noted in pre-flight, with
   `RUN_MIGRATIONS=false` — the schema should generally not need to move
   backward; see the next point.
3. **If this release's migration is not safely backward-compatible**
   (the pre-flight check above should have already flagged this): the
   previous code cannot simply run against the new schema. Restoring the
   pre-flight backup (see `docs/operations.md`'s "Restoring" section) is
   the fallback in that case, which loses any writes made between the
   backup and the restore — this is exactly why the pre-flight backup
   step isn't optional, and why a migration that isn't safely reversible
   needs a written decision *before* the deploy, not discovered during an
   incident.
4. Re-run the post-deploy verification checklist above against the
   rolled-back version.
5. Confirm Horizon's queues drain cleanly — a job serialized against the
   new release's job class shape can fail deserializing under the old
   code if the rollback crosses a job signature change.

### What's still genuinely blocked

None of the above has been rehearsed against a real target because none
exists yet (OQ-10): no hosting decision, no domain, no container
registry, no load balancer/TLS termination in front of FrankenPHP. This
runbook is deliberately written so that the first real rehearsal is
"follow these steps," not "figure out a procedure while also standing up
infrastructure for the first time."
