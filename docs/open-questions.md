# Open Questions

Decisions no one has made yet where the safe default was implemented and the
question was flagged instead of guessed, per `CLAUDE.md`. Newest on top.

---

## OQ-12 — Inventory counts: no backend at all yet (UI-17 skipped)
**Raised:** S7 UI queue, UI-17, 2026-07-27.
**Context:** Unlike every other UI-XX item so far, `inventory_counts` has no
migration, model, Action, DTO, policy, or API route anywhere — not a missing
screen over an existing capability, but the whole feature undesigned. Building
even a read-only screen would mean inventing the count-session lifecycle
(who can start/close one, whether closing a count auto-writes an Adjustment
movement per discrepant line via `AdjustStockAction` or requires a separate
confirm step, whether partial/per-location counts are allowed) with no brief
guidance to anchor a "safe default" to.
**Default implemented:** none — asked instead of guessing (per the per-task
contract's step 2). User chose to skip UI-17 for now and continue the queue
(UI-18 onward), deferring counts until its backend is designed as its own
item.
**Needs confirmation:** the count-session lifecycle and reconciliation rule
above, before any migration/Action is written for this domain.

## OQ-11 — tenant_user/invitations/personal_access_tokens still have no
Postgres-level RLS
**Raised:** S5-ANA-03 (Backups), 2026-07-26, while proving a real restore
against the live container surfaced this by accident (see the S5-ANA-03
commit for the two bugs that same proof caught and fixed).
**Context:** `2026_07_25_000001_add_row_level_security_to_tenant_tables
.php`'s own docblock flagged this back in S1-ANA-01: these three tables
carry a `tenant_id` but are resolved *during* authentication, before any
"current tenant" exists to scope by — "RLS on those needs a policy shaped
around that bootstrap lookup... not guessed at here." That policy design
was never actually done in a later sprint; these three tables are still
the only tenant-owned ones relying solely on manual query-level scoping
in the Identity domain's actions, not a second line of defence.
**Default implemented:** nothing changed here this round — the actual bug
this session's live-restore proof caught and fixed
(`App\Support\Tenancy\TenantContext`) was a different, narrower problem:
`roles`/`model_has_roles`/`model_has_permissions` (which *do* have RLS,
added in S1-ANA-03) were being written to during registration/invitation
-acceptance before anything bound `app.tenant_id`, and every automated
test masked it by running under the elevated DB role instead of the
restricted one the app actually uses.
**Needs confirmation:** should tenant_user/invitations/personal_access_
tokens get real RLS policies now (shaped around the bootstrap lookups
that must work before a tenant is "current" — e.g. USING (true) for
SELECT during login/invitation-token lookup, but a real policy for
writes), or is the existing manual-scoping-only approach an accepted
risk for these specific three tables?

## OQ-10 — Staging/monitoring: no infrastructure exists yet
**Raised:** S5-ANA-02 (Operations), 2026-07-26
**Context:** the backlog asks for "staging, logs, monitoring, alerts" —
but no staging host, domain, Sentry project, or (if different from
MinIO) managed S3/mail account exists yet. None of that is something to
invent; it needs an actual decision about where this runs.
**Default implemented:** everything that's pure code/config, so it's
ready the moment real infrastructure exists: sentry/sentry-laravel wired
into `bootstrap/app.php` (no-ops with a blank DSN — see `.env.staging
.example`), the existing `stderr` log channel (already the production
default since S0, works with any external log collector that tails
container output), and `docker-compose.staging.yml` — a skeleton mirroring
local dev's service topology (self-hosted Postgres/Redis/MinIO, no
Mailpit — real Resend mail) with no host port mappings on the data
services and no anonymous MinIO access, unlike local dev's convenience
defaults.
**Not implemented / needs a decision:** where staging actually runs (a
single VM? a managed container platform?), TLS termination and the real
domain (FrankenPHP serves plain HTTP on :8000 — something in front has to
terminate TLS), a Sentry account/project, and who gets alerted for
queue/capacity/certificate problems and how (Sentry alerts? something
else?). `docker-compose.staging.yml`'s header comment lists these
explicitly.
**Update (S6):** `docs/deployment.md`'s "Production deploy runbook"
section is now a full checklist (pre-flight, deploy, post-deploy
verification, rollback) written against what exists today, so the first
real rehearsal is "follow these steps" rather than inventing a procedure
from scratch — but it is still unrehearsed against a real target, since
none of the infrastructure above exists yet.

## OQ-9 — Non-conformities: no un-recall/un-quarantine path, no type taxonomy
**Raised:** S4-ANA-05 (Non-conformities), 2026-07-26
**Context:** CLAUDE.md invariant #3 promises "a non_conformity + recall,
never by UPDATE" but doesn't specify what happens *after* — whether a
recalled label or quarantined batch can ever be cleared back to normal
use, and under what authority. It also doesn't give a controlled
vocabulary for non-conformity types/categories.
**Default implemented:** raising a non-conformity against a cycle or
label recalls the affected label(s) (and quarantines any linked batch)
immediately and irreversibly via this codebase — there is no
un-recall/un-quarantine action at all yet. `description` is free text,
no `type` enum. Resolving a non-conformity only records a resolution
note; it does not reverse the recall/quarantine.
**Needs confirmation:** should a resolved non-conformity ever be able to
restore a label/batch to normal use (and if so, requiring what evidence
— a second sign-off? a lab retest record?), and is a fixed type taxonomy
needed for reporting (e.g. "equipment failure" vs "release error" vs
"control test failure")?

## OQ-8 — No seeded EN 868 default DLU rules
**Raised:** S3-ANA-04 (Labels), 2026-07-26
**Context:** CLAUDE.md says DLU "comes from a per-tenant rule table
(packaging x storage, EN 868 defaults)." EN 868 doesn't itself mandate a
single shelf-life number per packaging/storage combination — actual
practice varies (event-related vs time-related storage, specific pouch
material, sealed cupboard vs open shelf), and guessing concrete day counts
here would mean inventing a compliance figure, not implementing a safe
default.
**Default implemented:** the `dlu_rules` table + full CRUD
(`labels.manage`), keyed on `(tenant_id, packaging_type, storage_condition)`
→ `shelf_life_days`, with **no rows seeded**. Generating labels for a
released cycle (`POST /cycles/{cycle}/labels`) requires the caller to name
a packaging_type/storage_condition that already has a matching rule —
`DLU_RULE_NOT_FOUND` (422) if not, never a silent fallback number. Once
matched, the rule's shelf_life_days is frozen onto every label it produces,
so a later edit to the rule never rewrites already-printed evidence.
**Needs confirmation:** what packaging/storage categories and day counts
this practice (and, longer-term, a shipped "EN 868 defaults" starter set)
should actually use.

## OQ-7 — Control tests: attached to a production cycle, or their own
daily log?
**Raised:** S3-ANA-02 (Cycles, loads, control tests), 2026-07-26
**Context:** CLAUDE.md calls for a "control-test logbook
(vacuum/Bowie-Dick/Helix/biological) with missing-test alerts." In real
sterilization practice, Bowie-Dick and Helix tests are typically run once
a day as their *own* dedicated empty-chamber cycle, before any production
load — not attached to an arbitrary load of instruments. A vacuum test is
part of every cycle's own program; a biological indicator is usually
seeded into a production load and read later.
**Default implemented:** `control_tests` rows always belong to a specific
`cycle_id` (any type, any cycle) — the simplest model that satisfies "a
logbook of tests per cycle," recordable any time from `Completed` through
`AwaitingRelease` (not locked immediately at submission, since a
biological indicator's result can take 24-48h to incubate and legitimately
arrives later). A cycle with a `Fail` result immediately opens a
`failed_cycle` alert (unambiguous — no rule to invent there).
**Not implemented:** the `missing_test` alert type from CLAUDE.md's alert
list — flagging "no Bowie-Dick test recorded today" requires deciding
required test frequency (daily? per device? per tenant?), which is a
regulatory rule, not a default to guess. `AlertType::MissingTest` doesn't
exist yet; only `FailedCycle` was added this round.
**Needs confirmation:** should Bowie-Dick/Helix instead be modeled as a
standalone daily log independent of any specific production cycle, and
what the actual required test frequency/type-per-device-kind rules are —
that second answer is also what `missing_test` needs before it can be
built without inventing a compliance rule.

## OQ-6 — Near-expiry alert window
**Raised:** S2-ANA-05 (Alert engine), 2026-07-28
**Context:** Nothing in the brief specifies how many days before a batch's
expiry (DLU-adjacent, but this is the *stock* expiry alert, not the label
DLU rule) a near-expiry alert should fire.
**Default implemented:** 30 days (`DetectAlertsAction::NEAR_EXPIRY_DAYS`),
hardcoded, not yet per-tenant configurable.
**Needs confirmation:** is 30 days right, and should this eventually be a
per-tenant setting (packaging/storage-dependent, like the DLU rule table
CLAUDE.md describes for labels) rather than a single constant?

## OQ-5 — Audit log "UI viewer" is API-only; the web app has no tenant
resolution yet
**Raised:** S1-ANA-04 (Audit log), 2026-07-25
**Context:** The backlog item asks for the audit log plus a UI viewer.
Everything shipped so far — S1-ANA-01 through S1-ANA-04 — is the
`/api/v1` surface the mobile app (and, indirectly, an Inertia frontend)
consumes; the token-scoped tenant resolution it relies on
(`App\Http\Middleware\Api\SetCurrentTenant`, reading the Sanctum token)
has no web/session equivalent. The web app currently authenticates via
Fortify exactly as the starter kit shipped it — a single global session
with no concept of "which practice" at all. NAMING.md's subdomain
convention (`{slug}.steriqore.<tld>`) implies the web side should resolve
tenant by subdomain rather than by token, which is a different mechanism
from the API's and not yet built.
**Default implemented:** `GET /api/v1/audit-events` — real, tested, permission
-gated (owner/admin only), tenant-scoped. No Inertia page renders it.
Building one now, ahead of web-side tenant resolution existing, would mean
either a page with no real tenant scoping (a security bug) or reinventing
that resolution ad hoc for one page instead of deciding it properly.
**Needs confirmation:** should web tenant resolution be subdomain-based (matches
NAMING.md) — its own backlog item — before any Inertia/React page (audit
log or otherwise) is built against tenant-scoped data?

## OQ-4 — Sanctum token lifetime
**Raised:** S1-ANA-02 (Identity), 2026-07-25
**Context:** Nothing in the brief specifies how long a mobile session/token
should last before requiring re-login.
**Default implemented:** `config('sanctum.expiration')` = 30 days
(`SANCTUM_TOKEN_EXPIRATION_MINUTES` env override), enforced by Sanctum's
guard against each token's `created_at`. Revocation (logout, logout-everywhere,
disabling a membership) is immediate regardless of this TTL.
**Needs confirmation:** is 30 days right for the pilot, or should it be
shorter given this is health-adjacent data?

## OQ-3 — Migrating `users.id` from bigint to uuid touches a completed item
**Raised:** S0-ANA-02 (ERD), 2026-07-24
**Context:** S0-ANA-01 (repo skeleton) is marked Completed and shipped a
starter-kit `users` table with a bigint auto-increment `id`. The tenancy
invariant requires UUIDv7 ids everywhere. Bringing `users` in line means a
migration that changes its primary key type and updates the `passkeys.user_id`
FK.
**Default implemented:** done — edited the original `0001_01_01_000000` and
`passkeys` migrations in place (not yet run against any shared/prod DB, so no
data was at risk) to bring `users.id` to `uuid` with the same UUIDv7 pattern
as every other table. `ProfileValidationRules::profileRules()`'s `?int
$userId` parameter was widened to `?string` to match — the only call site
that assumed a bigint id.
**Needs confirmation:** OK that this was done without waiting, given it was a
hard blocker for every other table's FKs? Flag if bigint ids on `users`
specifically were kept for a reason not visible in the code.

## OQ-2 — Tenant plan/status values and trial length
**Raised:** S0-ANA-02 (ERD), 2026-07-24
**Context:** `tenants.plan`/`tenants.status` need a concrete enum. Billing is
out of MVP scope, so this is a minimal placeholder. The brief says "15–30 day
self-serve trial" without picking a number, and doesn't define what a
`suspended` tenant should actually be blocked from.
**Default implemented:** `plan`: `trial`/`active`/`suspended`. `status`:
`trialing`/`active`/`suspended`/`cancelled`. `trial_ends_at` nullable
timestamp, no default length enforced in the schema (set at tenant-creation
time in code).
**Needs confirmation:** exact trial length in days, and the precise behaviour
of `suspended` (read-only? fully locked? grace period?).

## OQ-1 — Can a user belong to more than one tenant?
**Raised:** S0-ANA-02 (ERD), 2026-07-24
**Context:** The build brief's domain model lists a `tenant_user` pivot
(implying many-to-many between users and tenants), which would matter for a
locum dentist working across multiple practices. But the tenancy invariant
("the tenant never comes from a request parameter — only from the
session/token") only holds cleanly if each authenticated session/token
resolves to exactly one tenant.
**Default implemented:** schema allows multi-membership at the data level
(`tenant_user` many-to-many), but authentication is single-tenant per
session/token — a user with two memberships logs in once per tenant and gets
a separate session/Sanctum token per tenant, chosen at login. Switching
practice requires re-authenticating, never a mid-session tenant switch.
**Needs confirmation:** does this match the intended workflow, or should the
MVP simply forbid multi-tenant membership altogether (one user = one practice,
full stop) until there's a real need for it?
