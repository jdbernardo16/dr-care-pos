# Payroll & Attendance Module — User Manual

> **DoctorCare Clinic Management System**  
> Version: 1.0 | Last updated: 2026-05-03

---

## Table of Contents

1. [Overview](#1-overview)
2. [Roles & Permissions](#2-roles--permissions)
3. [Attendance Module](#3-attendance-module)
   - 3.1 Clock In/Out
   - 3.2 Break In/Out
   - 3.3 Attendance Records
   - 3.4 Data Flow: Attendance → Payroll
4. [Overtime Requests](#4-overtime-requests)
   - 4.1 Filing an Overtime Request (Employee)
   - 4.2 Approving/Rejecting Overtime (Admin)
5. [Holiday Configuration](#5-holiday-configuration)
   - 5.1 Holiday Types & Pay Multipliers
   - 5.2 Adding/Editing Holidays
6. [Payroll Module](#6-payroll-module)
   - 6.1 Setting Hourly Rates
   - 6.2 Pay Period Types
   - 6.3 Creating a Pay Run
   - 6.4 Payroll Run Details & Breakdown
   - 6.5 Posting (Accounting Export)
   - 6.6 Voiding a Pay Run
   - 6.7 Pay Runs List
7. [Pay Computation Rules](#7-pay-computation-rules)
   - 7.1 Regular Pay (8-Hour Cap)
   - 7.2 Overtime Pay
   - 7.3 Holiday Pay
   - 7.4 Night Differential
   - 7.5 Gross Pay Formula
8. [Accounting Integration](#8-accounting-integration)
9. [Troubleshooting](#9-troubleshooting)
10. [Glossary](#10-glossary)

---

## 1. Overview

The Payroll & Attendance module tracks employee working hours, computes salaries based on Philippine labor law, and exports payroll expenses to the accounting system. The workflow is:

```
Clock In → Take Break(s) → Clock Out
         → Overtime Filing (optional)
         → Admin creates Pay Run
         → Post (creates Accounting Entry)
```

### Key Features

- **Live attendance clocking** with break in/out tracking
- **Overtime request & approval** workflow
- **Configurable holidays** with automatic pay multipliers
- **Automated pay computation** with 8-hour cap, OT rates, holiday premiums, and night differential
- **Accounting export** — one-click posting as a debit expense entry

---

## 2. Roles & Permissions

| Role | Attendance | Overtime | Payroll | Holidays |
|---|---|---|---|---|
| **Admin** | View all, edit, delete | File, approve, view all | Create, post, void | Create, edit, delete |
| **Store Admin / Manager** | View all, create, edit | Approve, view all | Create drafts, view | View |
| **Cashier / Employee** | Clock in/out only | File own requests | None | None |

---

## 3. Attendance Module

### 3.1 Clock In/Out

**Navigation:** Sidebar → **Attendance** → **Clock In/Out**

The clock page shows:

- **Live clock** — current time updates every second
- **Status badge** — shows your current state:
  - 🟢 **Clocked In** — you have an active shift
  - 🟡 **On Break** — you are on break
  - ⚪ **Not Clocked In** — no active shift
- **Live time summary** (visible when clocked in):
  - **Hours** — total time since clock-in
  - **Break** — accumulated break time
  - **Net** — working hours (total minus break)
- **Note field** — optional note for clock in/out
- **File Overtime Request** link — quick access to overtime filing

> ⚠️ **Shifts reset at midnight.** Each day is independent. You must clock in each day you work.

#### To Clock In:
1. Click the green **Clock In** button
2. Add an optional note
3. Status changes to **Clocked In** (green badge)

#### To Clock Out:
1. Make sure you're not on break (end break first)
2. Click the red **Clock Out** button
3. The shift is recorded with total hours and net hours

### 3.2 Break In/Out

While clocked in, two buttons appear below the Clock Out button:

#### Start Break:
1. Click the yellow **Break In** button
2. Status changes to **On Break** (yellow badge)
3. The clock automatically tracks break duration

#### End Break:
1. Click the blue **Break Out** button
2. Status returns to **Clocked In**
3. Break time is added to your cumulative break hours for the day

> 💡 If you clock out while on break, the system auto-ends your break first before clocking out.

### 3.3 Attendance Records

**Navigation:** Sidebar → **Attendance** → **Records**

Displays a table of all attendance records with columns:
| Column | Description |
|---|---|
| Employee | Username |
| Clock In | Timestamp when shift started |
| Clock Out | Timestamp when shift ended |
| Break | Total break hours |
| Net Hours | Working hours (total minus break) |
| Total | Total hours (clock in → clock out) |
| Status | Clocked In / Clocked Out / Absent / On Break |
| Recorded By | Admin who created the record |
| Created At | When the record was created |

> 👤 **Non-admin users** see only their own records. Admins see all records.

### 3.4 Data Flow: Attendance → Payroll

Attendance records with **status = clocked_out** and **net_hours > 0** that are **not yet included in any payroll run** are eligible for payroll processing.

---

## 4. Overtime Requests

### 4.1 Filing an Overtime Request (Employee)

**Navigation:** Sidebar → **Payroll** → **File Overtime**  
Or click **File Overtime Request** on the Clock In/Out page

The overtime form:

1. **Date** — pick the day you worked overtime (pre-filled to today)
2. **Start Time** — when the overtime work started (time picker only)
3. **End Time** — when the overtime work ended (time picker only)
4. **Reason** — why overtime is needed (required)
5. Preview shows calculated OT hours automatically
6. Click **Submit Request**

After submitting:
- Status shows **Pending Approval** (yellow)
- The request appears in the **My Recent Requests** table below
- Admin must approve before it's included in payroll

### 4.2 Approving/Rejecting Overtime (Admin)

**Navigation:** Sidebar → **Payroll** → **Overtime Requests**

Shows all overtime requests with filters by status. Pending requests have:

- ✅ **Approve** — instantly grants the overtime
- ❌ **Reject** — prompts for an optional reason

Approved overtime is automatically included in payroll runs for the matching period.

---

## 5. Holiday Configuration

**Navigation:** Sidebar → **Payroll** → **Holidays**

### 5.1 Holiday Types & Pay Multipliers

| Holiday Type | Pay Multiplier | Example |
|---|---|---|
| **Regular Holiday** | ×2.0 (Double Pay) | Christmas Day, New Year's Day, Independence Day |
| **Special Non-Working** | ×1.3 (+30%) | Ninoy Aquino Day, All Saints' Day |

> 💡 **Recurring holidays** (e.g., Christmas) repeat yearly. Toggle "Recurring yearly" to avoid re-entering them each year.

### 5.2 Adding/Editing Holidays

1. Click **Add Holiday**
2. Fill in:
   - **Name** — e.g., "Christmas Day"
   - **Date** — pick the date
   - **Type** — Regular Holiday or Special Non-Working
   - **Multiplier** — automatically set based on type (×2.0 or ×1.3), can be customized
   - **Recurring yearly** — check if it repeats annually
3. Click **Save**

Pre-seeded Philippine holidays for 2026 are included by default.

---

## 6. Payroll Module

### 6.1 Setting Hourly Rates

Before running payroll, each employee must have an hourly rate:

1. Go to **Users** in the sidebar
2. Click **Edit** on the employee
3. Click the **Payroll** tab
4. Enter the **Hourly Rate** (e.g., `55.00`)
5. Save

> 💡 Only users with a non-null hourly rate appear in the payroll employee selection.

### 6.2 Pay Period Types

| Type | Duration | Typical Use |
|---|---|---|
| **Weekly** | Monday – Sunday | Part-time, hourly staff |
| **Bi-Weekly** | 1st–15th, 16th–end of month | Common Philippine payroll |
| **Monthly** | 1st – last day | Salaried employees |

### 6.3 Creating a Pay Run

**Navigation:** Sidebar → **Payroll** → **Create Pay Run**

1. **Select Employee** — pick from the dropdown (only users with hourly rates shown)
2. **Select Period Type** — Weekly / Bi-Weekly / Monthly
3. Click **Compute Period** to auto-fill the date range
4. Adjust dates manually if needed
5. Click **Preview Attendance** — shows all unpaid attendance records for the period

**Preview Table Columns:**

| Column | Description |
|---|---|
| Date | Day of attendance |
| Clock In / Out | Exact timestamps |
| Break Start / End | Break timestamps (if any) |
| Break | Total break hours |
| Net Hrs | Working hours (total − break) |
| Reg Hrs | Regular hours (capped at 8) |
| Reg Pay | 8h × hourly rate |
| OT Hrs | Hours beyond 8 that could be OT |
| Est. OT Pay | OT hours × rate × 1.25 (estimate) |
| Est. Total | Regular Pay + OT Pay |

6. Click **Create Pay Run** to create a **Draft** payroll run

> ⚠️ **Overtime Note:** The preview estimates OT pay at the regular OT rate (×1.25). The actual OT pay in the payroll run uses the correct rate based on holiday/rest day rules, and only counts **approved** overtime requests.

### 6.4 Payroll Run Details & Breakdown

**Navigation:** Sidebar → **Payroll** → **Pay Runs** → Click **View**

Shows a full breakdown:

**Summary Panel:**

| Field | Description |
|---|---|
| Employee | Username |
| Period Type | Weekly / Bi-Weekly / Monthly |
| Period | Start date — End date |
| Hourly Rate | e.g., ₱55.00/hr |
| Total Hours | Sum of net hours |
| Regular Pay | Regular hours × rate |
| Overtime Hours | Approved OT hours |
| Overtime Pay | OT hours × rate × OT multiplier |
| Holiday Premium | Holiday hours × rate × (holiday multiplier − 1) |
| Night Differential | Night hours × rate × 0.10 |
| **Gross Pay** | **Total of all components** |

**Attendance Records Table:**

| Column | Description |
|---|---|
| Date | Day |
| Clock In / Out | Shift timestamps |
| Break | Break hours deducted |
| Net Hrs | Working hours |
| Reg Hrs | Regular hours (≤8) |
| Reg Pay | Regular pay amount |
| OT Hrs | Approved overtime hours |
| OT Pay | Overtime pay amount |
| Holiday | Holiday premium pay (if applicable) |
| Night Diff | Night differential pay (if applicable) |
| Total | Day's total pay |

### 6.5 Posting (Accounting Export)

Only **draft** runs with gross pay > 0 can be posted.

1. From the Pay Runs list, click **Post** (green button)
2. Or from the detail page, click **Post Pay Run**
3. Confirmation: "This will create an accounting entry"
4. On confirm:
   - A `Transaction` record is created as a **Direct Expense**
   - A `TransactionHistory` debit entry is recorded to **Salaries And Wages**
   - The payroll run status changes to **Posted**
   - The expense automatically appears in:
     - Dashboard expense widgets
     - Accounting → Transactions list
     - Accounting → Transaction History
     - Account Summary Reports

### 6.6 Voiding a Pay Run

Only **posted** runs can be voided.

1. Click **Void** (red button)
2. The linked accounting transaction + history is **deleted**
3. Attendance records are **freed** for inclusion in a new payroll run
4. Status changes to **Void**

### 6.7 Pay Runs List

**Navigation:** Sidebar → **Payroll** → **Pay Runs**

Filterable table with columns:
- Employee, Period, Hours, Rate, Gross Pay, **Status** (color-coded badge), Created date
- **Actions**: View / Post (draft) / Void (posted) / Delete (draft)

Status badges:
- ⚪ **Draft** — editable, not yet posted
- 🟢 **Posted** — accounting entry created, locked
- 🔴 **Void** — cancelled, no accounting entry

---

## 7. Pay Computation Rules

### 7.1 Regular Pay (8-Hour Cap)

```
regular_hours = min(net_hours, 8)
regular_pay = regular_hours × hourly_rate
```

Each day's working hours are capped at **8 hours** for regular pay. Any hours beyond 8 require approved overtime to be compensated.

### 7.2 Overtime Pay

Overtime rates follow **Philippine labor law** (DOLE):

| Scenario | Multiplier | Computation |
|---|---|---|
| **Regular day OT** (beyond 8h) | ×1.25 (+25%) | `OT_hours × rate × 1.25` |
| **Rest day / Special holiday OT** | ×1.30 (+30%) | `OT_hours × rate × 1.30` |
| **Regular holiday OT** | ×2.00 (+100%) | `OT_hours × rate × 2.00` |

> 💡 Overtime is only paid for **approved** overtime requests. Hours beyond 8 without an approved OT request are not compensated as OT.

### 7.3 Holiday Pay

| Holiday Type | Pay Treatment |
|---|---|
| **Regular Holiday** (×2.0) | Regular hours are paid at double rate: `regular_pay × 2.0` |
| **Special Non-Working** (×1.3) | Regular hours are paid at ×1.3: `regular_pay × 1.3` |

The system stores the **premium** (the extra amount above base pay) in the `holiday_pay` column:
```
holiday_premium = regular_hours × rate × (holiday_multiplier − 1)
```

### 7.4 Night Differential

Hours worked between **10:00 PM and 6:00 AM** qualify for an additional **10%** of the hourly rate:
```
night_diff_pay = night_diff_hours × rate × 0.10
```

### 7.5 Gross Pay Formula

```
gross_pay = regular_pay + overtime_pay + holiday_pay + night_diff_pay
```

---

## 8. Accounting Integration

When a payroll run is posted:

1. A `Transaction` record is created in `nexopos_transactions` with:
   - Type: `ns.direct-transaction`
   - Account: **Salaries And Wages** (under Direct Expenses → Operating Expenses)
   - Value: `gross_pay`

2. A `TransactionHistory` entry is recorded as a **debit** operation

3. The expense automatically feeds into:
   - **Dashboard Day aggregates** — adds to daily expense totals
   - **Monthly balance tracking**
   - **Account Summary Report** — the Salaries And Wages account reflects payroll expenses

> 💡 The credit side (cash disbursement) is handled manually by the accountant. The system creates only the debit expense entry.

---

## 9. Troubleshooting

### "No attendance records found" when creating pay run
- Check that the employee has clocked-out records in the period
- Check that the attendance hasn't already been included in another pay run
- Verify the employee has an hourly rate set

### Preview shows 0 hours
- Ensure net_hours > 0 for the attendance records
- Old records from before the break system may have `net_hours = 0` — run `UPDATE nexopos_attendance SET net_hours = total_hours - break_hours WHERE net_hours = 0 AND total_hours > 0`

### Overtime not showing in payroll
- The OT request must be **approved** by an admin
- The OT date must fall within the pay period
- OT hours beyond the day's net hours are capped

### 419 error when submitting
- Page session expired — refresh and login again
- The Vue components use `nsHttpClient` which handles CSRF tokens automatically

### Times showing wrong hours
- The system uses `Asia/Manila` timezone. Verify `config('app.timezone')` is set correctly.

---

## 10. Glossary

| Term | Definition |
|---|---|
| **Net Hours** | Total hours minus break hours. This is the working time used for pay computation. |
| **Regular Hours** | Working hours capped at 8 per day (standard Philippine workday). |
| **Overtime (OT)** | Hours worked beyond 8 in a day, must be pre-approved via OT request. |
| **Holiday Premium** | Extra pay on top of regular pay for working on a holiday. |
| **Night Differential** | Additional 10% pay for hours worked between 10pm and 6am. |
| **Draft** | A payroll run that is still editable and not yet posted to accounting. |
| **Posted** | A payroll run that has been finalized and exported as an accounting entry. |
| **Void** | A previously posted run that has been cancelled (accounting entry removed). |
| **Direct Expense** | A one-time expense transaction (as opposed to recurring). |
| **Salaries And Wages** | The Chart of Accounts expense account where payroll is recorded. |
