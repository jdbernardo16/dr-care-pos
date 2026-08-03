# Stock Movement Report — MYRA E 400 & DOLO NEUROBION

**Prepared:** 2026-08-03
**Purpose:** To explain why the stock of these two products went up and down. The movements were caused by stock adjustments (manual changes made by admin) — **not a system error**.

---

## Background

The store is currently doing a physical count of all items. While counting, items were temporarily set to a high stock number (1000) so the store could keep selling. Then, one by one, each item is being corrected to its actual count through the **Stock Adjustment** feature.

Stock adjustment records show:
- **who** made the change,
- **when** it was made,
- and **how much** stock was changed.

This means every movement on the Stock Flow page is a real action done by someone — either a sale, a restock, or a stock adjustment. It is not something the system does on its own.

---

## Product 1: MYRA E 400 I.U (Vitamin E)

### What happened, in order

| Date & Time | Action | Stock Change | Stock After | Done By |
|-------------|--------|--------------|-------------|---------|
| 07-29 03:47 | Stock adjustment (corrected from 1000 to actual count) | −961 | 39 | admin |
| 07-29 05:49 | Stock adjustment (added more stock) | +200 | 239 | admin |
| **07-31 00:15** | **Stock adjustment (stock set to 1)** | **−238** | **1** | **admin** |
| 08-01 04:19 | Sold 1 | −1 | 0 | RenzoOng |
| 08-02 05:14 | Stock adjustment (verified count entered) | +234 | 234 | admin |
| 08-02 05:15 | Sold 1 | −1 | 233 | jane_23 |
| 08-02 05:16 | Sold 1 | −1 | 232 | jane_23 |
| 08-03 17:13 | Sold 3 | −3 | **229** | jane_23 |

### Explanation

- On **07-31**, a stock adjustment set MYRA E 400 to **1 unit**. This was done through the Stock Adjustment screen at the same time as several other items were being adjusted in one go. It was a manual change — either a temporary setting while counting, or an accidental entry.
- After that, the remaining 1 unit was sold, so stock went to 0.
- On **08-02**, the verified count of **234** was entered, and stock became 234.
- Since then, **5 units were sold**, so the current stock is **229**.

**Conclusion:** The drop from 239 to 1 was a manual stock adjustment made by admin. The stock is now correct based on the 234 verified count minus the 5 items sold. **No system error.**

---

## Product 2: DOLO NEUROBION (Para+Bcomplex)

### What happened, in order

| Date & Time | Action | Stock Change | Stock After | Done By |
|-------------|--------|--------------|-------------|---------|
| 07-29 02:17 | Sold 5 | −5 | 23 | admin |
| 07-29 03:59 | Stock adjustment (corrected from 1000 to actual count) | −973 | 27 | admin |
| **07-30 22:52** | **Stock adjustment (stock set to 1)** | **−26** | **1** | **admin** |
| 08-04 01:20 | Stock adjustment (corrected back to 27) | +26 | 27 | admin |
| 08-04 01:20 | Sold 3 | −3 | **24** | ciara |

### Explanation

- On **07-30**, a stock adjustment set DOLO NEUROBION to **1 unit**. Like MYRA E 400, this was part of a group of items adjusted at the same time through the Stock Adjustment screen — a manual change.
- On **08-04**, the adjustment was **undone** — admin added the 26 units back, returning the stock to **27**. This confirms the "1" was never the real count; it was only a temporary change.
- After that, 3 units were sold, so the current stock is **24**.

**Conclusion:** The drop from 27 to 1 was a manual stock adjustment made by admin, and it was corrected back to 27 on 08-04. The current stock of 24 reflects 27 minus 3 sold. **No system error.**

---

## Other items affected in the same adjustments

When admin made these adjustments, several other items were changed at the same time (each group below was one adjustment session):

**07-30 22:52** — DOLO NEUROBION was adjusted together with:
- DR.WONG'S YELLOW 135g (set to 1)
- DR. WONG'S YELLOW 80g (set to 1)
- DIATABS 2mg (set to 1)

**07-31 00:15** — MYRA E 400 was adjusted together with:
- Mini Nebulizer machine (set to 1)
- MUPISAPH 20mg 5g (set to 1)
- MEOXICLAV 312.50/60ML (set to 1)
- MELA 15MG (set to 1)
- MYRA E 400iu 12+2 (stock removed — item deleted as duplicate)

Some of these were corrected back later, but a few still show **1 unit** today and should be checked against the physical count:
- ANGIOFLO PLUS 50/5
- Mini Nebulizer machine
- MELA 15MG
- REMEF 50MG
- SLEEPWELL 3mg

---

## Summary

1. **No bug.** The stock flow records are accurate — every change was made by a person (sale, restock, or stock adjustment) and is logged with the date, time, and who did it.
2. **The large drops** (MYRA E 400: 239 → 1, DOLO NEUROBION: 27 → 1) were **manual stock adjustments** made by admin while the store was doing the physical count. They were either temporary settings or accidental entries.
3. **Both products were later corrected** to their verified counts (MYRA E 400: 234, DOLO NEUROBION: 27), and the current stock simply reflects the items sold after that.
4. **Action needed:** verify the physical count for the few items still showing 1 unit (listed above) and update them through Stock Adjustment when counted.
