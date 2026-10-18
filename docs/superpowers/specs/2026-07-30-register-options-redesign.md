# Register Options Modal Redesign

## Summary

Redesign the POS register options modal to show a detailed cash summary and sales summary (inspired by Loyverse POS), while retaining the existing 4 action buttons (Close, Cash In, Cash Out, History).

## Current State

The modal shows only 2 summary fields:
- **Sales** (`register.total_sale_amount` from `getRegisterDetails()`)
- **Balance** (`register.balance` from DB)

4 action buttons in a 2×2 grid.

## Target Layout

### Cash Summary (left column)

| Field | Source |
|-------|--------|
| Starting Cash | `register.opening_balance` |
| Cash Payments | Sum of order payments where payment type `is_cash = true` |
| Cash Refunds | Sum of refunded orders (payment_status = refunded) |
| Paid In | Sum of `register-cash-in` history actions |
| Paid Out | Sum of `register-cash-out` history actions |
| Expected Cash | starting_cash + cash_payments + paid_in - cash_refunds - paid_out |

### Sales Summary (right column)

| Field | Source |
|-------|--------|
| Gross Sales | Sum of order `subtotal` for paid orders |
| Discounts | Sum of order `discount` |
| Refunds | Sum of refunded order totals |
| Net Sales | Sum of order `total` (gross - discounts) |
| Payment Breakdown | Grouped by payment type (Cash, GCash, etc.) |

### Buttons (retained as-is)
- Close
- Cash In
- Cash Out
- History

## Changes

### Backend

1. **New service method** `CashRegistersService::getRegisterSessionSummary(Register $register)`:
   - Validates register is opened
   - Gets last opening history entry
   - Computes cash summary fields using orders, payments, and history
   - Computes sales summary fields using paid orders since opening
   - Returns structured array with `cash_summary` and `sales_summary`

2. **New controller method** `CashRegistersController::getSessionSummary(Register $register)`:
   - Calls service method
   - Returns JSON response

3. **New route** `GET /api/cash-registers/{register}/session-summary`:
   - Middleware: `nexopos.read.registers`

### Frontend

1. **Modify** `ns-pos-cash-registers-options-popup.vue`:
   - Replace `loadRegisterSummary()` to call new `/session-summary` endpoint
   - Replace the two summary divs (Sales / Balance) with the new two-column layout
   - Keep the 4 action buttons and their handlers exactly as-is
