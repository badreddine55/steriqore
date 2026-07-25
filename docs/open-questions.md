# Open Questions

Decisions no one has made yet where the safe default was implemented and the
question was flagged instead of guessed, per `CLAUDE.md`. Newest on top.

---

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
