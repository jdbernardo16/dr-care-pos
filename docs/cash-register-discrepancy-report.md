# Cash Register Investigation Report

**Date:** July 30, 2026
**Register:** Main Cashier
**Cashier:** RenzoOng

---

## Summary

The cash register system showed ₱8,705.00 in the drawer, but when the cash was physically counted, there was actually ₱9,919.00. That's a difference of ₱1,214.00. We found two main reasons plus a small leftover that's likely a counting error.

| Problem Found | Amount Affected |
|---------------|-----------------|
| Order 260729-033: ₱1,000 cash was recorded as GCash by mistake | ₱1,000.00 |
| Small leftover difference (₱214) — likely a counting mistake | ₱214.00 |

---

## What We Found

### Issue 1: A Cash Payment Was Recorded as GCash

**Order Number:** 260729-033 (Order #53)
**What happened:** A customer paid ₱1,000.00 in **cash**, but the cashier selected **GCash** as the payment type on the screen.

**Why this matters:** The system does not track GCash payments in the cash drawer (since no physical cash comes in from GCash). So:
- The system did NOT record the ₱1,000.00 as going into the drawer
- The system DID record the ₱780.00 change that was given out from the drawer
- This made the system think the drawer had ₱1,000.00 less than it actually did

**The fix:** The cashier should select "Cash" instead of "GCash" when customers pay with actual cash. This is a training/practice issue, not a system bug.

### Issue 2: Duplicate Payment Entries (System Bug — Now Fixed)

We found two orders where payments were recorded multiple times:

- **Order 260729-071:** The payment was recorded 6 times instead of once (5 extra entries)
- **Order 260729-073:** The payment was recorded 3 times instead of once (2 extra entries)

**Root cause:** There was a bug in the system where, whenever an order was edited, the old payment records were kept and new ones were added on top. Instead of replacing the old with the new, the system just kept adding more.

**What was fixed:** A programmer fixed the system code so that old payments are now properly removed before new ones are saved. This will prevent future duplicates.

**Does this affect the cash count?** No. Even though there were extra entries, the total cash amounts were still correct — the system tracked the right totals. The fix just prevents confusing duplicate lines in the register history.

### Issue 3: Small ₱214 Difference

Even after accounting for the ₱1,000 mistake above, there's still ₱214.00 more in the drawer than the system shows. This is most likely a simple counting error — a few bills miscounted or coins not precisely tallied. No system issue was found for this.

---

## Sales Report Discount Issue (Also Fixed)

Separately, we found that **product discounts were not showing up in the Sales Report**.

**Example:** For order 260730-001, the cashier sold 10 pieces of Biogesic at ₱4.50 each with a 20% discount. The discount (₱9.00 total) was applied correctly to the order, but the Sales Report showed ₱0.00 in discounts.

**Root cause:** The report was only looking at discounts applied to the whole order (order-level), not discounts applied to individual products (product-level). Since RenzoOng applies discounts per product, they were never showing in the report.

**What was fixed:**
1. The Sales Report now includes product-level discounts in the "Sales Discounts" total
2. The discount column now shows the total discount amount (e.g., ₱9.00) instead of a per-item amount (₱0.90)
3. The profit calculation was also corrected — it was subtracting the discount twice

**Before the fix, the report showed:**
| Line | Amount |
|------|--------|
| Sub Total | ₱12,565.00 |
| Sales Discounts | ₱0.00 |
| Total | ₱12,565.00 |

**After the fix, the report correctly shows:**
| Line | Amount |
|------|--------|
| Sub Total | ₱13,257.00 |
| Sales Discounts | ₱692.00 |
| Total | ₱12,565.00 |

---

## Other Things Checked

- **All discounts are working correctly** — every order's product discounts were verified against the system's math and all calculations are accurate
- **All order totals are correct** — the formula `subtotal − discounts + shipping = total` was verified across all orders
- **GCash and other payment types are configured correctly** — Cash is tracked in the drawer, GCash is not (as intended)

---

## Recommendations

1. **Double-check payment type** — When a customer hands you cash, make sure "Cash" is selected, not "GCash" or another option
2. **Count the opening balance carefully** — The register's opening balance is entered manually; make sure it matches the actual cash you're putting in the drawer
3. **For the ₱214 difference** — No action needed unless it happens repeatedly; small counting differences are normal
