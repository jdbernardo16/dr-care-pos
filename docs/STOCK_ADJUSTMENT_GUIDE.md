# Stock Adjustment — How to Use Correctly

**Updated:** Aug 31, 2026 — System fix applied  
**For:** Store Owner, Cashiers, Stock Staff  
**Where:** Dashboard → Products → Stock Adjustment

> **One-line rule:** Add ONE product → Set quantity → Choose operation → Check preview → Press Proceed. Repeat for next product.

---

## 1. Before You Start

- Count the product **physically** first, especially for medicines.
- Decide the operation:
  - **Add** — you received new stock (e.g., delivery +50). Stock goes `73 → 123`.
  - **Delete / Defective / Lost** — you remove stock (e.g., expired -5). Stock goes `73 → 68`.
  - **Set** — you overwrite stock to an exact count (e.g., counted 73 on shelf). **Dangerous if wrong.** Use only after physical count. Shows `73 → 73` or `73 → 1` (wipe).

---

## 2. Step-by-Step

1. Go to **Dashboard → Products → Stock Adjustment**.
2. In the **Search** box, type the product name (e.g., `SCHEEPRIN`). Click the suggestion to add it to the table.
3. You will see the product row in **orange**:
   - `Stock: 73 → — (set quantity & operation)`
   - `Operation: Tap to choose` (orange)
   - `Quantity: Tap to set` (orange)
   - Top banner: `Please set quantity for all rows or remove them. 1 incomplete`
   - **Proceed is disabled** — you cannot save yet. This is intentional.
4. Tap **Tap to set** → numpad appears (starts at `0`). Enter the quantity (e.g., `50`) → tap the **share/arrow** icon to confirm.
5. Tap **Tap to choose** → select **Add**, **Set**, **Delete**, **Defective**, or **Lost**.
6. Check the **Stock Preview** column and the text under the product name:
   - `Stock: 73 → 123 (+50)` for Add
   - `Stock: 73 → 68 (-5)` for Delete
   - `Stock: 73 → 50 (23 will be removed)` for Set that reduces — in **red**
7. When the row is complete, orange disappears and footer shows `All rows are ready to proceed.` in green.
8. Press **Proceed** → read the confirmation popup carefully → press **Yes** to save.
9. Table clears. Done. Add the next product and repeat.

---

## 3. What the Colors and Messages Mean

| What you see | Meaning | Action |
|---|---|---|
| Orange row, `Tap to set` / `Tap to choose`, `73 → —`, banner `1 incomplete`, Proceed disabled | Row not finished. System blocks save to prevent the old “wiped to 1” bug. | Set quantity and operation, or press **Remove** to delete the row. |
| `Stock: 73 → 78 (+5)` in normal color, `0 → 1` in normal | Preview of what will happen after saving. | Verify the `→ After` number matches your physical count. |
| `Stock: 73 → 1 (72 will be removed)` in **red**, preview `73 → 1` in red background | You are using **Set** to overwrite and it will **remove** stock. | Double-check. If you meant to **Add**, cancel and change operation to **Add**. |
| Footer `All rows are ready to proceed.` + Proceed enabled (blue) | All rows have quantity and operation. | Safe to press Proceed. |
| Popup `You are about to set: CHOCO KNOT 0 → 1. Would you like to confirm?` | Normal confirmation for any Set. | Check `Before → After` is correct, then Yes. |
| Popup `Warning: You are about to overwrite stock` — `You are about to set: OISHI 1000 → 1. Are you sure?` | You are about to **wipe** stock. This is the bug that lost 523 pcs before. | **Stop.** Cancel (No). Verify count, change to **Add** if you only wanted to add. |

---

## 4. Golden Rule (Still Recommended)

> **Add ONE, Set it, Save. Then add the next ONE.**

Even though the system now **blocks** incomplete rows, doing one product at a time is clearest and fastest, like scanning at the cash register. Adding 5 products at once is allowed only if you complete **every** row before saving.

**DON’T:** Add 5 products, set only 1, and press Proceed.  
**DO:** Add 1 → set quantity → choose operation → Proceed → next.

---

## 5. Common Mistakes

- **Leaving quantity at `Tap to set`** → Save blocked. Fix: tap and enter number.
- **Leaving operation at `Tap to choose`** → Save blocked. Fix: choose Add/Set/Delete.
- **Using Set when you meant Add** — Example: stock is 73, you got 50 new pcs, you pick Set 50 → result `73 → 50` (lost 23). Correct is **Add 50 → 123**.
- **Setting 0 for Add/Delete** → Error `Please provide a quantity greater than 0`. Fix: enter 1 or more, or use Set 0 if you really want to set to zero after count.
- **Deleting more than available** — e.g., try to Delete 10 when stock is 5 → Error `quantity exceed the available quantity`. Fix: check count.

---

## 6. After Saving — How to Verify

- The table clears and shows `Search and add some products`.
- Check the product’s current stock in Products list or POS — it should match your `→ After` preview.
- If you adjusted medicine, recount once more to confirm.

---

## 7. Daily Check (1 minute)

Until everyone is comfortable, the opening staff should quickly check: **Dashboard → Products → Stock Flow Records** and look for any `Set` that made a large drop (e.g., `136 → 1`). If found, recount and fix that product alone with **Set** to the real count.

---

## 8. Need Help?

- **Wiped to 1 by mistake?** Add the product alone → Operation **Set** → Quantity = **real counted** stock → Reason: `Restore — fix mistake` → Proceed.
- **Unsure about Add vs Set?** Ask: “Did I count everything on the shelf?” → Use **Set**. “Did I just receive new delivery to add on top?” → Use **Add**.
- Contact the admin to re-train using Section 2 steps.

---

*This system now shows you `Before → After` before you save, blocks empty rows, and warns you before any wipe. If you see orange, fix it. If you see red, double-check.*
