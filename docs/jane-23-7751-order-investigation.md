# Investigation: Jane_23's ₱7,751 Order Not Showing in Order History

**Date:** August 17, 2026
**Reported by:** Jane_23 (cashier)
**Investigators:** Developer account review of POS dashboard, order records, payment records, and register history

---

## Summary

Jane_23 reported that she held items worth ₱7,000+ at the register, then opened and charged the hold — but the sale did not appear in today's order history.

**The transaction was found and confirmed.** The money did go through. It is simply recorded under the date the order was originally held (yesterday), not the date it was charged (today), and it is attributed to a different cashier than the one who actually processed the payment.

---

## What Happened (Timeline)

1. **August 16, 17:44** — An order for **17 items totaling ₱7,751** (order `260816-013`, "Marken") was created at the register and held. The order was recorded under **RenzoOng**'s account.
2. **August 17, 21:50** — The hold order was opened and charged **₱7,751 in cash**.
3. **August 17, 21:50:46** — Payment was recorded and the register cash balance increased from ₱6,622.25 to ₱14,373.20 (+₱7,751).

---

## Why the Order Was Not Visible in Today's History

- Order history is organized by the **order's original creation date**.
- This order was **created (held) on August 16**, so it appears under August 16's history — not today's.
- The **payment** happened today (August 17), but the order record itself keeps its original date.
- Result: the sale is "invisible" when checking today's transactions, even though the payment was received today.

---

## Why the Charge Is Attributed to RenzoOng, Not Jane

When a held order is opened and charged later, the system records the cashier who **originally created the hold** — not the cashier who actually performed the charge.

- The payment record and the register history both copy the author from the order's original creator.
- Since RenzoOng created the hold, every later charge on it is stamped with RenzoOng's name.
- **Jane may have legitimately charged this sale** — the system simply does not record who actually operated the register at the moment of payment.

---

## Findings

| Question | Answer |
|---|---|
| Did the ₱7,751 charge happen? | **Yes** — confirmed in payment and register records |
| Where is the money? | In the register cash balance (+₱7,751 at 21:50:46) |
| Why is it missing from today's order history? | The order was held yesterday, so it's filed under yesterday's date |
| Why does it show RenzoOng instead of Jane? | The system attributes the charge to the person who created the hold, not the person who charged it |

---

## Recommendations

1. **Verify the cash-out / z-reading** for the register to confirm the ₱7,751 is accounted for in the day's close.
2. **Short-term (manual):** When reconciling, search orders by order code `260816-013` or by the original hold date to find the transaction.
3. **Long-term (system fix):** Record the **actual cashier performing the payment** at charge time, instead of inheriting the hold's original creator. This ensures correct attribution of sales to the right cashier.
4. **Consider showing "charged date" alongside "created date"** in order history so later-charged holds are visible on the day they were paid.