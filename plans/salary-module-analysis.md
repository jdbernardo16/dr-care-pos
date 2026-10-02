# Salary / Payroll Module — Analysis Document

**Project:** NexusPOS (Laravel Point-of-Sale)
**Date:** 2026-05-02
**Status:** Analysis & Planning

---

## Table of Contents

1. [Overview](#1-overview)
2. [Data Model Modifications](#2-data-model-modifications)
3. [Calculation Logic](#3-calculation-logic)
4. [Accounting Export](#4-accounting-export)
5. [Feasibility Assessment](#5-feasibility-assessment)
6. [Potential Risks](#6-potential-risks)

---

## 1. Overview

### Purpose

The Salary/Payroll module computes employee wages from attendance clock-in/clock-out records, applying an admin-configured hourly rate per employee. It produces a payroll run — a point-in-time snapshot of hours worked and pay owed for a given period — and exports the total as an accounting expense transaction that flows into the existing Chart of Accounts and dashboard reporting.

### Data Flow (Attendance → Salary → Accounting)

```
nexopos_attendance          nexopos_users              nexopos_payroll_runs
┌──────────────────┐       ┌──────────────┐           ┌──────────────────────┐
│ clock_in_at      │       │ hourly_rate  │──┐        │ period_start/end     │
│ clock_out_at     │──┐    │ name         │  │        │ total_hours          │
│ total_hours      │  │    └──────────────┘  │        │ hourly_rate (snapshot)│
│ status           │  │                      │        │ gross_pay            │
│ user_id          │──┤    ┌────────────────┐│        │ status (draft/posted)│
└──────────────────┘  ├───▶│ Payroll Engine │┘        │ transaction_id (FK)  │
                       │    │ 1. Query att.  │         └──────────┬───────────┘
                       │    │ 2. × rate      │                    │
nexopos_payroll_run_items   │ 3. Sum gross   │                    ▼
┌──────────────────────┐    │ 4. Create run  │         nexopos_transactions
│ payroll_run_id (FK)  │◀───│ 5. Export acct │         ┌──────────────────┐
│ attendance_id (FK)   │    └────────────────┘         │ type = direct    │
│ date                 │                               │ value = gross    │
│ hours_worked         │                               │ account_id →     │
│ hourly_rate          │                               │  Salaries/Wages  │
│ pay_amount           │                               └────────┬─────────┘
└──────────────────────┘                                        │
                                                                ▼
                                                     nexopos_transactions_histories
                                                     ┌──────────────────────────┐
                                                     │ operation = debit        │
                                                     │ value = gross pay        │
                                                     │ status = active          │
                                                     └──────────┬───────────────┘
                                                                │
                                                                ▼
                                                     DashboardDay aggregates
                                                     → monthly balance tracking
                                                     → account summary reports
```

### Accounting Integration Path

1. Payroll run is **posted** (status changes from `draft` → `posted`).
2. A `nexopos_transactions` record is created with `type = 'direct'`, linked to the **"Salaries And Wages"** expense account (already exists under Direct Expenses → Operating Expenses in the Chart of Accounts).
3. A corresponding `nexopos_transactions_histories` journal entry is recorded as a **debit** operation.
4. The expense automatically feeds into:
    - `DashboardDay` aggregates (via `increaseDailyExpenses()`)
    - Monthly balance tracking
    - `ReportService::getAccountSummaryReport()` — the existing account summary report

### Key Design Decisions

| Decision                  | Choice                                                                     | Rationale                                                                                                 |
| ------------------------- | -------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| Hourly rate storage       | `nexopos_users.hourly_rate` column                                         | Simplicity; avoids a separate employee_profiles table for a single field                                  |
| Rate snapshot at run time | Copied into `payroll_runs.hourly_rate` and `payroll_run_items.hourly_rate` | Prevents mid-period rate changes from affecting posted runs                                               |
| Pay period model          | Configurable: weekly, bi-weekly (15-day), monthly                          | Flexibility for different employment arrangements                                                         |
| Accounting method         | Single-entry debit to expense account                                      | Credit side (cash/bank) handled separately or manually; avoids complexity of full double-entry automation |

---

## 2. Data Model Modifications

### 2.1 New Column on `nexopos_users`

**Migration:** Add `hourly_rate` to the existing users table.

```sql
ALTER TABLE nexopos_users
ADD COLUMN hourly_rate DECIMAL(10, 2) NULL DEFAULT NULL
COMMENT 'Hourly wage rate for payroll computation. NULL = not eligible for hourly payroll.';
```

| Column        | Type            | Default | Notes                                                                                                                                                                   |
| ------------- | --------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `hourly_rate` | `DECIMAL(10,2)` | `NULL`  | `NULL` means the user is not on hourly payroll (e.g., salaried, admin, or not an employee). Only users with a non-null `hourly_rate` appear in payroll selection lists. |

**Why `nexopos_users` and not a separate `employee_profiles` table?**

- Only one additional field is needed for payroll purposes.
- The existing `UserCrud` form can be extended with a single numeric input field.
- A separate table adds join complexity with no immediate benefit. If future requirements demand more employee-specific fields (department, position, tax ID, bank details), a dedicated profile table can be introduced later via a new migration without breaking the `hourly_rate` column.

### 2.2 New Table: `nexopos_payroll_runs`

This is the **header** table — one row per employee per pay period.

```sql
CREATE TABLE nexopos_payroll_runs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    total_hours     DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    hourly_rate     DECIMAL(10, 2) NOT NULL,
    gross_pay       DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status          VARCHAR(20) NOT NULL DEFAULT 'draft'
                    COMMENT 'draft | posted | void',
    transaction_id  BIGINT UNSIGNED NULL DEFAULT NULL,
    notes           TEXT NULL,
    author_id       BIGINT UNSIGNED NOT NULL,
    uuid            CHAR(36) NULL,
    created_at      TIMESTAMP NULL DEFAULT NULL,
    updated_at      TIMESTAMP NULL DEFAULT NULL,

    -- Constraints
    CONSTRAINT fk_payroll_runs_user
        FOREIGN KEY (user_id) REFERENCES nexopos_users(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_payroll_runs_transaction
        FOREIGN KEY (transaction_id) REFERENCES nexopos_transactions(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_payroll_runs_author
        FOREIGN KEY (author_id) REFERENCES nexopos_users(id)
        ON DELETE RESTRICT,

    -- Unique: one payroll run per user per period
    UNIQUE KEY uq_payroll_user_period (user_id, period_start, period_end),

    -- Indexes
    INDEX idx_payroll_status (status),
    INDEX idx_payroll_period (period_start, period_end),
    INDEX idx_payroll_transaction (transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

| Column                        | Type                        | Purpose                                                                                       |
| ----------------------------- | --------------------------- | --------------------------------------------------------------------------------------------- |
| `user_id`                     | FK → `nexopos_users`        | The employee being paid                                                                       |
| `period_start` / `period_end` | `DATE`                      | Defines the pay period window                                                                 |
| `total_hours`                 | `DECIMAL(10,2)`             | Sum of all hours worked in the period                                                         |
| `hourly_rate`                 | `DECIMAL(10,2)`             | **Snapshot** of the user's rate at time of payroll run creation                               |
| `gross_pay`                   | `DECIMAL(10,2)`             | `total_hours × hourly_rate`                                                                   |
| `status`                      | `VARCHAR(20)`               | `draft` (editable, not yet posted), `posted` (locked, transaction created), `void` (reversed) |
| `transaction_id`              | FK → `nexopos_transactions` | Links to the accounting transaction created on post; `NULL` while in draft                    |
| `notes`                       | `TEXT`                      | Admin notes (e.g., "Includes overtime for holiday week")                                      |
| `author_id`                   | FK → `nexopos_users`        | Who created/ran this payroll                                                                  |

**Unique constraint** `uq_payroll_user_period` prevents duplicate payroll runs for the same employee in the same period.

### 2.3 New Table: `nexopos_payroll_run_items`

This is the **detail/line-item** table — one row per attendance record included in the run.

```sql
CREATE TABLE nexopos_payroll_run_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id  BIGINT UNSIGNED NOT NULL,
    attendance_id   BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    date            DATE NOT NULL,
    clock_in        DATETIME NOT NULL,
    clock_out       DATETIME NULL,
    hours_worked    DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    hourly_rate     DECIMAL(10, 2) NOT NULL,
    pay_amount      DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP NULL DEFAULT NULL,
    updated_at      TIMESTAMP NULL DEFAULT NULL,

    -- Constraints
    CONSTRAINT fk_payroll_items_run
        FOREIGN KEY (payroll_run_id) REFERENCES nexopos_payroll_runs(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_payroll_items_attendance
        FOREIGN KEY (attendance_id) REFERENCES nexopos_attendance(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_payroll_items_user
        FOREIGN KEY (user_id) REFERENCES nexopos_users(id)
        ON DELETE RESTRICT,

    -- Unique: one attendance record can only belong to one payroll run
    UNIQUE KEY uq_payroll_item_attendance (attendance_id),

    -- Indexes
    INDEX idx_payroll_items_run (payroll_run_id),
    INDEX idx_payroll_items_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

| Column           | Type                        | Purpose                                          |
| ---------------- | --------------------------- | ------------------------------------------------ |
| `payroll_run_id` | FK → `nexopos_payroll_runs` | Parent payroll run                               |
| `attendance_id`  | FK → `nexopos_attendance`   | The specific attendance record                   |
| `hours_worked`   | `DECIMAL(10,2)`             | Copied from `attendance.total_hours` at run time |
| `hourly_rate`    | `DECIMAL(10,2)`             | Snapshot of rate (same as parent run)            |
| `pay_amount`     | `DECIMAL(10,2)`             | `hours_worked × hourly_rate` for this line       |

**Unique constraint** `uq_payroll_item_attendance` ensures an attendance record cannot be included in multiple payroll runs — preventing double-payment.

### 2.4 New Index on `nexopos_attendance`

```sql
-- Composite index for payroll period queries
ALTER TABLE nexopos_attendance
ADD INDEX idx_attendance_user_clockin (user_id, clock_in_at);

-- Optional: index for filtering only completed shifts
ALTER TABLE nexopos_attendance
ADD INDEX idx_attendance_status_clockin (status, clock_in_at);
```

The composite index `(user_id, clock_in_at)` is critical for the payroll engine's period-based queries:

```sql
SELECT * FROM nexopos_attendance
WHERE user_id = ?
  AND clock_in_at BETWEEN ? AND ?
  AND status = 'clocked_out';
```

### 2.5 New Permissions

The following permissions should be registered in the system's permission registry:

| Permission Key     | Description                                    | Suggested Roles            |
| ------------------ | ---------------------------------------------- | -------------------------- |
| `payroll.read`     | View payroll runs and payslips                 | admin, store.administrator |
| `payroll.create`   | Create draft payroll runs                      | admin, store.administrator |
| `payroll.update`   | Edit draft payroll runs                        | admin                      |
| `payroll.delete`   | Delete draft/voided payroll runs               | admin                      |
| `payroll.post`     | Post payroll runs (creates accounting entries) | admin                      |
| `payroll.void`     | Void a posted payroll run                      | admin                      |
| `payroll.set-rate` | Set/edit hourly_rate on user records           | admin                      |
| `payroll.reports`  | Access payroll summary reports                 | admin, store.administrator |

**Note:** `payroll.post` and `payroll.set-rate` are intentionally restricted to `admin` only, as they involve financial transactions and sensitive wage data.

### 2.6 User CRUD Extension

The existing [`UserCrud`](app/Crud/UserCrud.php) form must be extended to include the `hourly_rate` field:

- **Field type:** Numeric text input (`type="number"`, step `0.01`, min `0`)
- **Visibility:** Only shown when user has `payroll.set-rate` permission
- **Placement:** In a new "Payroll" section/tab on the user edit form, or grouped with employment-related fields
- **Validation:** `nullable | numeric | min:0 | max:99999.99`

---

## 3. Calculation Logic

### 3.1 Pay Period Concept

The system supports three pay period types, configurable per payroll run:

| Period Type | Duration                                  | Typical Use Case                |
| ----------- | ----------------------------------------- | ------------------------------- |
| `weekly`    | 7 days (Monday–Sunday or Sunday–Saturday) | Hourly staff, part-time workers |
| `bi-weekly` | 15 days (1st–15th, 16th–end of month)     | Common in Philippine payroll    |
| `monthly`   | Full calendar month (1st–last day)        | Salaried employees, managers    |

The period type is selected when initiating a payroll run. The system calculates `period_start` and `period_end` based on the selected type and a reference date (defaults to "previous completed period").

### 3.2 Attendance Query

For a given `user_id`, `period_start`, and `period_end`:

```sql
SELECT id, clock_in_at, clock_out_at, total_hours, status
FROM nexopos_attendance
WHERE user_id = :user_id
  AND clock_in_at >= :period_start
  AND clock_in_at <  :period_end_plus_one
  AND status = 'clocked_out'
  AND total_hours IS NOT NULL
  AND total_hours > 0
ORDER BY clock_in_at ASC;
```

**Filtering rules:**

1. **Only `clocked_out` status** — shifts where the employee has completed clock-out. Incomplete shifts (`clocked_in`, `on_break`) are excluded.
2. **`total_hours > 0`** — guards against zero or negative hour records.
3. **`total_hours IS NOT NULL`** — `total_hours` is auto-calculated on clock-out; a NULL value indicates a data integrity issue.
4. **Date range is inclusive of `period_start` and exclusive of `period_end + 1 day`** — captures all clock-ins that fall within the period, even if clock-out crosses midnight.

### 3.3 Per-Item Calculation

For each qualifying attendance record:

```
pay_amount = attendance.total_hours × hourly_rate
```

Where `hourly_rate` is the **snapshot** taken from `nexopos_users.hourly_rate` at the time the payroll run is created (not live-queried).

**Pseudocode:**

```php
$hourlyRate = $user->hourly_rate; // Snapshot at run creation time

foreach ($attendanceRecords as $attendance) {
    $hoursWorked = (float) $attendance->total_hours;
    $payAmount = round($hoursWorked * $hourlyRate, 2, PHP_ROUND_HALF_DOWN);

    $runItem = PayrollRunItem::create([
        'payroll_run_id' => $payrollRun->id,
        'attendance_id'  => $attendance->id,
        'user_id'        => $user->id,
        'date'           => $attendance->clock_in_at->toDateString(),
        'clock_in'       => $attendance->clock_in_at,
        'clock_out'      => $attendance->clock_out_at,
        'hours_worked'   => $hoursWorked,
        'hourly_rate'    => $hourlyRate,
        'pay_amount'     => $payAmount,
    ]);
}
```

### 3.4 Gross Pay Aggregation

```
gross_pay = SUM(payroll_run_items.pay_amount)
          = SUM(attendance.total_hours) × hourly_rate
```

Both approaches should yield the same result. The system computes `gross_pay` by summing the individual `pay_amount` values from the run items to maintain per-item auditability.

### 3.5 Rounding Strategy

| Rule               | Approach                                                                                                                                                              |
| ------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Per-item rounding  | `round(value, 2, PHP_ROUND_HALF_DOWN)` — rounds 0.005 down to 0.00                                                                                                    |
| Gross pay rounding | Sum of already-rounded per-item values                                                                                                                                |
| Rationale          | Floor-biased rounding (half-down) avoids overpayment by fractions of a centavo. Over thousands of transactions, half-up rounding could accumulate small overpayments. |

### 3.6 Edge Cases

#### 3.6.1 Overtime

The current attendance system does not distinguish regular hours from overtime hours. `total_hours` is a single aggregate. **Initial implementation treats all hours at the base `hourly_rate`.** Overtime support can be added later via:

- An `overtime_multiplier` field on `nexopos_users` (e.g., 1.25 for 25% overtime premium)
- An `overtime_threshold` (hours per day beyond which overtime applies)
- Separate overtime line items in `payroll_run_items`

#### 3.6.2 Missing Clock-Out

If an employee forgets to clock out, `status` remains `clocked_in` and `total_hours` is `NULL`. These records are **excluded** from the payroll run. The admin must either:

- Manually edit the attendance record to set a clock-out time (via existing attendance management)
- Use an **adjustment mechanism** (see Section 3.7)

#### 3.6.3 Negative Hours

`total_hours` is computed as `clock_out_at - clock_in_at`. If `clock_out_at < clock_in_at` (data error), the value could be negative. The query filters `total_hours > 0` to exclude these. The admin should correct the underlying attendance record.

#### 3.6.4 Cross-Midnight Shifts

If an employee clocks in at 10:00 PM on the last day of the period and clocks out at 6:00 AM the next day, the `clock_in_at` falls within the period, so the shift is included. The full `total_hours` (8 hours) is attributed to the period in which the shift **started**. This is standard payroll practice.

#### 3.6.5 Zero-Hour Employees

Users with `hourly_rate = NULL` are excluded from payroll selection. Users with `hourly_rate = 0.00` would produce zero pay — the UI should warn but not block this (edge case for volunteer/unpaid interns tracked for record-keeping).

### 3.7 Adjustment Mechanism

For scenarios where the admin needs to add, remove, or modify hours outside of attendance records:

- **Manual line items:** The payroll run UI should allow adding ad-hoc `payroll_run_items` not linked to an `attendance_id` (make `attendance_id` nullable).
- **Adjustment type:** A `type` column on `payroll_run_items` (`regular` | `adjustment` | `overtime` | `deduction`) would clarify the nature of each line.
- **Negative adjustments:** Deductions (e.g., for late penalties) can be entered as negative `pay_amount` values.

This is a **Phase 2 consideration** — the initial implementation can rely on editing attendance records directly before running payroll.

---

## 4. Accounting Export

### 4.1 Integration with Existing TransactionService

The existing [`TransactionService`](app/Services/TransactionService.php) provides all the primitives needed. No new accounting logic is required.

**Key methods to leverage:**

| Method                                                 | Role in Payroll                                            |
| ------------------------------------------------------ | ---------------------------------------------------------- |
| `TransactionService::create($data)`                    | Creates the `nexopos_transactions` record                  |
| `TransactionService::triggerTransaction($transaction)` | Records the `nexopos_transactions_histories` journal entry |
| `TransactionService::recordTransactionHistory(...)`    | Low-level journal entry creation                           |

### 4.2 Posting Flow

When a payroll run is posted (`status` changes from `draft` → `posted`):

```php
// 1. Create the transaction
$transaction = TransactionService::create([
    'name'        => "Payroll: {$user->name} — " . $periodLabel,
    'value'       => $payrollRun->gross_pay,
    'type'        => 'direct',              // One-time expense, not recurring
    'account_id'  => $salariesAccountId,    // "Salaries And Wages" account
    'scheduled_date' => now()->toDateString(),
    'description' => "Salary payment for period {$payrollRun->period_start} to {$payrollRun->period_end}",
    'author'      => auth()->id(),
]);

// 2. Record the journal entry (debit to expense)
TransactionService::recordTransactionHistory([
    'transaction_id' => $transaction->id,
    'operation'      => 'debit',            // Expense increase = debit
    'value'          => $payrollRun->gross_pay,
    'transaction_account_id' => $salariesAccountId,
    'status'         => 'active',
    'author'         => auth()->id(),
]);

// 3. Link transaction to payroll run
$payrollRun->update([
    'transaction_id' => $transaction->id,
]);
```

### 4.3 Chart of Accounts Mapping

The target account already exists:

| Account Name           | Account Number | Category   | Parent                               |
| ---------------------- | -------------- | ---------- | ------------------------------------ |
| **Salaries And Wages** | (existing)     | `expenses` | Operating Expenses → Direct Expenses |

The `account_id` for this account should be resolved by name or a configurable setting:

```php
$salariesAccount = TransactionsAccount::where('name', 'Salaries And Wages')
    ->where('category_identifier', 'expenses')
    ->first();
```

### 4.4 Double-Entry Considerations

In proper double-entry bookkeeping, every debit has a corresponding credit:

| Side       | Account                      | Operation | Amount    |
| ---------- | ---------------------------- | --------- | --------- |
| **Debit**  | Salaries And Wages (Expense) | Increase  | Gross pay |
| **Credit** | Cash on Hand / Bank (Asset)  | Decrease  | Gross pay |

**The credit side is NOT automated in the initial implementation.** Rationale:

1. The actual cash disbursement may happen on a different date than the payroll run posting.
2. Payment may come from different cash accounts (petty cash, bank transfer, multiple registers).
3. The existing system already supports manual transaction creation — the accountant can create the corresponding credit entry.
4. Automating the credit side would require selecting a source account and confirming actual disbursement, adding UI complexity.

**Future enhancement:** A "Pay Salaries" workflow that creates both debit and credit entries in one action, linked to a cash register or bank account.

### 4.5 Downstream Effects

Once the transaction is created and the journal entry recorded:

1. **DashboardDay aggregates:** The `increaseDailyExpenses()` method (called during transaction creation) adds the payroll amount to the day's expense total.
2. **Monthly balance:** The expense flows into monthly profit/loss calculations.
3. **Account Summary Report:** `ReportService::getAccountSummaryReport()` includes the Salaries And Wages account, now reflecting payroll expenses.
4. **Cash Flow Report:** If a corresponding credit is later recorded, the net cash flow reflects the salary disbursement.

### 4.6 Voiding a Payroll Run

When a posted payroll run is voided:

1. The linked transaction's status is set to `inactive` or a reversing entry is created.
2. The payroll run status changes to `void`.
3. Attendance records are **not** deleted — they remain available for inclusion in a corrected payroll run.
4. A new unique constraint check allows a new payroll run for the same user+period (since the voided one no longer blocks it — requires careful handling of the unique key; consider using a composite unique that includes `status` or soft-deletes).

---

## 5. Feasibility Assessment

### 5.1 Integration Compatibility

| Existing System             | Compatibility         | Notes                                                                            |
| --------------------------- | --------------------- | -------------------------------------------------------------------------------- |
| **Attendance module**       | ✅ Clean integration  | Payroll reads attendance as a data source; no changes to attendance logic needed |
| **User/Employee system**    | ✅ Minimal change     | One new column (`hourly_rate`); no schema refactoring                            |
| **Accounting/Transactions** | ✅ Leverages existing | `TransactionService` handles all journal entries; no new accounting logic        |
| **CRUD framework**          | ✅ Follows patterns   | Can mirror `AttendanceCrud` structure for payroll CRUD                           |
| **Permissions system**      | ✅ Extends naturally  | New `payroll.*` permission keys follow existing convention                       |
| **Module system**           | ✅ Optional           | Can be built as a native feature or as an installable module                     |
| **Dashboard/Reports**       | ✅ Automatic          | Expense flows into existing aggregates without report changes                    |

### 5.2 Scope of Changes

| Change Type                  | Count                                                    | Impact                                      |
| ---------------------------- | -------------------------------------------------------- | ------------------------------------------- |
| New column on existing table | 1 (`nexopos_users.hourly_rate`)                          | Low — nullable, backward-compatible         |
| New tables                   | 2 (`nexopos_payroll_runs`, `nexopos_payroll_run_items`)  | Low — isolated, no existing FK dependencies |
| New indexes                  | 2 on `nexopos_attendance`                                | Low — improves query performance            |
| New permissions              | 8 (`payroll.*`)                                          | Low — additive only                         |
| New service class            | 1 (`PayrollService`)                                     | Medium — core calculation logic             |
| New controller               | 1 (`PayrollController`)                                  | Medium — CRUD + posting workflow            |
| New CRUD class               | 1 (`PayrollCrud`)                                        | Low-Medium — follows existing patterns      |
| New routes                   | 1 route file (`routes/api/payroll.php`)                  | Low — additive                              |
| UI pages                     | 3–4 (payroll list, create run, run detail, payslip view) | Medium — Vue components needed              |
| User CRUD modification       | 1 field addition                                         | Low — single input field                    |

### 5.3 Build Strategy: Native Feature vs. Module

| Approach           | Pros                                                                     | Cons                                                                      |
| ------------------ | ------------------------------------------------------------------------ | ------------------------------------------------------------------------- |
| **Native feature** | Simpler development; no module overhead; direct access to all internals  | Harder to distribute/disable independently; couples payroll to core       |
| **Module**         | Isolated codebase; can be enabled/disabled; follows modular architecture | Module system is untested (empty `modules/` directory); extra boilerplate |

**Recommendation:** Build as a **native feature** initially. The module system exists but has no reference implementations. Once the payroll feature stabilizes, it can be extracted into a module if distribution/isolation becomes a requirement.

### 5.4 Dependencies & Prerequisites

- **No new Composer packages required.** All computation uses basic arithmetic and existing Laravel/MySQL features.
- **No new npm packages required.** UI can be built with existing Vue.js and component library already in the frontend build.
- **PHP 8.x** — already in use.
- **MySQL 5.7+ / MariaDB 10.3+** — already in use; DECIMAL columns and composite indexes are well-supported.

---

## 6. Potential Risks

### 6.1 Hourly Rate Changes Mid-Period

**Risk:** An admin changes a user's `hourly_rate` while a payroll run is in draft, or between the time attendance was recorded and payroll is run.

**Mitigation:**

- The `hourly_rate` is **snapshotted** into `payroll_runs.hourly_rate` and `payroll_run_items.hourly_rate` at the moment the payroll run is created.
- Once a run is posted, the snapshot is immutable.
- If a rate change is needed for a future period, only new runs pick up the updated rate.
- **UI warning:** If `user.hourly_rate` differs from `payroll_run.hourly_rate` when viewing a draft run, show a warning.

### 6.2 Partial Clock-Outs / Missing Records

**Risk:** Employees who forget to clock out have no `total_hours`, so their shifts are excluded from payroll, leading to underpayment disputes.

**Mitigation:**

- **Prevention:** The attendance UI should highlight employees who are still clocked in at period end.
- **Detection:** The payroll run creation screen should list **excluded** attendance records (those with `status != 'clocked_out'`) so the admin can review and correct them before running payroll.
- **Correction:** Admins can edit attendance records via the existing attendance management to set a clock-out time.
- **Phase 2:** An adjustment mechanism (Section 3.7) allows adding manual hours directly in the payroll run.

### 6.3 Concurrent / Duplicate Payroll Runs

**Risk:** Two admins simultaneously create payroll runs for the same employee and period, or an admin accidentally runs payroll twice.

**Mitigation:**

- **Database-level:** The unique constraint `uq_payroll_user_period (user_id, period_start, period_end)` prevents duplicate rows regardless of application logic.
- **Application-level:** Before creating a run, check for existing runs (any status) for the same user+period and block with a clear error message.
- **Posted runs:** Once posted, the run is locked. The UI hides the "Create Run" button for periods that already have a posted run.

### 6.4 Performance on Large Datasets

**Risk:** A business with 50+ employees and daily attendance over several years could see slow payroll queries when scanning large date ranges.

**Mitigation:**

- **Composite index** `idx_attendance_user_clockin (user_id, clock_in_at)` ensures the payroll query hits an index for both the user filter and the date range.
- **Status filter** `status = 'clocked_out'` further narrows the result set.
- **Pagination:** Payroll runs are created per-user, not for all users at once — each run's query is scoped to one `user_id`.
- **Archiving strategy (future):** Old attendance records can be archived to a separate table if performance degrades over years.

**Estimated query performance:**

| Employees | Attendance records/year | Indexed query (est.) |
| --------- | ----------------------- | -------------------- |
| 10        | ~3,650                  | < 10ms               |
| 50        | ~18,250                 | < 50ms               |
| 100       | ~36,500                 | < 100ms              |

### 6.5 Retroactive Attendance Changes

**Risk:** An admin edits or deletes an attendance record that was already included in a posted payroll run, causing discrepancies between the payroll snapshot and the live attendance data.

**Mitigation:**

- **Soft prevention:** The `uq_payroll_item_attendance` unique constraint on `payroll_run_items.attendance_id` prevents an attendance record from being deleted if it's referenced by a payroll run item (foreign key `ON DELETE RESTRICT`).
- **Detection:** A "Payroll Status" column or computed attribute on the attendance record can indicate whether it has been included in a posted payroll run.
- **Audit trail:** The payroll run items preserve the exact `hours_worked` and `pay_amount` at the time of posting, so the payslip remains correct even if the underlying attendance is later modified.
- **Reconciliation report:** A future report could compare posted payroll run items against current attendance data to detect discrepancies.

### 6.6 Authorization & Access Control

**Risk:** Unauthorized users viewing salary data, modifying hourly rates, or posting payroll runs.

**Mitigation:**

- **Granular permissions** (see Section 2.5) — separate permissions for viewing, creating, posting, voiding, and setting rates.
- **Role-based defaults:**
    - `admin`: All payroll permissions
    - `store.administrator`: Read, create drafts, view reports (cannot post or set rates)
    - `store.cashier`: No payroll access
    - `user`: No payroll access
- **Data filtering:** API responses for payroll data should be filtered by permission. Users without `payroll.read` should never see `hourly_rate` or payroll data in any response.
- **Audit log:** All payroll actions (create, post, void) should be logged with `author_id`.

### 6.7 Data Privacy

**Risk:** Salary data is sensitive. If exposed, it could cause workplace friction or violate privacy norms.

**Mitigation:**

- Payroll data is never exposed to non-admin roles.
- The `hourly_rate` field on the user object is stripped from API responses unless the requesting user has `payroll.read` or `payroll.set-rate`.
- Payslip viewing is restricted to the employee themselves (can view their own) and admins (can view all).
- **Future consideration:** Encrypt the `hourly_rate` column at rest if regulatory requirements demand it.

### 6.8 Edge Case: Employee with No Attendance in Period

**Risk:** Creating a payroll run for an employee who has zero attendance records in the period.

**Mitigation:**

- Allow it — the run will have `total_hours = 0.00` and `gross_pay = 0.00`.
- This is valid for employees on leave without pay, or for record-keeping purposes.
- The UI should show a notice: "No attendance records found for this period. Gross pay will be 0.00."

---

## Appendix A: Summary of Database Changes

```sql
-- 1. Add hourly_rate to users
ALTER TABLE nexopos_users
ADD COLUMN hourly_rate DECIMAL(10, 2) NULL DEFAULT NULL;

-- 2. New table: payroll runs (header)
CREATE TABLE nexopos_payroll_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_hours DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    gross_pay DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    transaction_id BIGINT UNSIGNED NULL DEFAULT NULL,
    notes TEXT NULL,
    author_id BIGINT UNSIGNED NOT NULL,
    uuid CHAR(36) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES nexopos_users(id) ON DELETE RESTRICT,
    FOREIGN KEY (transaction_id) REFERENCES nexopos_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (author_id) REFERENCES nexopos_users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_payroll_user_period (user_id, period_start, period_end),
    INDEX idx_payroll_status (status),
    INDEX idx_payroll_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. New table: payroll run items (line items)
CREATE TABLE nexopos_payroll_run_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payroll_run_id BIGINT UNSIGNED NOT NULL,
    attendance_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    clock_in DATETIME NOT NULL,
    clock_out DATETIME NULL,
    hours_worked DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    hourly_rate DECIMAL(10, 2) NOT NULL,
    pay_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (payroll_run_id) REFERENCES nexopos_payroll_runs(id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_id) REFERENCES nexopos_attendance(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES nexopos_users(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_payroll_item_attendance (attendance_id),
    INDEX idx_payroll_items_run (payroll_run_id),
    INDEX idx_payroll_items_user_date (user_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Performance indexes on attendance
ALTER TABLE nexopos_attendance
ADD INDEX idx_attendance_user_clockin (user_id, clock_in_at),
ADD INDEX idx_attendance_status_clockin (status, clock_in_at);
```

## Appendix B: Key Files to Create or Modify

| File                                                          | Action | Purpose                             |
| ------------------------------------------------------------- | ------ | ----------------------------------- |
| `database/migrations/xxxx_add_hourly_rate_to_users.php`       | Create | Add `hourly_rate` column            |
| `database/migrations/xxxx_create_payroll_runs_table.php`      | Create | Payroll runs header table           |
| `database/migrations/xxxx_create_payroll_run_items_table.php` | Create | Payroll run line items table        |
| `database/migrations/xxxx_add_attendance_payroll_indexes.php` | Create | Performance indexes                 |
| `app/Services/PayrollService.php`                             | Create | Core calculation & posting logic    |
| `app/Models/PayrollRun.php`                                   | Create | Eloquent model                      |
| `app/Models/PayrollRunItem.php`                               | Create | Eloquent model                      |
| `app/Http/Controllers/Dashboard/PayrollController.php`        | Create | HTTP controller                     |
| `app/Crud/PayrollCrud.php`                                    | Create | CRUD configuration                  |
| `routes/api/payroll.php`                                      | Create | API routes                          |
| `app/Crud/UserCrud.php`                                       | Modify | Add `hourly_rate` field             |
| `app/Models/User.php`                                         | Modify | Add `hourly_rate` to fillable/casts |
| `app/Models/Attendance.php`                                   | Modify | Add `payrollItem` relationship      |

---

_End of analysis document._
