# DrCare Payroll & Attendance System

> Generated from the DrCare knowledge graph — traces the full pipeline from clock-in to expense report.

---

## Overview

The DrCare payroll system connects three domains:

```
Attendance (time tracking) → Payroll (run generation) → Expenses (accounting)
```

Each domain is a service with its own models, controllers, and CRUD interfaces. The flow is linear and transactional — each step locks the previous one.

---

## 1. Attendance — Time Tracking

### Service: `AttendanceService` (`app/Services/AttendanceService.php`)

The attendance system is a simple **clock in → clock out** timesheet with four possible states:

| Status | Meaning |
|--------|---------|
| `clocked_in` | Employee is actively working |
| `clocked_out` | Shift complete, hours calculated |
| `absent` | Marked absent manually |
| `on_break` | On a break |

### Clock-In Flow

```
clockIn($userId, $note)
  1. Check no active clock-in exists → throws if already clocked in
  2. Create Attendance record:
     - user_id, clock_in_at (now), clock_in_ip, note
     - status = "clocked_in"
```

The guard prevents double clock-ins:
```php
// AttendanceService.php:23
$activeRecord = Attendance::forUser($userId)->clockedIn()->first();
if ($activeRecord instanceof Attendance) {
    throw new NotAllowedException(__("You're already clocked in."));
}
```

### Clock-Out Flow

```
clockOut($userId, $note)
  1. Find active clock-in record → throws if none found
  2. Calculate total_hours = diffInMinutes(clock_in, now) / 60
  3. Update:
     - clock_out_at, clock_out_ip, clock_out_note
     - total_hours = rounded diff
     - status = "clocked_out"
```

### Model: `Attendance` (`app/Models/Attendance.php`)

Database table: `nexopos_attendance`

```php
// Key fields
id | user_id | clock_in_at | clock_out_at | total_hours | status | author_id
```

Key scopes used by Payroll:
```php
scopeForUser($userId)         → WHERE user_id = ?
scopeForPayPeriod($start,$end)→ WHERE clock_in_at BETWEEN ? AND ?
scopeClockedIn()              → WHERE status = 'clocked_in'
```

**Critical bridge method** — prevents double-payment:
```php
public function payrollItem()
{
    return $this->hasOne(PayrollRunItem::class, 'attendance_id');
}
```

### CRUD: `AttendanceCrud` (`app/Crud/AttendanceCrud.php`)

- Identifier: `ns.attendance` (table: `nexopos_attendance`)
- Permissions: `attendance.create`, `.read`, `.update`, `.delete`
- Supports manual entry, edit, delete via standard CRUD UI

### Graph Node: `Attendance`

```
Degree: 9
Connections:
  → .user()            [EXTRACTED]  belongsTo User
  → .author()          [EXTRACTED]  belongsTo User (recorded by)
  → .payrollItem()     [EXTRACTED]  hasOne PayrollRunItem ← BRIDGE TO PAYROLL
  → .scopeClockedIn()  [EXTRACTED]  scope query
  → .scopeForUser()    [EXTRACTED]  scope query
  → .scopeForToday()   [EXTRACTED]  scope query
  → .scopeForDate()    [EXTRACTED]  scope query
  → .scopeForPayPeriod()[EXTRACTED] scope query
```

---

## 2. Payroll — Run Generation

### Service: `PayrollService` (`app/Services/PayrollService.php`)

The payroll lifecycle has **three states** — a strict progression:

```
DRAFT ──postRun()──▶ POSTED ──voidRun()──▶ VOID
  │                       │
  └──deleteDraft()──┘     (accounting entry created)
```

### Step 1: Eligibility

Employees qualify if they have an `hourly_rate` set and are `active`:

```php
// PayrollService.php:24
return User::whereNotNull('hourly_rate')->where('active', true)->get();
```

### Step 2: Period Boundaries

Supports three pay periods:

| Type | Start | End |
|------|-------|-----|
| `weekly` | Sunday 00:00 | Saturday 23:59 |
| `bi-weekly` | 1st or 16th of month | 15th or end of month |
| `monthly` | 1st of month | last day of month |

### Step 3: Create Draft — `createDraft($userId, $periodStart, $periodEnd)`

The core payroll generation logic:

```
1. Validate user has hourly_rate set
2. Check no existing run overlaps (same user + period + draft/posted status)
3. Fetch UNPAID attendance:
   → Attendance::forUser()
     → forPayPeriod()
     → where status = clocked_out
     → where total_hours > 0
     → whereDoesntHave('payrollItem')    ← THE KEY FILTER
4. For each attendance record:
   → pay_amount = hours_worked × hourly_rate
   → Create PayrollRunItem (one per attendance)
5. Save PayrollRun header with:
   → total_hours, hourly_rate, gross_pay
   → status = "draft"
```

**The `whereDoesntHave('payrollItem')` call at line 36 is what prevents double-payment.** Once an attendance record has a linked `PayrollRunItem`, it disappears from eligible hours.

### Step 4: Post Run — `postRun($runId)`

Finalizing a draft:

```
1. Validate status = "draft" and gross_pay > 0
2. Find "Salaries And Wages" expense account:
   → TransactionAccount::where('name', 'Salaries And Wages')
                      ->where('category_identifier', 'expenses')
3. Create Transaction (header):
   → name = "Payroll: {username} — {period}"
   → value = gross_pay
   → type = "ns.direct-transaction"
   → account_id = Salaries account
   → active = false
4. Create TransactionHistory (ledger entry):
   → operation = "debit"
   → value = gross_pay
   → transaction_account_id = Salaries account
   → status = "active"
5. Link transaction_id back to PayrollRun
6. status = "posted"
```

### Step 5: Void Run — `voidRun($runId)`

Reverses a posted run:

```
1. Validate status = "posted"
2. Delete TransactionHistory records linked to the transaction
3. Delete Transaction record
4. Clear transaction_id on PayrollRun
5. status = "void"
```

### Step 6: Summary — `getPayrollSummary($startDate, $endDate)`

Aggregates all posted runs:

```php
return [
    'total_employees' => $runs->unique('user_id')->count(),
    'total_hours'     => $runs->sum('total_hours'),
    'total_gross_pay' => $runs->sum('gross_pay'),
    'total_runs'      => $runs->count(),
];
```

### Controller: `PayrollController` (`app/Http/Controllers/Dashboard/PayrollController.php`)

| API Endpoint | Method | Description |
|-------------|--------|-------------|
| `POST /api/payroll/draft` | `createDraft()` | Create a payroll draft |
| `POST /api/payroll/{id}/post` | `postRun()` | Post (finalize) a run |
| `POST /api/payroll/{id}/void` | `voidRun()` | Void a run |
| `DELETE /api/payroll/{id}/draft` | `deleteDraft()` | Delete a draft |
| `GET /api/payroll/runs` | `getRuns()` | List runs (with filters) |
| `GET /api/payroll/{id}` | `getRun()` | Get run detail with items |
| `GET /api/payroll/{id}/payslip` | `getPayslip()` | Get payslip |
| `GET /api/payroll/eligible-users` | `getEligibleUsers()` | List eligible employees |
| `GET /api/payroll/unpaid-attendance` | `getUnpaidAttendance()` | Get unpaid hours |
| `GET /api/payroll/period-boundaries` | `getPeriodBoundaries()` | Get period dates |
| `GET /api/payroll/summary` | `getSummary()` | Get aggregated summary |

### Routes: `routes/web/payroll.php`

```php
GET  /payroll              → listRuns()   (page)
GET  /payroll/create       → createRun()  (page)
GET  /payroll/{id}         → showRun()    (page)
```

### Models

**PayrollRun** (`app/Models/PayrollRun.php`) — table: `nexopos_payroll_runs`
```
id | user_id | period_start | period_end | period_type | total_hours | hourly_rate | gross_pay | status | transaction_id | author_id
```

**PayrollRunItem** (`app/Models/PayrollRunItem.php`) — table: `nexopos_payroll_run_items`
```
id | payroll_run_id | attendance_id | user_id | date | clock_in | clock_out | hours_worked | hourly_rate | pay_amount
```

`PayrollRunItem.attendance_id` is the foreign key that links back to `Attendance` — the **bridge** between time tracking and payment.

### Graph Node: `PayrollService`

```
Degree: 13
Methods:
  → __construct(TransactionService)  — injects accounting dependency
  → getEligibleUsers()               — find employees with hourly_rate
  → getUnpaidAttendance()            — fetch clocked-out, unpaid hours
  → getPeriodBoundaries()            — calculate pay period dates
  → createDraft()                    — main payroll generation
  → postRun()                        — finalize → create accounting entry
  → voidRun()                        — reverse → delete accounting entry
  → deleteDraft()                    — remove draft
  → getRuns()                        — query runs
  → getRunWithItems()                — full detail
  → getPayslip()                     — employee payslip
  → getPayrollSummary()              — aggregated totals
```

---

## 3. Expenses — Accounting Integration

### Service: `TransactionService` (`app/Services/TransactionService.php`)

The accounting layer handles ALL money movements in the system. Payroll is one of several transaction sources.

### Account Types: `TransactionAccount` (`app/Models/TransactionAccount.php`)

Table: `nexopos_transactions_accounts`

Accounts are categorized by `category_identifier`:
- `expenses` — money out (including salaries)
- `income` — money in (sales, services)
- `assets` / `liabilities` / `equity`

The "Salaries And Wages" account is an **expense** account:
```php
$salariesAccount = TransactionAccount::where('name', 'Salaries And Wages')
    ->where('category_identifier', 'expenses')
    ->first();
```

### Transaction Types: `Transaction` (`app/Models/Transaction.php`)

Table: `nexopos_transactions`

| Type constant | Value | Purpose |
|--------------|-------|---------|
| `TYPE_DIRECT` | `ns.direct-transaction` | One-time, used by payroll |
| `TYPE_SCHEDULED` | `ns.scheduled-transaction` | Future-dated |
| `TYPE_RECURRING` | `ns.recurring-transaction` | Repeating (rent, utilities) |
| `TYPE_ENTITY` | `ns.entity-transaction` | Tied to an entity |
| `TYPE_INDIRECT` | `ns.indirect-transaction` | Indirect entries |

### Ledger Entries: `TransactionHistory` (`app/Models/TransactionHistory.php`)

Table: `nexopos_transactions_histories`

Each transaction has history records that form the **double-entry ledger**:

```
id | transaction_id | operation (debit/credit) | value | transaction_account_id | status | trigger_date | type
```

When a payroll run is **posted**, the system creates:

```
Transaction (header):
  name     = "Payroll: johndoe — 2026-04-01 / 2026-04-15"
  value    = $2,400.00 (gross pay)
  type     = "ns.direct-transaction"
  account  = "Salaries And Wages" (expense category)

TransactionHistory (ledger line):
  operation            = "debit"
  value                = $2,400.00
  transaction_account  = "Salaries And Wages"
  status               = "active"
```

When a run is **voided**, both records are **deleted** — hard reversal.

### Other Expense Sources

The `TransactionService` handles multiple transaction sources beyond payroll:

| Source | Method | Description |
|--------|--------|-------------|
| **Payroll** | `postRun()` in PayrollService | Salaries & wages |
| **Sales** | `handleSaleTransaction()` | Order revenue → income accounts |
| **COGS** | `handleCogsFromSale()` | Cost of goods sold |
| **Procurement** | `handleProcurementTransaction()` | Inventory purchases |
| **Recurring** | `triggerRecurringTransaction()` | Rent, utilities, subscriptions |
| **Manual** | `create()` | Direct user-entered transactions |

### How Sale Transactions Work

```
handleSaleTransaction(Order):
  1. Determine rule based on payment status (paid/unpaid/refunded)
  2. Look up TransactionActionRule for that status
  3. Find the source and offset accounts
  4. Create TransactionHistory:
     - operation = debit/credit (from config)
     - value = order total
     - account = matched from category_identifier
```

### Graph Node: `TransactionService`

```
Degree: 43  ← one of the most connected nodes
Key methods:
  → handleSaleTransaction()            — order revenue
  → handleCogsFromSale()               — cost of goods sold
  → handleProcurementTransaction()     — inventory purchases
  → handleRecurringTransactions()      — recurring expenses
  → createOrderTransactionHistory()    — general ledger entry
  → recordTransactionHistory()         — raw history record
  → triggerTransaction()               — execute a transaction
```

---

## Full Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                     ATTENDANCE (Time Tracking)                       │
│                                                                     │
│  clockIn() ──────────────▶ Attendance ──────▶ clockOut()             │
│                            (clocked_in)          │                   │
│                                                   │                  │
│                              total_hours ◀────────┘                  │
│                              status = clocked_out                    │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    │  whereDoesntHave('payrollItem')
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      PAYROLL (Run Generation)                        │
│                                                                     │
│  getEligibleUsers() ──▶ User (hourly_rate IS NOT NULL)               │
│                           │                                          │
│  getUnpaidAttendance() ──▶ Attendance (clocked_out, unpaid)          │
│                           │                                          │
│  createDraft() ──────────▶ PayrollRun (status: draft)                │
│                           │  + PayrollRunItem × N                    │
│                           │    (pay_amount = hours × rate)           │
│                           │                                          │
│  postRun() ──────────────▶ PayrollRun (status: posted)               │
│                               │                                      │
└───────────────────────────────┼──────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│                     EXPENSES (Accounting)                            │
│                                                                     │
│  Transaction:                                                        │
│    name = "Payroll: {user} — {period}"                               │
│    value = gross_pay                                                 │
│    type = "ns.direct-transaction"                                    │
│    account → "Salaries And Wages" (expense category)                 │
│                                                                     │
│  TransactionHistory:                                                 │
│    operation = "debit"                                               │
│    value = gross_pay                                                 │
│    status = "active"                                                 │
│                                                                     │
│  Reports:                                                            │
│    → getPayrollSummary() — filtered aggregation                      │
│    → getAccountSummaryReport() — per-account totals                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Key Design Decisions

1. **Double-payment prevention**: The `whereDoesntHave('payrollItem')` scope on Attendance ensures each clock-in/out pair is paid exactly once. The `PayrollRunItem.attendance_id` foreign key is the lock.

2. **Transaction integrity**: Payroll uses database transactions (`DB::beginTransaction` / `DB::commit` / `DB::rollBack`) at two levels — draft creation (PayrollRun + Items) and posting (Transaction + History). If anything fails, everything rolls back.

3. **Hard reversal**: Voiding a run deletes accounting entries rather than creating offsetting entries. This is simpler but means no audit trail of the voided entry itself.

4. **Expense account lookup**: The "Salaries And Wages" account is looked up by name at post time — it must exist or posting fails. This is configured via the standard TransactionAccount CRUD.

5. **Payroll is just one transaction source**: The `TransactionService` handles sales, COGS, procurement, and recurring expenses through the same `Transaction`/`TransactionHistory` model pair. Payroll creates `TYPE_DIRECT` transactions.

---

## Knowledge Graph Communities

From the DrCare graph, these communities are involved:

| Community | Relevance |
|-----------|-----------|
| **Inventory & Products** (Community 5) | Product costs flow into COGS |
| **Order Lifecycle Events** (Community 3) | Sales trigger accounting rules |
| **CRUD Tables & Transactions** (Community 4) | Transaction CRUD + history management |
| **Users & Customer Groups** (Community 9) | Employee records with hourly_rate |
| **Scheduled Jobs & Reports** (Community 7) | Recurring transactions, report generation |

### God Nodes (Most Connected)

| Node | Degree | Role |
|------|--------|------|
| `ns()` | 420 | Global helper — used everywhere |
| `TransactionService` | 43 | Central accounting hub |
| `TransactionHistory` | 35 | Ledger entries |
| `TransactionAccount` | 32 | Chart of accounts |
| `PayrollService` | 13 | Payroll logic |
| `Transaction` | 11 | Transaction records |
| `Attendance` | 9 | Time tracking |

---

*Generated from `graphify-out/graph.json` — the DrCare knowledge graph.*
