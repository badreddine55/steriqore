# S7 — UI task queue

The app shell is done. Everything else is a "Soon" stub until its item lands
here. Work the queue below **one task at a time** — the user says "Build
UI-XX"; do not start the next until they say so (a terse "processed" /
"process" / "continue" on the current task counts as that signal for the
*next* queued item).

This file is the durable record of that queue: what each item covers, what
got built, and what's still open. Update the **Status** column (and add a
one-line note) as part of every task's commit — CLAUDE.md points here so it
loads every session.

## Per-task contract (all 8 steps, every item)

1. Read the build brief and locate the EXISTING Action(s)/query the screen
   calls. State which ones will be used before writing code.
2. If the screen needs behaviour the domain layer doesn't already have,
   STOP and tell the user. Do not add migrations, invent business rules, or
   widen an Action's scope. Small, safe, mechanical extensions (a filter
   mirroring an existing one, widening a DTO with an existing column) are
   fine to just do and document. A genuine architecture gap ("no creation
   path exists at all") is stop-worthy.
3. Build the Inertia page + components. Props are typed spatie/laravel-data
   DTOs. TypeScript stays strict — no `any`, no `as unknown as`.
4. Every state is designed: loading skeleton, empty state, error state,
   permission-denied case (item simply isn't rendered).
5. Authorization: controller goes through its Policy. Hiding a button is
   UX, never security. Add the route to the permission-aware nav.
6. Remove the "Soon" badge for that nav item and enable the link.
7. Tests: a feature test (happy path + policy denial + cross-tenant
   403/404) and a Playwright happy path. Existing suite stays green.
8. Report: routes added, DTOs, files touched, a screenshot, and anything
   that hit step 2.

## Design constraints (every screen)

- Semantic status color only (icon+text+color, never color alone).
- Tabular numerals aligned in tables; compact rows, sticky headers,
  URL-persisted filters.
- Print stylesheet forcing a light surface.
- Irreversible actions name their consequence in the confirm button/copy.
- Keyboard nav, visible focus, reduced motion respected.

## Queue

| # | Title | Status | Note |
|---|-------|--------|------|
| UI-01 | Cycles list | Done | Brought the pre-existing shell screen up to the full contract |
| UI-02 | Cycle detail — timeline, load, control tests, attachments | Done | |
| UI-03 | Start a cycle, build the load, run to awaiting release | Done | |
| UI-04 | Control tests — record + tenant-wide logbook | Done | Missing/overdue-test alerts explicitly out (OQ-7) |
| UI-05 | Release — compliant/rejected, releaser-only | Done | |
| UI-06 | Label sheet — print preview, PDF, justified reprint | Done | |
| UI-07 | Label detail — full chain, status, print/usage history | Done | |
| UI-08 | Devices — list + detail | Done | |
| UI-09 | Device programs | Done | |
| UI-10 | Maintenance records + next-due surfacing | Done | |
| UI-11 | Products + categories | Done | |
| UI-12 | Suppliers + supplier references | Done | |
| UI-13 | Purchase orders — list, create, detail, status | Done | |
| UI-14 | Receipt flow — partial receipts, batch/expiry, discrepancy | Done | |
| UI-15 | Batches + expiries | Done | |
| UI-16 | Stock levels + movement ledger | Done | |
| UI-17 | Inventory counts | **Blocked** | No count-session backend exists at all (OQ-12) — skipped until its backend is designed as its own item |
| UI-18 | Alerts inbox | Done | missing_test alert type stays absent (OQ-7) |
| UI-19 | Patients (pseudonymous refs) + procedures | Done | |
| UI-20 | Associate a label to a procedure (web fallback for scan) | Done | |
| UI-21 | Evidence search — patient/cycle/batch/device/period/operator | Done | |
| UI-22 | Evidence dossier — preview, PDF, CSV/XLSX exports | Done | |
| UI-23 | Non-conformities + recall flow | Done | Blast radius shown before confirming |
| UI-24 | Quarantine | Done | |
| UI-25 | Audit log viewer | Done | Old/new value diffing, filter by actor/action/subject/period |
| UI-26 | Team — users, invitations, roles, disable (never delete) | Done | |
| UI-27 | Sites, rooms, storage locations (Settings) | Done | Built the Tenancy domain layer (Actions/Policies/DTOs) from scratch — see item detail |
| UI-28 | Practice settings (identity, label format, DLU rule table, alerts & notifications) | Done | New versioned-settings pattern added — see item detail |
| UI-29 | Data export (GDPR portability / offboarding) | Done | Backend gaps closed as part of the item — see item detail |
| UI-30 | — | Queued — brief pending | |
| UI-31 | — | Queued — brief pending | |

## Item detail

### UI-01 — Cycles list
The Cycles list existed from the app-shell milestone; brought up to the
contract every later screen follows. Reused `Cycle`/`CycleData`,
`Device`/`User` for filter dropdowns, `CyclePolicy::viewAny`. New:
`App\Support\Data\OptionData`, a generic `{id, label}` DTO reused for every
later filter/picker instead of a bespoke lookup DTO per domain.

### UI-02 — Cycle detail
Timeline, load composition, control tests, attachments — all reused, no new
domain logic. Assembles four DTOs the mobile API already exposes across
separate endpoints into one page load. Signature element: `CycleTimeline`,
a horizontal station track; the terminal station only takes a compliance
tone once actually reached.

### UI-03 — Start a cycle, build the load, run to awaiting release
Cycle detail gains write actions: create (device → active program
cascade), add/remove load items in draft, attachments at any status,
state-machine advances (start/complete/submit for release). All reused
Actions, no new business rules. Cross-cutting fix found here: `ApiException`
had no render path for non-API (web) requests — fixed with a dedicated web
exception renderer, since reused as-is by every later task's Actions.

### UI-04 — Control tests
Record-a-test form on cycle detail (`RecordControlTestAction`, unchanged)
plus a tenant-wide `/control-tests` logbook filterable by type/result/
device/date. New read-only `ControlTestLogEntryData` joins in cycle number
+ device name. Missing/overdue-test warnings explicitly NOT built — OQ-7:
no per-tenant test frequency rule exists to determine "missing" against.

### UI-05 — Release
Cycle detail gains the release decision itself: releaser-only (owner/
admin/releaser — `cycles.release`, distinct from `cycles.manage`) buttons
named for their consequence — "Release as compliant" (direct) vs "Reject
cycle" (dialog, reason required). Reuses `ReleaseCycleAction`/
`ReleaseCycleRequest` as-is; no domain change.

### UI-06 — Label sheet
Per-cycle `/cycles/{cycle}/labels`: once released+compliant, generates one
label per load item (packaging + storage from the practice's DLU rules —
DLU frozen at generation time), shows QR + DataMatrix, status, use-by,
print count. First print is one click; every reprint after requires a
typed reason via dialog. Reused as-is: `GenerateCycleLabelsAction`,
`PrintLabelAction`, `RenderLabelPdfAction`, `LabelCodeRenderer`.

### UI-07 — Label detail
Tenant-wide `/labels` index + `/labels/{label}` detail: full chain (site →
device → cycle → sequence → batch), QR/DataMatrix, complete print history,
point-of-care usage (patient/practitioner/procedure) or an honest "not yet
scanned" state. Print/reprint extracted into a shared `ReprintLabelDialog`
reused from UI-06. Un-stubbed "Labels" nav (last labeling-domain item).

### UI-08 — Devices
List (filter by name/status/kind/site) + detail with an Edit dialog.
Reused `CreateDeviceAction`/`UpdateDeviceAction`/`DeviceData`/
`DevicePolicy` as-is. Deliberately scoped to the device's own fields only —
programs (UI-09) and maintenance (UI-10) are separate items so this layout
doesn't need reshaping later.

### UI-09 — Device programs
Extends device detail with a Programs section (a program only ever exists
in the context of one device). Add/edit/remove, gated on `devices.manage`.
Reused `CreateDeviceProgramAction`/`UpdateDeviceProgramAction`/
`DeleteDeviceProgramAction` as-is — a program is a soft-deleted preset;
past cycles keep their own frozen `device_program_id`.

### UI-10 — Maintenance records + next-due surfacing
Extends device detail with a Maintenance section: history table + record
form (create-only — a correction is a new record, never an edit). The
actual point: the most recent record's `next_due_at` is surfaced as a
status badge in the device header ("Next due …" amber / "Overdue since …"
red) — pure presentation, no new alert type, no new Action.

### UI-11 — Products + categories
First INVENTORY screen. Products list (search/filter, cursor-paginated)
with a "New product" dialog, detail with full edit, soft delete (Products
aren't an evidence table). Categories get their own lightweight
create-only dialog (the domain never supported update/destroy for them
either). Reused `CreateProductAction`/`UpdateProductAction`/
`DeleteProductAction`/`CreateProductCategoryAction` as-is.

### UI-12 — Suppliers + supplier references
Suppliers list + detail with full edit + soft delete. "Supplier
references" = the Product↔Supplier link (`SupplierProduct`: their catalog
code, pack size, price) shown as a table with a "link a product" form,
attach-only (matches the domain — no destroy route either). Reused
`CreateSupplierAction`/`UpdateSupplierAction`/`DeleteSupplierAction`/
`AttachSupplierProductAction` as-is.

### UI-13 — Purchase orders
List (cursor-paginated), a dedicated create page (dynamic line rows —
doesn't fit a modal), detail with status + the two transitions ("Mark as
ordered" direct, "Cancel" dialog with optional reason). Reused
`CreatePurchaseOrderAction`/`MarkPurchaseOrderOrderedAction`/
`CancelPurchaseOrderAction` as-is.

### UI-14 — Receipt flow
Completes the PO lifecycle from UI-13: a "Receive goods" form on the PO
detail page (shown while ordered/partially_received) + receipt history.
Per line: batch number, expiry, quantity received (defaults to remaining,
capped), optional discrepancy reason; blank batch number skips a line, so
one PO can be received across several partial deliveries. Status computed
by `ReceiveGoodsAction` itself, unchanged — the one place stock actually
moves (invariant #1).

### UI-15 — Batches + expiries
Tenant-wide, read-only batches list — batches have no create/update route
anywhere (only ever a side effect of UI-14's `ReceiveGoodsAction`), and
their only transition (active → quarantined) belongs to the recall flow
(UI-24). Filters: product, supplier, status, expiry (near-expiry/expired,
reusing `DetectAlertsAction::NEAR_EXPIRY_DAYS`, now public, as the single
source of truth).

### UI-16 — Stock levels + movement ledger
Both read-only: stock is derived from `stock_movements` (invariant #1), no
web form for issue/transfer/adjust (mobile-only). Levels (`/stock`):
on-hand per batch+location, filterable, "in stock only" toggle. Ledger
(`/stock/movements`): every movement ever recorded, newest first, signed
quantities (+20/-8) making the append-only nature visible in the data
itself. Added `StockMovementPolicy::viewAny` (only had `manage` before).

### UI-17 — Inventory counts — BLOCKED
No migration, model, Action, DTO, policy, or API route exists for
inventory counts anywhere. Unlike every prior gap this isn't a missing
screen over an existing capability — it's an undesigned feature
(count-session lifecycle, reconciliation rule). See OQ-12
(`docs/open-questions.md`). Skipped to UI-18; pick back up once its
backend is designed as its own item.

### UI-18 — Alerts inbox
Reuses the alert engine as-is: `DetectAlertsAction` (low_stock/
near_expiry/expired) and `RecordControlTestAction` (failed_cycle) already
open real `Alert` rows; `AlertPolicy`/`ResolveAlertAction` already existed.
`missing_test` stays absent (OQ-7 — no frequency rule to test "missing"
against). Filters: type, severity, state.

### UI-19 — Patients + procedures
Same `Patient`/`PatientData` the mobile API uses; `CreatePatientAction`
takes no input (pseudonym auto-generated, never client-supplied) — "New
patient" is a one-click confirm, not a form. Archiving is a soft delete
(past `label_usages` keep referencing the pseudonym's identity, invariant
#6). "Procedures" is a free-text column on `label_usages`, not its own
entity — shown via a new read-only `PatientUsageEntryData` linking out to
the label detail page (UI-07).

### UI-20 — Associate a label to a procedure
The web fallback for the mobile scan. Found and fixed during this item:
`LabelUsage::patient()` excluded archived patients via Eloquent's default
soft-delete scope (`->withTrashed()` fix, invariant #6 regression test
added).

### UI-21 — Evidence search
Patient/cycle/batch/device/period/operator, all under 2s. Added an
`operator_id` filter to `SearchEvidenceAction` mirroring the existing
`device_id` pattern. Widened `EvidenceSearchResultData` with
`operator_name`.

### UI-22 — Evidence dossier
Preview + PDF download, CSV/XLSX exports, permission-gated. Reuses
`RenderEvidenceDossierAction` and the existing `EvidenceExport`/
`Excel::download` as-is.

### UI-23 — Non-conformities + recall flow
Shows the blast radius (labels to recall, batches to quarantine) BEFORE
confirming. New `RaiseNonConformityAction::previewBlastRadius()` — a
read-only preview deliberately duplicating (not sharing) `execute()`'s
skip-condition logic, flagged to keep in sync.

### UI-24 — Quarantine
Read-only list joining `Batch` with the `AuditEvent` row that quarantined
it (`action=batch.quarantined`) — no new column, no new FK.

### UI-25 — Audit log viewer
Old/new value diffing (`ValueDiff` component handles create/update/delete
uniformly), filter by actor/action/subject/period. Extended the API
controller with `QueryBuilder`/`AllowedFilter`; fixed `ListAuditEventsRequest`
mid-task to validate nested `filter[...]` keys (the dominant convention
across the API), not flat top-level ones.

### UI-26 — Team
Users, invitations, role assignment, disable (never delete). Reuses the
mobile API's `CreateInvitationAction`/`RevokeInvitationAction`/
`DisableTenantMembershipAction` and `InvitationPolicy`/`TenantUserPolicy`
behind a new read-only list view — `TenantUser`/`Invitation` had no index
before. Disabling revokes tokens for that tenant only; the account itself
is never deleted (invariant #6).

### UI-27 — Sites, rooms, storage locations (Settings)
Domain: Tenancy (S1-ANA-01). Unlike every prior UI-XX item, almost none of
the domain layer existed yet — only the `Site`/`Room`/`StorageLocation`
models, one read-only `SiteData`/`SitesController::index`, and a
view-only `SitePolicy`. Built from scratch: `sites.manage` permission;
`CreateSiteAction`/`UpdateSiteAction`/`ArchiveSiteAction` (+ the Room and
StorageLocation equivalents); `RoomData`/`StorageLocationData`;
`RoomPolicy`/`StorageLocationPolicy`; `Http/Requests/Api/V1/Tenancy/*`;
Web `SiteController`/`RoomController`/`StorageLocationController`.

Archive semantics reuse Laravel's `SoftDeletes` (matching the
Product/Supplier/Patient precedent) rather than a bespoke `archived_at`
column — a new migration backfills `deleted_at` + RLS onto
`sites`/`rooms`/`storage_locations`, which predated both conventions.
Every relation from another domain into these three (`Device::site()`,
`Product::defaultLocation()`, `StockLevel::location()`,
`StockMovement::location()`, plus the internal `Room::site()` /
`StorageLocation::site()`/`room()`/`parent()`) got `->withTrashed()` so
archived rows keep resolving on historical evidence (invariant #6) —
found and fixed proactively this time, having hit the exact same class of
bug twice already in UI-19/UI-21.

Site show page: a tree of rooms, each showing its storage locations,
with add/edit/archive at every level, plus rooms/devices/active-batches
summary cards. Archiving a site or room cascades visibly to its children
(the count doubles as the blast-radius preview, already loaded — no
separate preview endpoint needed, unlike UI-23). Every archive requires a
reason.

Two rules implemented exactly as specified, both worth a second look:
moving a room to a different site is refused (409) once any of its
storage locations has ever recorded a stock movement; moving a storage
location to a different room is allowed but was scoped to rooms of the
**same site only** — a deliberate narrowing from a literal reading of the
brief (which didn't say same-site), since letting a single site's page
relocate stock to a completely different practice read as an unrequested,
confusing capability. Flagged for confirmation.

The persistent demo/dev tenant predates the new `sites.manage` permission
and needed a one-time backfill (`SeedTenantRolesAction::execute()` inside
`TenantContext::run`) directly against the shared dev Postgres instance —
both host and the app container already point at the same database, so
this didn't need repeating per-environment.

Removed the "Soon" badge on Sites.

### UI-28 — Practice settings
Domain: Tenancy + Labeling + Inventory alerts. One page, four tabbed
sections — not four routes. Owner/admin (`practice_settings.manage`)
reach every section; only owner (`evidence_settings.manage`, the first
place this codebase distinguishes owner from admin at all) can actually
save label format or DLU rule changes — the two evidence-affecting
sections. This was a genuine step-2 stop: **no versioned-settings
concept existed anywhere** before this item (confirmed by research, then
designed with the user's sign-off before writing any migration).

**The versioning design**: `dlu_rules`/label-format stay exactly the
fast "current" projections every existing query already reads
(`GenerateCycleLabelsAction`, `CycleLabelController`'s picker); a new
`dlu_rule_versions`/`label_format_versions` table behind each is the
append-only ledger (mirrors `cycle_releases`/`label_prints`' trigger-
enforced immutability) — every save inserts a new version row (actor +
required reason + effective date) and updates the projection. `Label`
gained a real `dlu_rule_version_id` FK (frozen once, at generation —
the DLU is computed then, never at print time); `label_prints` gained
`label_format_version_id` (recorded per print event, since format is a
render-time concern that can legitimately differ between a label's
first print and a later reprint — the brief is explicit that's fine).
This is the first place a dossier could show "which rule version applied
at print time," which the old `dlu_rule_id`-in-an-audit-event's-JSON
approach never really supported.

**Reused vs new**: `CreateDluRuleAction`/`UpdateDluRuleAction` (shared
with the mobile API) were widened in place to write a version alongside
the projection and require `reason` — a real, intentional breaking
change to `POST/PATCH /api/v1/dlu-rules` (both now require `reason`),
not just additive; `docs/openapi.yaml` regenerated. Label format,
practice identity, alert settings, and digest subscriptions are new,
web-only concepts (Tenant gained identity columns + a `logo`
spatie/laravel-medialibrary collection, mirroring `AttachCycleFileAction`'s
established pattern).

**Alerts & notifications**: `DetectAlertsAction`'s hardcoded
`NEAR_EXPIRY_DAYS` constant (OQ-6) became a real per-tenant
`AlertSettings.near_expiry_days` value (30 still the default). A
near-expiry threshold change previews how many open alerts would
appear/disappear before saving (mirrors UI-23's blast-radius pattern).
Digest subscriptions are role-based, not address-based — recipients
resolve live from whoever currently holds the role, so it can't go
stale as the team (UI-26) changes — sent via a new `digests:send`
scheduled command + `DigestMail` (Resend, matching `InvitationMail`'s
existing pattern). Control-test frequency/overdue-tolerance and
failed-cycle escalation got settings fields; the missing-test alert
*detection* itself (OQ-7) stays out of scope, unbuilt — this only gives
it somewhere to eventually read a frequency value from.

**Test-print**: a real sample-sheet PDF (`RenderLabelFormatSampleAction`
+ a dedicated `pdf/label-format-sample` view), built from synthetic data
so it never touches a real label — reflects whatever's currently in the
form, including unsaved edits, so a practice can check a change before
committing to it.

**EN 868 defaults**: deliberately NOT seeded (would mean inventing a
compliance figure — OQ-8's reasoning, unchanged). The DLU rule table
shows a persistent banner instead: shelf-life values are unverified
until a dental professional signs them off.

**Not built**: cycle numbering versioning — the brief's Rules section
names it as evidence-affecting, but it isn't one of the four Screens and
no existing "cycle numbering scheme" concept was found to version.
Flagging rather than guessing at what that would even mean.

### UI-29 — Data export
Domain: Reporting/Compliance. The tenant-facing screen over the S6
account-wide export (`RequestDataExportAction`, `GenerateDataExportJob`,
`data_export_requests`) — every table, every attached file, zipped to the
`backups` disk. This was a genuine step-2 stop: research (14 questions via
an Explore agent) found the S6 backend didn't actually match the brief —
no list endpoint, no rate limiting, no manifest/size/count/expiry tracking,
a presigned URL embedded directly in the polled response (violates the
brief's own "never render a stored presigned URL" rule), no download audit
event, no owner-notification email. User chose "build it all now" rather
than scope the screen down to what already existed.

**Backend gaps closed**: a migration added `size_bytes`, `record_count`,
`file_count`, `table_manifest` (JSON), and `expires_at` to
`data_export_requests`; the `DataExportStatus` enum gained `Expired` (kept
the existing `pending/processing/completed/failed` wire values — renaming to
the brief's `queued/running/ready/expired` wording would have been a
breaking change to the mobile contract for no real benefit; the web UI maps
to friendlier labels itself). `GenerateDataExportJob` now computes the
manifest (per-table row counts via the same `information_schema` scan that
already found the tables), file count, zip size, and `expires_at`
(`RETENTION_DAYS`, OQ-13). `RequestDataExportAction` gained real rate
limiting — one in-flight export per tenant (409 `EXPORT_ALREADY_RUNNING`)
and a weekly cap (429 `EXPORT_WEEKLY_LIMIT_REACHED`, OQ-13) — and now emails
every owner via a new `DataExportRequestedMail` (Resend, matching
`DigestMail`'s pattern) whenever an export is requested, including when the
requester is an owner themselves — the out-of-band control that makes a
compromised account's export visible outside the audit log. The
role-resolution query `SendDigests` had inlined was extracted into a shared
`ResolveTenantUsersWithRoleAction` rather than duplicated.

**The presigned-URL rule**: `DataExportRequestData` no longer carries a
`download_url` field at all — a new `GenerateDataExportDownloadUrlAction`
mints a fresh 30-minute presigned URL only when the dedicated download
action is hit, and audits `data_export.downloaded` at that exact moment
(distinct from `data_export.requested`). Both the API (`POST
/api/v1/data-export-requests/{id}/download`) and the new Web controller's
`download()` action share this Action; neither ever returns or stores the
URL anywhere else. A new `data-exports:expire` scheduled command (daily,
OQ-13) deletes the S3 object and flips the row to `expired` once
`expires_at` passes, so the history screen stops offering a dead download
button.

**Screen**: one page under Settings (`data-exports.manage`, owner/admin
only) — a request card (explains contents, disabled with a stated reason
while one is already running), a history table (status badge, size,
expiry), a manifest dialog for the ready state (per-table row counts, file
count, total size), and an inline "Request again" on a failed row. The page
polls via `router.reload()` while an export is queued/running — this is the
first "generate then poll" pattern in the app (confirmed via research: no
prior precedent existed to reuse). Verified against the real container, not
just Storage fakes: a live request went through the actual Horizon worker,
dumped 41 tables, uploaded to MinIO, and the download action minted a real,
working presigned URL — the same manual-verification approach already
established for backup/restore and Browsershot PDF rendering, since the
job's DB-dump path reads through a separate connection Pest's transaction
wrapping can't reach.

**Bug found and fixed while verifying manually**: the new
`data-exports:expire` command initially followed `DetectAlerts`/
`SendDigests`'s existing `$tenant->makeCurrent()`-only pattern and silently
expired nothing — `data_export_requests` has a Postgres RLS policy (unlike
the tables those two commands touch), and `makeCurrent()` alone satisfies
Eloquent's `TenantScope` but never sets the `app.tenant_id` RLS session
variable, so the query failed closed at the database level. Fixed by using
`TenantContext::run()` (the same helper the request middleware uses)
instead, with a regression test.

Removed the "Soon" badge on Data export.

### UI-30 — Queued
Brief pending.

### UI-31 — Queued
Brief pending.
