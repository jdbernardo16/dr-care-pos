# Hold Order Stock Bug — Investigation & Fix

**Date:** 2026-10-14
**Reported by:** Ciara, Jane_23, Bianca Clarice (cashiers)
**Investigated by:** Developer review of orders, stock histories, and source code — reproduced with automated tests

---

## TL;DR — The claims are TRUE. It is a system bug.

When a cashier opens an **held order**, changes something in the cart (removes an item, re-adds an item, or changes the quantity), and then charges it, the system corrupts the stock of the affected items:

1. **Items removed from the cart get "credited back" to stock** even though a hold never deducted them — this creates the **`adjustment return`** entries the cashiers see, and phantom stock (e.g. BINGO orange `2 → 6`).
2. **Items added to the cart during the charge are never deducted** from stock — the sale goes through (cash payment recorded) but inventory stays high.
3. **Reduced quantities are deducted using the OLD quantity**, not the final one.

The result is exactly what the cashiers described: "nadodoble ang dagdag sa inventory" — the stock goes UP instead of down when a held order is charged.

---

## Evidence (from the live data)

### Ciara — BINGO orange "2 nalang, naging 6" (Oct 13)

Order `261012-157` (order #8498), charged Oct 13 06:09:53:

| Item | Return-adjustment | Stock before | Stock after |
|---|---|---|---|
| BINGO orange | +2 | 4 | **6** |
| PROTEC-ZINC | +20 | 156 | 176 |
| INFACARE 5mg | +10 | 256 | 266 |
| VITAPLEX-B | +10 | 231 | 241 |
| MULTIGO | +30 | 175 | 205 |
| EYEBERRY | +5 | 30 | 35 |

The full day for BINGO orange:

1. Oct 13 03:05 — sold 2 in order #8426 (`4 → 2`) — *"2 nalang sa POS"*
2. Oct 13 05:50 — order #8426 voided → +2 returned (`2 → 4`)
3. Oct 13 06:09 — the **held order #8498 charged** → phantom +2 (`4 → 6`) — *"naging 6"*

Order #8498 has **no `sold` entries at all** — the sale was charged (gcash ₱446 recorded) but nothing was deducted from inventory.

### Jane_23 — PROGLINMET 50/1g (Sep 2, same bug, older data)

Order #3574, charged Sep 2 03:35:28 — items removed from the held cart were credited back:

- PROGLINMET +20 (`20 → 40`), PIDCLIN +100, ROSUSAPH +30, NEUROBION +20
- The same items were then sold again in order #3589 → net effect: the +20 phantom cancelled a real sale, so **POS shows 20 pcs while the actual stock is 0**.

### Other confirmed occurrences

- SAPHXOSTAT 80MG +20 (Sep 12, order #4698, ciara)
- Probacil +12 (Aug 28, order #3033, RenzoOng)
- CARDOZ 25MG +1 (Aug 21, order #2331, jane_23)

---

## Root causes (technical)

All inside `app/Services/OrdersService.php`:

1. **`__deleteUntrackedProducts()`** — when products missing from the submitted cart are deleted, the code returned their quantity to stock using a check on the *new* payment status (`$order->payment_status !== 'hold'`). During a hold charge the status has already been set to `paid`, so products that were **never deducted** (a hold does not touch stock) were credited back → **`return-adjustment` phantom entries**.

2. **Payment-status event timing** — the `sold` stock transactions are recorded by `OrderAfterPaymentStatusChangedEventListener` while the order is being saved, **before** the new/edited order-product rows are written to the database. Therefore:
   - products **added** while charging are never deducted (`sold` missing — order #8498 had none at all);
   - products with **changed quantities** are deducted using the previous quantity;
   - voiding such an order then returns stock that was never deducted either.

---

## The fix

- `__deleteUntrackedProducts()` now returns stock **only if the product was actually deducted** (verified via an existing `sold` history) — hold orders and other never-deducted items are never credited back.
- Added `__reconcileOrderProductHistory()` which runs after the order is saved and guarantees that every product of a paid/partially paid order has an exact `sold` transaction:
  - missing deduction → recorded now;
  - quantity changed during the charge → only the difference is adjusted and the history row is corrected.
  - already-deducted products are skipped so existing adjustments are never applied twice (partially-paid edits keep working correctly).

### Automated tests

`tests/Feature/HoldOrderRemovedProductTest.php` (added to the Parallel-2 suite):

| Test | Before fix | After fix |
|---|---|---|
| Removing a product before charging keeps stock | ✗ +5 phantom | ✔ |
| Product added while charging is deducted | ✗ never deducted | ✔ |
| Quantity reduced before charging deducts the final quantity | ✗ deducted old quantity | ✔ |
| Editing a partially paid order doesn't double-adjust | ✗ double return | ✔ |

Regression suites all pass: `CreateOrderTest`, `OrderHoldTest`, `OrderIdempotencyTest`, `OrderRefundTest`, `OrderWithInstalment`, `PartiallyPaidOrderWithAdjustmentTest`, `CombiningProductsTest`, `CashRegisterActionsTest`.

---

## The price claim (466 → 388) — needs one more detail

No order with a ₱466 → ₱388 change was found in the data, so it could not be confirmed yet.

Suspected mechanism: when a held order is reopened, the POS **recomputes each item's price from the current catalog price** (it does not use the price saved on the held order). If a product was repriced between the hold and the re-open, the cart total changes.

To confirm: **capture the order code** next time the price changes on reopening (or a screenshot of both totals). That will tell us whether it was a repriced item or a cart edit.

---

## Notes for the store

1. **Physical counts for the affected items** are needed — the phantom stock added since August is still reflected in the system. The full list of phantom `return-adjustment` entries can be pulled from the stock ledger (Stock Adjustment / Stock Flow page) or by querying `nexopos_products_histories` where `operation_type = 'return-adjustment'`.
2. The fix prevents new phantom stock from being created; it does not rewrite historical entries (that's what the physical count + stock adjustment is for).
3. **Local environment notice:** the `snapshot:load` attempts on Oct 14 (13:43) dropped all local tables and then failed (the fresh MariaDB dump starts with a sandbox-mode marker that `snapshot:load` can't execute). The dump `2026-10-14_13-38-19.sql` was restored manually with the `mysql` CLI — the local database now contains today's data again. The snapshot `backup-2026-10-13-215759.sql` remains available as a backup.
