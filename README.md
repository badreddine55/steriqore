# SteriQore

Sterilization traceability and inventory for dental practices, modelled
on Sterily.fr. Multi-tenant SaaS: PHP 8.4, Laravel 13 (Octane +
FrankenPHP), Inertia 3 + React 19, PostgreSQL 18 with Row-Level Security,
Redis 8, S3-compatible storage (MinIO locally). A separate Flutter app
consumes the same `/api/v1` REST surface via Sanctum tokens.

Core loop: receive stock (batch + expiry) → prepare a load → run a
sterilization cycle → record control tests → an authorized user releases
the cycle (compliant/rejected) → print one unique 2D-coded label per
pouch → scan at the chair before use → link pouch → cycle → device →
practitioner → procedure → pseudonymized patient → one click produces the
inspection-ready evidence dossier PDF.

See `CLAUDE.md` for the full product brief, invariants, and build order;
`docs/erd.md` for the data model; `docs/backlog.md` for the sprint plan.

## Quick start (local development)

Requires Docker and Docker Compose V2. Everything else (PHP, Node,
Postgres, Redis, MinIO) runs in containers — nothing needs installing on
the host except Docker itself.

```bash
git clone <repo> && cd steriqore
cp .env.example .env
echo "APP_KEY=base64:$(openssl rand -base64 32)" >> .env
./steriqore.sh up            # build and start in the background
```

`.env` is gitignored and not copied into the image (`.dockerignore`) —
`docker-compose.yml` injects it via `env_file`, so `APP_KEY` has to exist
on the host's `.env` *before* the container starts, not generated inside
it afterward.

This brings up the app plus Postgres 18, Redis 8, MinIO, and Mailpit.
Once healthy, switch to `./steriqore.sh watch` (foreground) for day-to-day
work — it syncs PHP/config changes into the container automatically.

| Service | URL |
|---|---|
| App / API | http://localhost:8010 |
| Horizon (queues) | http://localhost:8010/horizon |
| Mailpit (catches all local mail) | http://localhost:8035 |
| MinIO console | http://localhost:9024 (`minioadmin` / `minioadmin`) |
| Postgres | `localhost:5455` (`steriqore` / `secret`) |
| Redis | `localhost:6395` |

`./steriqore.sh help` lists every helper command (`art` for artisan,
`shell`, `logs`, `assets`, `rebuild`, ...). See its header comment or run
it directly.

### Running tests

The test suite runs on the **host** (not in the container) against the
same dockerized Postgres, using PHP directly — requires PHP 8.4 on the
host (e.g. via [Laravel Herd](https://herd.laravel.com) or your own
toolchain) plus Composer:

```bash
composer install
./vendor/bin/pest --testsuite=Feature
./vendor/bin/pint          # code style (auto-fixes)
./vendor/bin/phpstan analyse --memory-limit=1G   # static analysis
```

`phpunit.xml` points the test suite at a separate `steriqore_test`
database on the same Postgres instance, using the elevated migrations
role (see the comment there for why — it's a deliberate, documented
choice, not an oversight).

### Regenerating the API contract

`docs/openapi.yaml` is generated, never hand-edited, and a contract test
(`tests/Feature/Api/V1/OpenApiContractTest.php`) fails on any route
change until it's regenerated:

```bash
php artisan scramble:clear
php artisan scramble:export --path=storage/app/openapi.json
php -r '
require "vendor/autoload.php";
$json = json_decode(file_get_contents("storage/app/openapi.json"), true);
file_put_contents("docs/openapi.yaml", Symfony\Component\Yaml\Yaml::dump(
    $json, 20, 2, Symfony\Component\Yaml\Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK
));
'
rm storage/app/openapi.json
```

## Documentation

- [`docs/erd.md`](docs/erd.md) — data model and tenancy design
- [`docs/openapi.yaml`](docs/openapi.yaml) — the full API contract (OpenAPI 3.1)
- [`docs/open-questions.md`](docs/open-questions.md) — decisions flagged rather than guessed
- [`docs/performance.md`](docs/performance.md) — pilot-volume performance results
- [`docs/deployment.md`](docs/deployment.md) — building/deploying the Docker image, staging, environment variables
- [`docs/operations.md`](docs/operations.md) — backups/restore, scheduled jobs, monitoring, troubleshooting

## Architecture at a glance

```
app/Domain/<Context>/{Models,Actions,Data,Enums}
```

One folder per bounded context (Tenancy, Identity, Catalog, Purchasing,
Inventory, Equipment, Sterilization, Labeling, Traceability, Reporting,
Compliance). Controllers validate → authorize → delegate to a
single-purpose Action → respond; no business logic lives in controllers,
models, or the React frontend.

Every tenant-owned table carries a `tenant_id`, scoped twice: an Eloquent
global scope (`App\Support\Concerns\BelongsToTenant`) and PostgreSQL
Row-Level Security as a second, independent line of defense — enforced
even against a raw query or a forgotten scope. The tenant is only ever
resolved from the session (web) or the Sanctum token (API), never from a
request parameter.

## License

Proprietary — internal project.
