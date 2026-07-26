# Open Questions

Decisions no one has made yet where the safe default was implemented and the
question was flagged instead of guessed, per `CLAUDE.md`. Newest on top.

---

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
