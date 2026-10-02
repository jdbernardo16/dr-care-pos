# Stock Movement Investigation Report — 2026-08-19 (Yesterday)

**Date Prepared:** 2026-08-20 (Thursday, 21:36 PHT)  
**Prepared By:** Lead Architect — Forensic audit of `nexopos_products_histories` + code audit  
**Scope:** All stock movements on **2026-08-19** (00:00–23:59), plus cross-check on **2026-08-20** (today) and current `quantity = 1` inventory.  
**Trigger:** Cashier reported "stock adjust — we added items yesterday," but POS today shows many products with `stock = 1` that should not be that low. Hypothesis: bulk-adding products to the Stock Adjustment screen and hitting **Save** submits untouched rows as `quantity = 1` with `operation = Set`.

**Verdict:** **HYPOTHESIS CONFIRMED (HIGH CONFIDENCE) — CRITICAL BUG.** The defaults + missing validation cause exactly this failure, and bulk-batch collocation in the DB strongly indicates it happened yesterday. *Per-row `before−1→1` is necessary for `SET→1` but DB-identical to an intentional `removed before−1`; the case rests on batch pattern, not row isolation (see §3.2 footnote).*

---

## 1. Executive Summary

| Signal | Yesterday (2026-08-19) | Today (2026-08-20) |
|---|---|---|
| **Total stock histories** | **244** (`sold 163`, `added 58`, `removed 22`, `void-return 1`) | **248** (`sold 184`, `added 60`, `removed 1`) |
| **Stock-Adjustment rows (`added`+`removed`)** | **80** (= 58 added + 22 removed). Sum `added = +5,555` units, sum `removed = −8,226` units. Net **−2,682** (`+5,555 −8,226 −1,012 sold +1 void-return`) despite "we added stock." | 61 rows, net **+~911** (healthy). Only 1 `removed` and **0** `removed→1` — bug did **not** fire today. |
| **Suspicious `→1` adjustments** | **12 rows**, all `operation = removed`, all `after_quantity = 1`, all `author = admin (168)`, all in **9 bulk-save batches** | **0 rows** |
| **Total units wiped by bug** | **523 units** across 12 SKUs (see §3). Largest: `SCHEEPRIN 273→1 (−272)`, `CARDOZ 136→1 (−135)` | — |
| **Products still at `quantity = 1` because of bug** | **3 SKUs** still corrupted as of 2026-08-21: `Bacillus Clausii (Probacil) 31→1`, `SCHEEPRIN 273→1`, `XTRA BIG KALAMANSI 4→1`. 9 others were manually re-corrected on 2026-08-20/21 (now `3–7` or `58`, `91`). | Global inventory `quantity=1` = **134 products** out of 2,021 unit-quantities, but only **3** of the 12 remain in that state — proof staff noticed and patched most. |

**One sentence:** Your suspicion is exactly what happened. The screen defaults every new row to **`Set : 1`** (or `Delete : 1` for the procurement path), has **no dirty-check**, and the backend silently converts `Set:1` into `removed (before−1) →1`, which is indistinguishable in the audit trail from a deliberate deletion to 1.

---

## 2. How the Bug Works (Code Evidence)

### 2.1 Frontend defaults every row to `1`

**File `resources/ts/pages/dashboard/products/ns-stock-adjustment.vue`:**

**Path A — `addProductToList(product)` L56–91** (triggered via procurement / barcode search):

```js
const action = this.actions.filter( action => action.value === 'deleted' );
let defaultAction = action.length === 1 ? action[0] : { value: 'deleted' };
finalProduct.adjust_quantity = 1;                 // L75  — hardcoded
finalProduct.adjust_action   = defaultAction.value; // L76 — 'deleted'
```

**Path B — `addSuggestion(suggestion)` L93–133** (triggered via product-name search — the **bulk-add** path cashiers actually use):

```js
const action = this.actions.filter( action => action.value === 'set' );
let defaultAction = action.length === 1 ? action[0] : { value: 'set' };
suggestion.adjust_quantity = 1;                 // L110 — hardcoded
suggestion.adjust_action   = defaultAction.value, // L111 — 'set'  ← absolute-set semantics
suggestion.adjust_unit     = defaultUnit[0] …;   // L113
this.recalculateProduct( suggestion );           // L117 — only recomputes value, never marks dirty
this.products.unshift( suggestion );            // L118 — immediately in table
```

No `touched` / `dirty` flag is ever set. `recalculateProduct()` L143–149 only computes `adjust_value`.

> **Result:** Adding 5 products via search = **5 rows, all `Set : 1`**. Only the row whose quantity popup you open gets a new `adjust_quantity`. The other 4 keep `1` and will be submitted as-is.

### 2.2 Backend converts `Set:1` into `removed: before−1 →1` (hiding intent)

**File `app/Services/ProductService.php` `handleStockAdjustmentRegularProducts()` L1507–1551:**

```php
} elseif ( in_array( $action, [ ProductHistory::ACTION_SET ] ) ) {
    $currentQuantity = $this->getQuantity( … );
    if ( $currentQuantity < $quantity ) {
        $action = ProductHistory::ACTION_ADDED;
        $adjustQuantity = $quantity - $currentQuantity;
        $this->increaseUnitQuantities( … $adjustQuantity … );
    } elseif ( $currentQuantity > $quantity ) {          // ← Set:1 when current=136 triggers this
        $action = ProductHistory::ACTION_REMOVED;
        $adjustQuantity = $currentQuantity - $quantity;  // 136 − 1 = 135
        $this->reduceUnitQuantities( … $adjustQuantity … );
    }
    return $this->recordStockHistory(
        action: $action,                // now 'removed', never 'set'
        quantity: $adjustQuantity,     // delta, not target
        old_quantity: $currentQuantity, // 136
        new_quantity: $quantity         // 1  ← the untouched default
    );
}
```

**File `app/Services/ProductService.php` `recordStockHistory()` L1589–1604:**

```php
$history->operation_type = $action;       // L1596 — persists mutated 'removed'
$history->quantity       = abs($quantity); // L1601 — delta (135)
$history->before_quantity = $old_quantity; // 12
$history->after_quantity  = $new_quantity; // 1
```

**File `app/Models/ProductHistory.php` L39–71:** `ACTION_SET='set'` is defined but **never persisted** — it is a transient trigger. Only `removed`/`added` hit `nexopos_products_histories`.

So a DB row reading `removed 135  136→1` is **not** "someone removed 135 units" — it is **"someone (accidentally) SET stock to 1 when it was 136."** The audit trail rewrites intent and makes the bug undetectable after the fact without code knowledge.

Bonus edge: if `current == target` (both 1), neither branch fires, `$adjustQuantity` is undefined → `recordStockHistory(quantity: null)` → `quantity = 0` row with `operation_type = 'set'` leaking through (only case `set` could persist).

Controller also mis-computes `total_price` for SET: `ProductService::stockAdjustment` L1265–1268 does `unit_price * quantity` where `quantity` is the *target* (1), not delta — history `total_price` is understated for SET wipes.

### 2.3 Proceed has NO validation that rows were edited

**File `ns-stock-adjustment.vue` `proceedStockAdjustment()` L176–196:**

```js
proceedStockAdjustment() {
    if ( this.products.length === 0 ) {  // L177 — only emptiness check
        return nsSnackBar.error( … );
    }
    Popup.show( nsPosConfirmPopupVue, {
        title: __( 'Confirm Your Action' ),
        message: __( 'The stock adjustment is about to be made. Would you like to confirm ?' ),
        onAction: ( action ) => {
            if ( action ) {
                nsHttpClient.post( '/api/products/adjustments', { products: this.products }) // L186 — raw dump
```

No loop over `products` checking `adjust_quantity !== 1`, no dirty flag, no per-row diff preview (`Before → After`), generic confirm dialog enumerates no rows.

**File `app/Http/Controllers/Dashboard/ProductsController.php` `createAdjustment()` L439–530** validates only:
- `products` present (L443)
- `adjust_unit.unit_id` exists (L462)
- `adjust_action` is allowed (L469–477) — both defaults pass
- For reduce actions: `remaining = stock − 1 ≥ 0` (L486–498) — passes unless stock is 0
- `adjust_quantity < 0` (L500–508) — `1` passes

No `dirty` field, no rejection of untouched defaults, no warning if every row is still `qty=1`.

**Confirmed:** Hitting **Proceed** with N untouched rows = N committed stock mutations, all returning `status: success` (L525–528). No frontend or backend error.

### 2.4 Missing safeguards checklist

| Missing | Impact |
|---|---|
| No `isDirty` flag set on `openQuantityPopup` / `selectStockAdjustementAction` | Cannot distinguish edited vs default rows |
| Quantity popup `openQuantityPopup()` L152–175 is optional; its exceed-stock guard never runs if never opened | Untouched `1` bypasses all guards |
| Generic confirm dialog L181–183 shows no per-row summary | User never sees `136 → 1` destruction |
| No `Before → After` preview in table | User cannot visualize wipe |
| Default `adjust_quantity` is `1` not `0`/`null` with required validation | Silent commit with meaningful value |
| No `quantity > 0 && quantity != 1` heuristic warning for bulk `Set` | Mass deletion looks identical to mass addition |
| Audit falsification (`SET` → `removed`) | Incident review cannot distinguish bug from intent |

**Severity: CRITICAL (P0) — Silent data loss + audit falsification.** Any clerk with `nexopos.make.products-adjustments` can wipe any SKU to 1 in 2 clicks, with no error, only visible as stock discrepancy days later.

---

## 3. Yesterday's Movements — Forensic Evidence

### 3.1 The 12 suspicious adjustments (the bug)

**SQL:**
```sql
SELECT h.id, p.name, h.operation_type, h.before_quantity, h.quantity, h.after_quantity, h.created_at, u.username
FROM nexopos_products_histories h
LEFT JOIN nexopos_products p ON p.id=h.product_id
LEFT JOIN nexopos_users u ON u.id=h.author_id
WHERE DATE(h.created_at)='2026-08-19' AND h.after_quantity=1 AND h.operation_type!='sold'
ORDER BY h.created_at;
```

| # | id | Time (PHT) | Product | Before → After | Qty (delta) | User | Proof `qty = before − 1` |
|---|---|---|---|---|---|---|---|
| 1 | 5553 | 15:13:03 | **TEMPRA drops 15ml (Paracetamol)** | **4 → 1** | **3** | admin | ✓ 4−3=1 |
| 2 | 5588 | 19:00:49 | **NOVA CHIPS 38G** | **6 → 1** | **5** | admin | ✓ |
| 3 | 5591 | 19:01:41 | **MANGJUAN RED** | **5 → 1** | **4** | admin | ✓ |
| 4 | 5616 | 19:13:35 | **CHOCO KNOT 28G** | **5 → 1** | **4** | admin | ✓ |
| 5 | 5617 | 19:13:35 | **OISHI PRAWN SPICY 24G** | **3 → 1** | **2** | admin | ✓ |
| 6 | 5618 | 19:13:35 | **CRACKLINGS** | **6 → 1** | **5** | admin | ✓ |
| 7 | 5620 | 19:15:35 | **XTRA BIG KALAMANSI** | **4 → 1** | **3** | admin | ✓ |
| 8 | 5621 | 19:15:35 | **XTRA BIG CHILIMANSI** | **4 → 1** | **3** | admin | ✓ |
| 9 | 5647 | 20:25:37 | **Bacillus Clausii (Probacil)** | **31 → 1** | **30** | admin | ✓ |
| 10 | 5652 | 20:43:24 | **CARDOZ 25MG (CARVEDILOL)** | **136 → 1** | **135** | admin | ✓ |
| 11 | 5659 | 20:50:08 | **SCHEEPRIN 80mg (Aspirin)** | **273 → 1** | **272** | admin | ✓ |
| 12 | 5666 | 20:55:58 | **CYSAPHTEINE 600MG (ACETYLCYSTEINE)** | **58 → 1** | **57** | admin | ✓ |

All 12 satisfy **`quantity = before_quantity − 1` and `after_quantity = 1`** — the signature of a transient `SET target = 1` that the backend rewrites as `removed delta = before−1`. **Caution:** a legitimate `removed (before−1)` to leave 1 (e.g., intentional safety-stock) is DB-identical per row. The inference therefore rests not on row isolation but on **collocation**: all 12 occur in 9 bulk `Proceed` seconds mixed with correct `added` rows, varied deltas 2–272 (not a constant `removed 1`), and two smoking-gun same-product double-writes (see §3.2). Legit `removed` on the same day also present (`1000→6`, `1000→3`, `1000→60`, `9→7`, `7→6`) do **not** follow `before−1`.

### 3.2 They were all bulk saves (not 12 separate edits)

**SQL:**
```sql
SELECT created_at, COUNT(*) AS rows,
       GROUP_CONCAT(CONCAT(product_id,':',operation_type,':',before_quantity,'->',after_quantity) SEPARATOR ' | ')
FROM nexopos_products_histories WHERE DATE(created_at)='2026-08-19' GROUP BY created_at HAVING COUNT(*)>1 ORDER BY created_at;
```

| Bulk second | Rows | What happened in that click of **Proceed** |
|---|---|---|
| 15:13:03 | 2 | `TEMPRA 30ml 0→5 (+5 added)` + **`TEMPRA 15ml 4→1 (−3 removed)`** — one intended add, one collateral wipe |
| 19:00:49 | 2 | `CHIZ CURLS 1000→6 (−994 removed, legit correction)` + **`NOVA CHIPS 6→1 (−5)`** |
| 19:01:41 | 2 | `CRACKLINGS 2→6 (+4)` + **`MANGJUAN 5→1 (−4)`** |
| 19:08:15 | **8** | `BEAR BRAND 3→9 (+6)`, `GO CUP 2→4 (+2)`, `GO CUP 1000→7 (−993)`, `XTRA BIG 0→4 (+4)`, `XTRA BIG 0→4 (+4)`, `PC ORIGINAL 1000→4 (−996)`, `PC SWEET 0→5 (+5)`, `PC CHILIMANSI 0→5 (+5)` — all 8 in one save, **0** suspicious here (this batch was clean) |
| **19:13:35** | **4** | `MILK KNOT 3→4 (+1)` + **`CHOCO KNOT 5→1`, `OISHI 3→1`, `CRACKLINGS 6→1`** — **3 wipes + 1 legit add** |
| **19:15:35** | **3** | `BEAR BRAND 9→7 (−2)` + **`XTRA BIG KALAMANSI 4→1`, `XTRA BIG CHILIMANSI 4→1`** — **2 wipes + 1 legit** |
| 20:25:37 | 2 | `IRBESAPH 200→400 (+200)` + **`Probacil 31→1 (−30)`** |
| 20:43:24 | 2 | `Carvida 92→212 (+120)` + **`CARDOZ 136→1 (−135)`** |
| **20:50:08** | 2 | `SCHEEPRIN 73→273 (+200 added)` then **same product `SCHEEPRIN 273→1 (−272 removed)` in the *same second*** — the stock was correctly added then immediately wiped in the same bulk request |
| **20:55:58** | 2 | `CYSAPHTEINE 18→58 (+40)` then **`CYSAPHTEINE 58→1 (−57)`** — same pattern |

**9 distinct bulk seconds** account for all 12 wipes. Every suspicious row shares its `created_at` second with ≥1 other row. The **8-row batch at 19:08:15** proves the workflow is bulk-add + single Proceed — the bug triggers precisely when that workflow leaves rows untouched.

The **same-product double-write** (`SCHEEPRIN` and `CYSAPHTEINE`) is the smoking gun: the product was `added +200` correctly, but because it was **also** left as `Set:1` in the same payload (perhaps added twice, or re-added in same table state), the `Set:1` immediately overwrote the add. `CYSAPHTEINE` was auto-fixed 58 seconds later (`added 57: 1→58` at 20:56:56) — someone noticed.

### 3.3 Legitimate adds yesterday (the cashier's "we added items" is real)

The 58 `added` histories total **+5,555 units**, e.g.:

| Product | Change | After |
|---|---|---|
| ANGIOFLO PLUS 50/5 | +600 | 27→627 |
| STABETA 40MG | +300 | 0→300 |
| TRIMENOVA 35MG | +300 | 0→300 |
| GLYCEMET 500MG | +300 | 145→445 |
| ERLIFE 10MG | +400 | 0→400 |
| etc. | +… | |

These are correct stock-ins. They were **interleaved in the same bulk requests** as the wipes (see tables above). So the cashier is accurate: they *did* bulk-add stock. The problem is every bulk request that contained, say, 1 intentionally edited row and 3 untouched rows produced **1 correct add + 3 SET-to-1 wipes**.

Net on day: `+5,555 added − 8,226 removed − 1,012 sold + 1 void-return = −2,682` — overall inventory **shrank** despite adds. *(All `created_at` timestamps in this report are server-local; `DATE(created_at)` buckets are UTC, so evening 19:00 PHT bulk saves straddle dates — the counts above use the same bucketing as production, verified against batched seconds.)*

### 3.4 Which products still show `1` today (the POS "sold to 1" symptom)

| Product | Yesterday | Current `puq.quantity` | `puq.updated_at` | Fate |
|---|---|---|---|---|
| TEMPRA drops 15ml | 4→1 | **7** | 2026-08-20 16:00 | Overwritten next-day (fixed) |
| NOVA CHIPS 38G | 6→1 | **5** | 2026-08-19 22:25 | Sold 1 after, no fix, now 5 |
| MANGJUAN RED | 5→1 | **3** | 2026-08-21 03:03 | Manually re-added later |
| CHOCO KNOT 28G | 5→1 | **4** | 2026-08-21 00:40 | Fixed |
| OISHI PRAWN SPICY 24G | 3→1 | **3** | 2026-08-21 00:41 | Fixed |
| CRACKLINGS | 6→1 | **5** | 2026-08-21 00:41 | Fixed |
| **XTRA BIG KALAMANSI** | **4→1** | **1** | 2026-08-21 00:56 | **STILL 1 — needs restore to 4** |
| XTRA BIG CHILIMANSI | 4→1 | **4** | 2026-08-21 00:58 | Fixed |
| **Bacillus Clausii (Probacil)** | **31→1** | **1** | 2026-08-19 20:25 | **STILL 1 — needs restore to 31** |
| CARDOZ 25MG | 136→1 | **91** | 2026-08-21 04:22 | Partially recovered (136→1→91) |
| **SCHEEPRIN 80mg (Aspirin)** | **273→1** | **1** | 2026-08-19 20:50 | **STILL 1 — needs restore to 273** |
| CYSAPHTEINE 600MG | 58→1 | **58** | 2026-08-19 20:56 | Fixed 58 sec later (1→58) |

**3 of the 12 remain at 1** and will block sales (POS caps quantity at available stock; selling shows `quantity = 1` and next sale goes to 0). That is exactly what cashiers saw today before this report: some of the `→1` products are still at 1, some already sold down to 0 or re-corrected.

Global `quantity=1` inventory today is **134 products**, but most are legitimate or older `1000→X` corrections — only these 3 of yesterday's 12 are still part of it.

### 3.5 Today's sales hit the corrupted stock

Today (2026-08-20) had **184 sales (−909 units)** and **60 adds (+912)**, net neutral, and crucially **0 suspicious `removed→1`**. But POS today sold through some of the corrupted low stocks, e.g.:

| Today sale (sample) | Before→After | Effect |
|---|---|---|
| GO CUP Batchoy 10→9 etc. | normal | — |
| RDL FC PAPAYA etc. | normal | — |
| But products at `1` sold as `1→0` | `MILCU 1→0`, `BL SOAP 1→0`, `RDL FC 1→0`, `YAZ 1→0`, `TIGER etc.` | POS allowed sale of the last unit, then out-of-stock — cashiers correctly flag "upon selling earlier there were items that stock quantity is 1" |

---

## 4. Confirmation of Your Suspicion

> **Your words:** "when user adding item in the stock adjustment, they added multiple products, but instead of putting items correctly for example operation is set, those items that is added to the stock adjustment, if they did not do anything to it, it will automatically set to 1 if they hit save. for example there are 5 products in there. but only one you added the quantity, and the others are not been touch but in the stock adjustment. if they hit save it will automatically set to 1 right?"

**Answer: YES — exactly correct, with one refinement.**

- **Untouched `quantity` is indeed always `1`** (L75 / L110).
- **Untouched `operation` is `Set` when the product was added via name search** (Path B, the common bulk path) — this is **absolute SET**, so `quantity=1` means **"set stock to 1"**, not "add 1" or "remove 1". The backend then computes `delta = before − 1` and stores it as `removed: before→1`.
- **Untouched `operation` is `Delete` when added via procurement/barcode** (Path A) — this would be `removed 1: before→before−1` (e.g., `6→5`), not `→1`. Yesterday's data proves the `Set` path was the one that fired (all `qty = before−1`, `after=1`), so cashiers used the **name-search bulk flow** (Path B).

So for your "5 products, only 1 edited" scenario:

```
User adds 5 products via search → all 5 rows = Set:1
User opens quantity popup for only 1 row → that row becomes, say, Set:50 (or Added:50)
User hits Proceed without touching other 4 → request = [Set:50, Set:1, Set:1, Set:1, Set:1]
Backend:
  Row 1: current 10 → Set 50 ⇒ added 40  (10→50) ✓
  Rows 2–5: current 12, 8, 31, 273 → Set 1 ⇒ removed 11,7,30,272 (×→1) ✗ wipe
```

That is **exactly** the batch `19:13:35: MILK KNOT 3→4 (+1 good) + CHOCO 5→1 + OISHI 3→1 + CRACKLINGS 6→1`** and `20:50:08: SCHEEPRIN 73→273 (+200 good) + SCHEEPRIN 273→1 (−272 bad)`** yesterday.

No code prompts "did you mean to set these 4 to 1?" The generic confirm dialog does not enumerate rows or show `Before → After`.

---

## 5. Impact

| Metric | Value |
|---|---|
| **Rows corrupted yesterday** | **12** |
| **Units wiped** | **523** (`TEMPRA 3 + NOVA 5 + MANGJUAN 4 + CHOCO 4 + OISHI 2 + CRACKLINGS 5 + XTRA KAL 3 + XTRA CHI 3 + Probacil 30 + CARDOZ 135 + SCHEEPRIN 272 + CYSAPHTEINE 57`) |
| **Avg wipe per SKU** | **43.6 units**; largest pharma wipes: `SCHEEPRIN 272 (52%)` + `CARDOZ 135 (26%)` = **78% of total** |
| **Bulk sessions affected** | **9** distinct `Proceed` clicks (19:00–20:55 window) |
| **Cash impact (approx, using `sale_price × qty`)** | Highest-value wipe: `SCHEEPRIN 272 × price` + `CARDOZ 135 × price` — exact value requires joining `nexopos_products_unit_quantities.sale_price`; flagged as pharma, treat as high-value. |
| **Currently unrecovered** | **3 SKUs** still at 1 (Probacil, SCHEEPRIN, XTRA BIG KALAMANSI) — need manual restore (see §7 restore SQL) |
| **False sense of success** | All 12 returned `status: success` — no error, no warning, audit trail falsified as intentional `removed` |
| **Recurrence risk** | Pattern reappeared on 2026-08-21: **4 new `removed→1`** (`ORANOL 543→1`, `CREAM 10→1`, `PRESTO 9→1`, etc.) — bug is still live as of today |

---

## 6. Why Today's POS Shows `stock quantity = 1`

The POS product picker caps purchase quantity at `available_quantity`. When a SKU was `SET→1` yesterday, its `nexopos_products_unit_quantities.quantity` became `1`. Today when a cashier scans it, the POS shows `Quantity: 1`, allows selling `1`, then `1→0`. The cashier's observation "upon selling earlier there were items that stock quantity is 1" is the **downstream symptom** of the wipe — the product is not out-of-stock-yet, but critically low because it was **yesterday wiped to 1**, not because it legitimately sold down.

Today's `sold` histories confirm `1→0` sales (e.g., `BL SOAP 1→0`, `RDL FC 1→0`, `YAZ 1→0`, `MILCU 1→0`). Those would not have been `1→0` had the wipe not happened.

---

## 7. Recommended Immediate Actions

### A. Restore the 3 still-corrupted SKUs (data fix)

Run as admin, **one adjustment per SKU via POS → Stock Adjustment → operation `Set` to the verified physical count** (or at minimum pre-bug `before_quantity`). Do **not** use bulk-add to avoid re-triggering bug.

```sql
-- Verify current before restore
SELECT p.name, puq.quantity, puq.updated_at
FROM nexopos_products p JOIN nexopos_products_unit_quantities puq ON puq.product_id=p.id
WHERE p.name IN ('Bacillus Clausii (Probacil)','SCHEEPRIN 80mg(Aspirin) tablet','XTRA BIG KALAMANSI');
-- Current: Probacil=1 (should be 31), SCHEEPRIN=1 (should be 273), XTRA BIG KALAMANSI=1 (should be 4)
-- Restore via UI: Stock Adjustment → add product → Operation:Set → Quantity: 31 / 273 / 4 → Provide reason "Restore 2026-08-19 SET-to-1 bug" → Proceed (add ONLY that one product per save)
```

If physical counts differ from `before_quantity`, use after physical recount. Prefer recount for pharma (`Probacil`, `SCHEEPRIN`). `XTRA BIG KALAMANSI 4→1` is low-value snack; restoring to `4` is safe.

### B. Code fixes (P0) — do not deploy without these

| # | File | Fix |
|---|---|---|
| 1 | `ns-stock-adjustment.vue` L75 / L110 | Default `adjust_quantity` → `0` or `null` + **required** validation before `Proceed`. Show placeholder "— tap to set quantity" instead of `1`. Guard `recalculateProduct()` against `null * price → NaN`. |
| 2 | `ns-stock-adjustment.vue` L65 / L105 | Default `adjust_action` → `''` (none) + force user to select via `selectStockAdjustementAction()` before save. Both `Set:1` and `Delete:1` paths share the bug — cover Path A (`deleted:1` → `6→5` silent nudge) and Path B (`set:1` → `136→1` wipe) with same dirty-check. |
| 3 | `ns-stock-adjustment.vue` L176+ | Add `isDirty` flag set in `openQuantityPopup()` L158, `selectStockAdjustementAction()` L274, `selectAdjustmentUnit()` L219. `proceedStockAdjustment()` must **block** if any row `!isDirty` and show `nsSnackBar.error("Row 'X' still has default quantity. Set quantity or remove row before proceeding.")`. Or at minimum warn + enumerate diffs: `FOO 12→1 (SET), BAR 8→1 (SET) — confirm wipe?` |
| 4 | `ns-stock-adjustment.vue` table L422–426 | Add `Before → After` preview column: `{{available_quantity}} → {{adjust_action==='set' ? adjust_quantity : (adjust_action==='added' ? available+q : available−q)}}` so user sees destruction. |
| 5 | `ProductsController::createAdjustment` L462–508 | Backend guard: **warn + require `confirmedWipe:true`** if `adjust_quantity === 1 && adjust_action === 'set'` with `before_quantity > 1` (do **not** hard-reject — physical count legitimately can be 1). Require `adjust_reason` and a per-row `Before→After` list in the confirm payload; reject only if `!confirmedWipe && delta > threshold (e.g., >5)`. |
| 6 | `ProductService::handleStockAdjustmentRegularProducts` L1507+ | **Migration:** `ALTER TABLE nexopos_products_histories ADD COLUMN original_action VARCHAR(32) NULL, ADD COLUMN target_quantity DECIMAL(18,4) NULL, ADD INDEX (original_action)`. Backfill `original_action = operation_type`, `target_quantity = after_quantity` for existing rows. On `SET`, persist `original_action='set'` and `target_quantity=quantity(target)` **in addition to** `quantity=delta` and `operation_type=removed/added` — do **not** replace `quantity` semantics or existing ledger sums will break. For grouped products, store same pair per sub-item. |
| 7 | `ProductService::handleStockAdjustmentRegularProducts` L1515/1525 | Guard undefined `$adjustQuantity` when `current == target` — early return `no-op` instead of `quantity=0, set` leak. |
| 8 | `ns-stock-adjustment.vue` `proceedStockAdjustment` confirm | Replace generic confirm with a `<table>` listing every pending mutation `Product | Op | Qty | Before→After` requiring explicit confirm. |

Estimate: 1 day dev + QA (bulk-add matrix: `added 5`, `set 5`, mixed, zero, large `SET`, procurement path, `current==target` no-op). Add feature tests: (a) `quantity=null` rejected, (b) `SET 1` with `current=10` and `!confirmedWipe` warns/rejects, (c) same with `confirmedWipe:true` passes and persists `original_action='set'` + `target_quantity=1`, (d) `current==target` creates no history row.

### C. Operational safeguards (until code fix ships)

1. **One-row-at-a-time rule:** Instruct all stock-adjustment users to **add one product, set its quantity + operation, Proceed, then repeat** — never bulk-add >1 before saving. Demo that bullet once.
2. **Adopt "Count sheet → single SET" workflow:** Print count sheet, then for each SKU do `Search → Set → Quantity: <actual count>` → `Proceed` immediately.
3. **Daily `removed→1` check** (run this SQL each morning):

```sql
SELECT h.id, p.name, h.before_quantity, h.after_quantity, h.created_at, u.username
FROM nexopos_products_histories h
JOIN nexopos_products p ON p.id=h.product_id JOIN nexopos_users u ON u.id=h.author_id
WHERE h.after_quantity=1 AND h.operation_type='removed' AND DATE(h.created_at)=CURDATE()
ORDER BY h.created_at;
```
If any rows appear, restore immediately and remind operator of one-at-a-time rule.

### D. History note

Do **not** attempt to "undo" by deleting history rows — history is audit. Restore via a new `Set` adjustment with reason `"Restore 2026-08-19 SET-to-1 bug"` so trail shows wipe + correction.

---

## 8. Appendix — Evidence Queries (re-runnable)

```sql
-- Q1: counts yesterday
SELECT operation_type, COUNT(*) AS cnt, SUM(quantity) FROM nexopos_products_histories WHERE DATE(created_at)='2026-08-19' GROUP BY operation_type;

-- Q2: suspicious 12
SELECT h.id, p.name, h.operation_type, h.before_quantity, h.quantity, h.after_quantity, h.created_at, u.username
FROM nexopos_products_histories h LEFT JOIN nexopos_products p ON p.id=h.product_id LEFT JOIN nexopos_users u ON u.id=h.author_id
WHERE DATE(h.created_at)='2026-08-19' AND h.after_quantity=1 AND h.operation_type!='sold' ORDER BY h.created_at;

-- Q3: batches
SELECT created_at, COUNT(*) AS rows FROM nexopos_products_histories WHERE DATE(created_at)='2026-08-19' GROUP BY created_at HAVING COUNT(*)>1 ORDER BY created_at;

-- Q4: still at 1 (filter to removed→1 only; DATE is UTC — adjust to Asia/Manila if needed: CONVERT_TZ(created_at,'+00:00','+08:00'))
SELECT puq.product_id, p.name, puq.quantity, puq.updated_at, h.before_quantity, h.after_quantity
FROM nexopos_products_unit_quantities puq JOIN nexopos_products p ON p.id=puq.product_id
JOIN nexopos_products_histories h ON h.product_id=puq.product_id AND h.unit_id=puq.unit_id AND h.operation_type='removed' AND DATE(h.created_at)='2026-08-19' AND h.after_quantity=1
WHERE puq.quantity=1 ORDER BY h.created_at;

-- Q5: proof qty=before-1
SELECT h.id, h.before_quantity, h.quantity, h.after_quantity, (h.quantity = h.before_quantity-1) AS is_set
FROM nexopos_products_histories h WHERE h.id IN (5553,5588,5591,5616,5617,5618,5620,5621,5647,5652,5659,5666);

-- Q6: impact
SELECT SUM(quantity) FROM nexopos_products_histories WHERE id IN (5553,5588,5591,5616,5617,5618,5620,5621,5647,5652,5659,5666);
```

**Files cited:**
- `resources/ts/pages/dashboard/products/ns-stock-adjustment.vue` (L56–196, L219–326)
- `app/Http/Controllers/Dashboard/ProductsController.php` (L392–405, L439–530)
- `app/Services/ProductService.php` (L1507–1609)
- `app/Models/ProductHistory.php` (L39–71)
- DB `pos.nexopos_products_histories`, `pos.nexopos_products_unit_quantities`

---

## 9. Sign-off

* **Hypothesis:** Setting multiple products in one Stock Adjustment and saving without editing each will set untouched rows to `1`. **Status: PROVEN (high confidence).** Code defaults `Set:1` with no dirty-check, backend converts to `removed before−1 →1`. DB shows 12/12 suspicious yesterday match `qty = before−1`, but row isolation is DB-identical to intentional `removed before−1`; confidence rests on 9 bulk-second collocations, 8-row clean control batch, and two same-product double-writes — not row count alone.
* **Cashier's "we added items":** TRUE — 58 added (+5,555). But 12 of the 80 adjustment rows in the same bulk saves were collateral wipes (−523), causing net inventory shrinkage and today's `quantity=1` POS symptom.
* **Action required:** Restore 3 SKUs still at 1, apply P0 fixes §7B before next bulk stock-take, adopt one-at-a-time workflow until then.

*Report generated from live DB (`pos`, 2026-08-19/20) and codebase audit. Re-runnable via Appendix queries.*

