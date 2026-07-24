# ANAS — Full Task Extraction

Source: `Dental_SaaS_Sprint_Dashboard_Anas_Meryem_Salim_EN.xlsx` (9 sheets analysed)
Serial dates converted to real dates. Project start: **Mon 27 July 2026**.

---

## 1. Workbook overview

| Sheet | What it holds |
|---|---|
| `Dashboard` | KPIs — 78 tasks total, 2 completed, 0 blocked, 0 overdue |
| `Roadmap_Sprints` | 8 sprints (S0 → S6 + PILOT), each with exit deliverable + gate |
| `Backlog_3_profils` | **Source of truth** — all 78 tasks, 20 columns |
| `Anas_Web_API` | Read-only mirror of your 32 tasks |
| `Meryem_Flutter` | 21 mobile tasks (Flutter Android/iOS) |
| `Salim_Integrations` | 25 product / pilot / partnership tasks |
| `Recette_MVP` | 12 acceptance scenarios — 7 of them owned by you |
| `Risques` | 10 risks — 4 owned by you (R-02, R-06, R-08) |
| `Listes_Mode_emploi` | Update rules, colour legend, allowed values |

**Your load: 32 tasks / 514 h** — that is 66 % of the 78-task backlog by effort, spread over 17 weeks (~30 h/week). Meryem 316 h, Salim 220 h.

**Sprint calendar**

| Sprint | Dates | Objective | Gate |
|---|---|---|---|
| S0 | 27 Jul – 2 Aug | Framing + API contract | Launch Go/No-Go |
| S1 | 3 – 16 Aug | SaaS foundations | Web/mobile login demo |
| S2 | 17 – 30 Aug | Inventory + purchasing | Complete inventory journey |
| S3 | 31 Aug – 13 Sep | Sterilization + 2D codes | Compliant cycle through scan |
| S4 | 14 – 27 Sep | Traceability + reports | Exportable evidence dossier |
| S5 | 28 Sep – 11 Oct | Hardening + compliance | Pre-acceptance, no critical defect |
| S6 | 12 – 25 Oct | MVP acceptance + delivery | Signed acceptance report |
| PILOT | 26 Oct – 22 Nov | Practice pilot | Commercial Go/No-Go |

---

## 2. Your 32 tasks

### S0 — Framing (27 Jul → 2 Aug) · 4 tasks · 46 h

| ID | Domain | Task | Dates | h | Prio | Status | Acceptance |
|---|---|---|---|---|---|---|---|
| S0-ANA-01 | Architecture | Init Laravel + React/Inertia repo and Git conventions | 27–29 Jul | 10 | Blocking | **Completed** | Local install reproducible, base tests pass |
| S0-ANA-02 | Data | Design PostgreSQL multi-tenant model + ERD | 27–31 Jul | 14 | Blocking | **In Progress** | Tenant, sites, users, FKs reviewed |
| S0-ANA-03 | API | Define OpenAPI v1 + error conventions | 29 Jul – 2 Aug | 12 | Blocking | To Do | Auth, tenant, inventory, cycles, labels, scan covered |
| S0-ANA-04 | Infrastructure | Configure PostgreSQL, Redis, S3-compatible storage locally | 29 Jul – 2 Aug | 10 | High | **Completed** | Services start with one documented command |

### S1 — SaaS foundations (3 → 16 Aug) · 4 tasks · 64 h

| ID | Domain | Task | Dates | h | Prio | Status | Depends | Acceptance |
|---|---|---|---|---|---|---|---|---|
| S1-ANA-01 | Tenancy | Tenants, sites, rooms, locations | 3–7 Aug | 18 | Blocking | To Do | S0-ANA-02 | Tests prove isolation between two practices |
| S1-ANA-02 | Identity | Accounts, invitations, sessions, password reset | 3–10 Aug | 18 | Blocking | **In Progress** | S1-ANA-01 | Login, revocation, expiry tested |
| S1-ANA-03 | Permissions | Roles, permissions, server-side policies | 7–14 Aug | 16 | Blocking | To Do | S1-ANA-02 | Horizontal + vertical unauthorized access rejected server-side |
| S1-ANA-04 | Audit | Sensitive-event audit log (append-only) | 10–16 Aug | 12 | High | To Do | S1-ANA-02 | Actor, tenant, time, action, reason retained |

### S2 — Inventory & purchasing (17 → 30 Aug) · 5 tasks · 86 h

| ID | Domain | Task | Dates | h | Prio | Depends | Acceptance |
|---|---|---|---|---|---|---|---|
| S2-ANA-01 | Catalogue | Products, categories, units, barcodes | 17–21 Aug | 18 | Blocking | S1-ANA-03 | CRUD, search, validation, audit work |
| S2-ANA-02 | Suppliers | Suppliers + supplier references | 17–23 Aug | 12 | High | S2-ANA-01 | Product ↔ supplier reference linked |
| S2-ANA-03 | Purchasing | Purchase orders + partial receipts | 21–28 Aug | 22 | Blocking | S2-ANA-01 | Statuses and discrepancies retained |
| S2-ANA-04 | Inventory | Batches, expiries, movements, locations | 20–30 Aug | 24 | Blocking | S2-ANA-01 | **Stock computed from movements, never editable directly** |
| S2-ANA-05 | Alerts | Thresholds, stockout, near-expiry alerts | 26–30 Aug | 10 | High | S2-ANA-04 | Alerts deterministic and filterable |

### S3 — Sterilization & 2D codes (31 Aug → 13 Sep) · 5 tasks · 86 h

| ID | Domain | Task | Dates | h | Prio | Depends | Acceptance |
|---|---|---|---|---|---|---|---|
| S3-ANA-01 | Equipment | Devices, programs, maintenance records | 31 Aug – 4 Sep | 14 | High | S1-ANA-04 | Serial number unique per practice, audited |
| S3-ANA-02 | Cycles | Cycle, load, tests, attachments | 31 Aug – 8 Sep | 24 | Blocking | S3-ANA-01 | States and transitions follow approved rules |
| S3-ANA-03 | Release | Compliant/rejected release + immutability | 5–10 Sep | 16 | Blocking | S3-ANA-02 | Only an authorized person can release a cycle |
| S3-ANA-04 | Labels | Identifiers, QR/DataMatrix, PDF, reprints | 7–13 Sep | 20 | Blocking | S3-ANA-03 | **No label for a rejected cycle** |
| S3-ANA-05 | Scan API | Resolution endpoint + validity rules | 9–13 Sep | 12 | Blocking | S3-ANA-04 | Unknown / recalled / expired codes handled clearly |

### S4 — Traceability & reports (14 → 27 Sep) · 5 tasks · 82 h

| ID | Domain | Task | Dates | h | Prio | Depends | Acceptance |
|---|---|---|---|---|---|---|---|
| S4-ANA-01 | Minimized patient data | External / pseudonymized patient identifiers | 14–18 Sep | 14 | Blocking | Minimum-data approval | No unnecessary patient field required |
| S4-ANA-02 | Traceability | Link labels to procedure + practitioner | 16–23 Sep | 20 | Blocking | S4-ANA-01, S3-ANA-05 | A recalled or rejected label cannot be used |
| S4-ANA-03 | Search | By patient, cycle, batch, device, period | 20–26 Sep | 16 | High | S4-ANA-02 | Pilot result returned in **under 2 s** |
| S4-ANA-04 | Reports | Evidence PDF + CSV/XLSX exports | 21–27 Sep | 16 | High | S4-ANA-02 | Complete export controlled by permissions |
| S4-ANA-05 | Non-conformities | Recalls, quarantine, audited corrections | 19–27 Sep | 16 | Blocking | S3-ANA-03 | **Validated evidence is never overwritten** |

### S5 — Hardening & compliance (28 Sep → 11 Oct) · 5 tasks · 74 h

| ID | Domain | Task | Dates | h | Prio | Acceptance |
|---|---|---|---|---|---|---|
| S5-ANA-01 | Security | Tenant isolation, IDOR, roles, rate limits, uploads | 28 Sep – 4 Oct | 18 | Blocking | No critical/high vulnerability open |
| S5-ANA-02 | Operations | Staging, logs, monitoring, alerts | 28 Sep – 5 Oct | 16 | Blocking | Errors, queues, capacity, certificates monitored |
| S5-ANA-03 | Backup | Automate PostgreSQL/S3 backups + test restore | 1–8 Oct | 14 | Blocking | A complete restoration succeeds |
| S5-ANA-04 | Performance | Index + test at pilot volumes | 4–10 Oct | 12 | High | API and search targets met |
| S5-ANA-05 | Documentation | README, API, deployment, operations docs | 5–11 Oct | 14 | High | A third party can install and operate the system |

### S6 — Acceptance & delivery (12 → 25 Oct) · 3 tasks · 52 h

| ID | Domain | Task | Dates | h | Prio | Depends | Acceptance |
|---|---|---|---|---|---|---|---|
| S6-ANA-01 | Web/API acceptance | Fix critical + high web/API defects | 12–20 Oct | 24 | Blocking | S5 reports | No critical bug, blocking scenarios pass |
| S6-ANA-02 | Deployment | Production, rollback, procedures | 18–24 Oct | 16 | Blocking | S5-ANA-02, S5-ANA-03 | Deployment and rollback tested on staging |
| S6-ANA-03 | Portability | Full export of data and files | 19–25 Oct | 12 | High | Reports + S3 | Export readable, attachments recoverable |

### PILOT (26 Oct → 22 Nov) · 1 task · 24 h

| ID | Domain | Task | Dates | h | Prio | Acceptance |
|---|---|---|---|---|---|---|
| PIL-ANA-01 | Pilot support | Monitor backend, fix incidents, measure performance | 26 Oct – 22 Nov | 24 | High | Incidents tracked, pilot SLA met |

---

## 3. Acceptance scenarios you own (`Recette_MVP`)

| ID | Scenario | Required result | Severity |
|---|---|---|---|
| REC-01 | User of practice A requests a resource of practice B | Server refusal, no leak, security log | **Critical** |
| REC-02 | Same receipt submitted twice | One stock movement only | **Critical** |
| REC-03 | Label generation requested for a rejected cycle | Explicit block | **Critical** |
| REC-05 | A label is reprinted | Reason, counter and audit | High |
| REC-06 | A historical user is disabled | Evidence retains the user's identity | High |
| REC-07 | External API outage simulated | Product core remains available | High |
| REC-09 | Search by patient / cycle / batch | Complete result < 2 s at pilot volume | High |
| REC-10 | Restore PostgreSQL/S3 backup | Data and attachments recovered | **Critical** |

REC-04 and REC-08 are Meryem's; REC-11 and REC-12 are Salim's.

## 4. Risks you own

- **R-02** — unstable API contract between web and mobile → versioned OpenAPI, mocks, contract tests.
- **R-06** — cross-tenant leak or permission error (Low prob. / **Critical** impact) → server policies, IDOR tests, security audit.
- **R-08** — duplicate movement or network approval → transactions, locks, idempotency keys.

## 5. Things worth flagging before you start

1. **S0 is nearly over.** By the calendar in the file, S0 ends 2 August and S1 starts 3 August. S0-ANA-03 (OpenAPI) is still To Do and it blocks Meryem's S0-MER-02 — that is the single most schedule-critical item on your plate right now.
2. **514 h over 17 weeks = 30 h/week**, while the `Cahier des charges` itself only assumes 20–25 h/week per person and warns that 10 weeks is "a target, not a blind promise". Your track is the one that will slip first.
3. **The spec defers AI** ("IA" is in the *reporté après validation du MVP* column). Whatever you build with OpenAI must be optional, feature-flagged, and outside the compliance-evidence path.
4. **No real patient data before S5-SAL-01** (GDPR/HDS decision) — build every demo and pilot dataset pseudonymized from day one.