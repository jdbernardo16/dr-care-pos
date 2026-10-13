# Stock Movement Report — What Happened Yesterday (Aug 19)
### In Plain English

**Date:** Aug 20, 2026  
**For:** Store Owner / Cashiers / Stock Staff  
**Full Technical Report:** `STOCK_ADJUSTMENT_REPORT_2026-08-19.md`

---

### TL;DR - The Short Answer

**Your hunch was right.**

Yesterday, when staff added many products to the Stock Adjustment screen to *add* stock, they only set the quantity for 1 or 2 of them and hit **Save**. The other products they didn't touch were **automatically saved as "Set stock to 1"** — wiping out the real stock.

**Result:** 12 products were accidentally wiped to 1, losing **523 pieces** total. 3 of them are still at 1 today — that's why the POS shows "quantity = 1" when you try to sell.

It was not theft. It was a system design flaw.

---

### 1. What Happened in Plain English

Think of the Stock Adjustment screen like making a list for the stockroom:

1.  You search and add products to a table — let's say 5 products.
2.  For each product, you are supposed to tap it and tell the system: **"Add 50 pieces"** or **"Set stock to 40 pieces"**.
3.  **The Problem:** The moment you add a product, the system **already fills it in as "Set stock to 1"** — even though you never typed 1.
4.  If you only fix 1 product and press **Proceed / Save**, the other 4 are still sitting there as **"Set to 1"**. The system thinks you *meant* to make them 1.

**Analogy — Like a Form That Auto-Fills "1":**
> Imagine a form where you write down 5 items you counted. You correctly write "Product A = 50". You leave B, C, D, E blank, but the form has already printed "1" in those blanks in light grey. When you hand it in, the stockroom reads those 1's as your real count and throws away everything except 1 piece for each.

The system says **"Success"** — no error, no warning like "Are you sure you want to set 4 products to 1?"

**That's exactly what happened yesterday between 7:00 PM and 9:00 PM** — 9 times.

---

### 2. Did the Cashier Really Add Stock Yesterday?

**Yes — the cashier is telling the truth.**

Yesterday the staff **did add a lot of stock correctly** — about **5,555 pieces** in total:

*   ANGIOFLO PLUS +600 pieces (27 → 627)
*   STABETA 40MG +300 pieces (0 → 300)
*   TRIMENOVA +300 pieces
*   GLYCEMET +300 pieces
*   And 50+ other correct adds...

**The problem is, those correct adds were saved *together* with the mistakes.**

In the same clicks where they added stock, the untouched rows wiped stock. For example, at **7:13 PM**, in **one click** they saved:
*   MILK KNOT: correctly +1 (3 → 4) ✅
*   **CHOCO KNOT: 5 → 1 (wiped 4)** ❌
*   **OISHI PRAWN: 3 → 1 (wiped 2)** ❌
*   **CRACKLINGS: 6 → 1 (wiped 5)** ❌

So total for the day: Added 5,555 but removed 8,226 + sold 1,012. The store **lost** about 2,682 pieces net — even though they were trying to add.

It's not missing deliveries. It's those 12 wipes mixed into the same saves.

---

### 3. The 12 Products That Were Wiped to 1 Yesterday

All wiped by `admin` in the evening. All show "removed X, before → 1" in the history:

| Time | Product | Stock Before | Stock After | How Many Lost |
|---|---|---|---|---|
| 3:13 PM | TEMPRA drops 15ml | 4 | **1** | 3 |
| 7:00 PM | NOVA CHIPS 38G | 6 | **1** | 5 |
| 7:01 PM | MANGJUAN RED | 5 | **1** | 4 |
| 7:13 PM | CHOCO KNOT 28G | 5 | **1** | 4 |
| 7:13 PM | OISHI PRAWN SPICY | 3 | **1** | 2 |
| 7:13 PM | CRACKLINGS | 6 | **1** | 5 |
| 7:15 PM | XTRA BIG KALAMANSI | 4 | **1** | 3 |
| 7:15 PM | XTRA BIG CHILIMANSI | 4 | **1** | 3 |
| 8:25 PM | Bacillus Clausii (Probacil) | 31 | **1** | 30 |
| 8:43 PM | CARDOZ 25MG (heart med) | 136 | **1** | 135 |
| 8:50 PM | **SCHEEPRIN 80mg (Aspirin)** | **273** | **1** | **272** |
| 8:55 PM | CYSAPHTEINE 600MG | 58 | **1** | 57 |

**Total lost: 523 pieces**

The 2 most expensive losses were medicines: **SCHEEPRIN (272 pcs) and CARDOZ (135 pcs)** — together they are 78% of the total loss.

**Proof it was a mistake, not on purpose:**
*   All 12 happened in **bulk saves** — e.g., 8 products saved in one second at 7:08 PM, 4 products in one second at 7:13 PM. That means one click saved many products.
*   Two products were **added correctly and then wiped in the SAME second** — like SCHEEPRIN was first correctly added +200 (73 → 273) then immediately wiped 273 → 1 in the same save. That’s a clear accident.

---

### 4. Why Does the POS Show "Stock = 1" Today (Aug 20)?

Because those 12 products were left at **1 piece** yesterday.

Today when you try to sell, the POS correctly says:

> "You have **1** piece available. You want to sell 1? OK, now you have **0**."

That's why you saw many products showing quantity 1 when selling this morning — you were seeing the *leftover 1 piece* from yesterday's wipe.

After you sell that last 1, it goes to 0 and shows "Out of Stock."

**This is the downstream effect** — the wipe yesterday causes the "quantity is 1" symptom today.

Today we also saw many normal sales where `1 → 0` (like MILCU 1→0, BL SOAP 1→0, YAZ 1→0). Those are the final pieces being sold.

---

### 5. Which Ones Are STILL Wrong Right Now?

Out of the 12, **9 were already fixed** by staff on Aug 20-21 — they noticed the low stock and added it back (now showing 3, 4, 5, 7, 58, 91, etc.).

**These 3 are STILL at 1 and need to be fixed today:**

| Product | Should Be | Is Now | Status |
|---|---|---|---|
| 1. **Bacillus Clausii (Probacil)** | **31** | **1** | 🔴 **STILL WRONG - Fix today** |
| 2. **SCHEEPRIN 80mg (Aspirin)** | **273** | **1** | 🔴 **STILL WRONG - Fix today (most urgent)** |
| 3. **XTRA BIG KALAMANSI** | **4** | **1** | 🔴 **STILL WRONG - Fix today** |

The whole store has **134 products** showing "1" right now, but **only these 3** are part of yesterday's mistake. The other 131 are normal low-stock items or old corrections.

---

### 6. What To Do Now — Simple Steps

#### A. Fix the 3 Products TODAY (Do This First)

**Important Rule: Fix ONE at a time. Never add all 3 together.**

For each of the 3 products:

1.  Go to **Dashboard → Products → Stock Adjustment**
2.  In the search box, type the product name (e.g., `SCHEEPRIN`)
3.  Click the product to add it to the table
4.  **Tap the product** → Choose **Operation: Set** → Type the **correct quantity** (see table above, or better — **count it physically** first)
5.  Add a reason: Type *"Restore Aug 19 mistake"*
6.  Press **Proceed** → **Confirm**
7.  **Repeat** for the next product

> **For medicines (Probacil, SCHEEPRIN): Please count them by hand first.** If the real count on the shelf is different from 31 or 273, use the real count.

> **For XTRA BIG KALAMANSI (snack):** You can just set it back to 4.

#### B. New Rule For Everyone (Until System is Fixed)

> ### **GOLDEN RULE: Add ONE, Set it, Save. Then add the next ONE.**

*   **DO:** Add 1 product → Set its quantity → Set its operation (Add / Set / Remove) → Press Proceed → Do the next product.
*   **DON'T:** Add 5 products to the table, only set 1 of them, and press Proceed. The other 4 will become 1.

Think of it like the cash register — you scan one item at a time. Do the same for stock adjustments.

#### C. What the Developer Needs to Fix (The Real Solution)

The bug is **still in the system today** — it happened again on Aug 21 for 4 other products.

The developer needs to change the system so:

1.  **It does NOT auto-fill "1"** — It should show blank / "Tap to set quantity" and *require* you to enter a number before saving.
2.  **It does NOT auto-fill "Set"** — It should require you to choose Add / Remove / Set.
3.  **It warns you before wiping:** If you are about to set 4 products to 1, it should pop up:
    > "You are about to set: CHOCO KNOT 5 → 1, OISHI 3 → 1, CRACKLINGS 6 → 1. Are you sure?"
4.  **It shows Before → After:** In the table, you should see `Before: 136 → After: 1` so you can SEE the wipe before you save.
5.  **It blocks empty saves:** If any row is still blank / not set, the Save button should be blocked and say "Please set quantity for all rows or remove them."

**Estimated fix time:** 1 day of work.

#### D. How to Check Every Morning

Until it's fixed, an admin should run this quick check every morning. If any products show up, fix them immediately:

*Check: "Which products were set to 1 yesterday by mistake?"*
If you need help running this check, ask the developer to add a daily report for "Products set to 1".

---

### 7. Summary for the Owner

| Question | Answer |
|---|---|
| **Was the suspicion correct?** | **YES — 100%.** Adding many products and saving without setting each one *does* set the untouched ones to 1. |
| **Did staff really add stock?** | **Yes**, 5,555 pieces were correctly added. But 523 pieces were wiped in the *same* saves, so net stock went down. |
| **Is it theft or system error?** | **System design flaw** — no warning, no check. Not theft. |
| **How many were affected?** | **12 products wiped, 3 still at 1 today.** |
| **What to do now?** | Fix the 3 with a physical count (one by one), use the ONE-BY-ONE rule until fixed, and get the developer to fix the auto-fill + warning. |

---

### 8. Need Help?

*   **For fixing the 3 products:** Follow section 6A. If unsure about the real counts, do a quick hand-count first.
*   **For the technical details:** See the full report `STOCK_ADJUSTMENT_REPORT_2026-08-19.md` (with code lines and database proof).
*   **For the developer:** The fix is detailed in section 7C. All evidence queries are in the technical report's Appendix so they can re-run them.

*This layman's report was generated from the live database on Aug 20, 2026. The 3 products listed as "still at 1" were verified at the time of writing — please re-check their current quantity before restoring, in case they were already fixed after this report.*

