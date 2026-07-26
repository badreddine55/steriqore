# Performance at pilot volume (S5-ANA-04)

CLAUDE.md's targets: "Common screens < 2s; evidence search < 2s at pilot
volume (index deliberately, GIN/tsvector)." REC-09: "search by
patient/cycle/batch -> complete result < 2s at pilot volume."

"Pilot volume" isn't given an exact number anywhere in the brief, so this
picks a concrete, documented scale rather than leaving the target
untested against any real figure — roughly one busy single-practice
year: **3 devices, 3,000 cycles, 10 pouches/cycle (30,000 labels/label
usages), 2,000 patients, 500 batches**. Generated via
`php artisan steriqore:seed-pilot-volume` (`app/Console/Commands
/SeedPilotVolume.php`) — bulk SQL inserts, not the API, so seeding itself
takes seconds rather than being part of what's timed.

## Method

Measured against the real Docker stack (not the test suite, which uses
an in-memory/local setup for speed) — a real HTTP request, through Sanctum
auth and the `tenant` middleware, to the running Octane/FrankenPHP
container, hitting real PostgreSQL 18.

## Results

| Request | Time |
|---|---|
| `GET /evidence-search` (no filter, worst case) | 44ms |
| `GET /evidence-search?patient_reference=...` | 19ms |
| `GET /evidence-search?batch_number=...` | 18ms |
| `GET /evidence-search?cycle_number=...` | 16ms |
| `GET /evidence-search?from=...&to=...` (full window) | 17ms |
| `GET /evidence-search/export?format=xlsx` (last 30 days, ~250 rows) | 83ms |
| `GET /evidence-search/export?format=xlsx` (all 30,000 rows) | 7.1s |
| `GET /cycles` | 13ms |
| `GET /patients` | 9ms |

Every *search* path is roughly 100x under the 2s target. `EXPLAIN
ANALYZE` on the underlying queries confirms every filter (patient
reference, cycle number, batch number, device, date range) resolves via
an index scan, never a sequential scan — the `label_usages
(tenant_id, used_at)` and `cycle_items.batch_id` indexes added in
S4-ANA-03, plus the existing unique constraints that double as indexes
on their leading columns (`cycles (tenant_id, device_id, cycle_number)`,
`cycle_items (cycle_id, sequence_in_cycle)`, `labels.cycle_item_id`,
`label_usages.label_id`).

GIN/tsvector was deliberately not added anywhere: every filter here is an
exact or prefix match on a structured column, not free-text search —
plain btree is the correct index type for this filter set, not a
full-text one.

## The one number outside target: full-history export

Exporting *all 30,000 rows* in one XLSX takes ~7 seconds — outside the
2s target, but the target is explicitly for *search*, not bulk exports,
and a realistic export (a date-bounded range, e.g. "last 30 days" — 83ms
for ~250 rows) is nowhere near it. Flagged here rather than silently
building a queued/async export nobody asked for: **if a tenant is
expected to routinely export their entire multi-year history in one
request, that likely needs `Excel::queue()` instead of the current
`Excel::download()`** (spatie/laravel-backup and Horizon are both
already in the stack for this) — not implemented, since nothing in
CLAUDE.md or the backlog asks for exports specifically to meet the 2s
bar, and doing so would be inventing scope.

## Cleaning up the pilot-volume tenant

The seeded tenant is fully isolated (its own `tenant_id`) and doesn't
interfere with anything else — safe to leave for further exploration, or
remove with:

```sql
delete from tenants where slug like 'pilot-volume-%';
```

(every child table cascades on `tenant_id`, per the existing FK
constraints).
