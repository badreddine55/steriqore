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
| UI-27 | Sites, rooms, storage locations (Settings) | **Next** | |
| UI-28 | — | Queued — brief pending | |
| UI-29 | — | Queued — brief pending | |
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
**Next.** Domain: Tenancy (S1-ANA-01). The three-level hierarchy the whole
app hangs off: tenant → sites → rooms → storage_locations — devices attach
to a site, products have a default storage location, batches live in one,
stock movements record a from/to location, the shell's site switcher reads
from it. Admin/owner only.

- Sites list + detail: create, rename, archive; show what a site currently
  holds (rooms, devices, active batches) so archiving's consequence is
  visible.
- Rooms nested under a site.
- Storage locations nested under a room — the level stock actually sits
  at, needs to be quick to add several in a row.
- Tree / master-detail layout, not three disconnected CRUD tables.

Rules: never hard-delete (archive instead — hidden from pickers, still
resolvable on every past record); if removal is attempted on something
referenced, show what references it and offer archive instead; archiving
a site cascades visibly to its rooms/locations (state the count before
confirming, same pattern as UI-23's blast radius); names unique per parent,
scoped by tenant; every mutation audited with actor + reason; cross-tenant
403/404; moving a location between rooms is allowed, moving a room between
sites with stock history is NOT (flag if this assumption is wrong).

Remove the "Soon" badge on Sites when done.

### UI-28 — Queued
Brief pending.

### UI-29 — Queued
Brief pending.

### UI-30 — Queued
Brief pending.

### UI-31 — Queued
Brief pending.
