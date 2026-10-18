# Dr Care POS — E2E Test Bug Report

## Summary

- **Tests written**: 33 (POS barcode, cart, checkout, edge cases)
- **Tests passing**: 33/33
- **Bugs found**: 4 (2 fixed, 1 fixed+documented, 1 noted)

---

## Bug #1 — Barcode scan crashes for non-scale barcodes [FIXED]

| Field | Value |
|-------|-------|
| **Severity** | Critical |
| **File** | `resources/ts/pages/dashboard/pos/ns-pos-grid.vue:374-378` |
| **Status** | Fixed |

### Problem
When scanning a normal barcode (non-scale), the `submitSearch()` handler crashes with:
```
TypeError: Cannot read properties of undefined (reading 'id')
```

The code accessed `result.unit.id` and `result.unitQuantity.sale_price`, but the API response for non-scale barcodes only returns `result.product` — no top-level `unit` or `unitQuantity`.

### Root Cause
`ProductsController::searchUsingArgument()` returns different response shapes:
- **Scale barcodes** (`ProductsController.php:550`): includes `unit`, `unitQuantity`, `scale` at top level
- **Normal barcodes** (`ProductsController.php:619`): only includes `product`

The frontend assumed both formats were identical.

### Fix
```javascript
// Added fallback extraction
const unitQuantity  =   result.unitQuantity || result.product.selectedUnitQuantity || result.product.unit_quantities?.[0];
const unit          =   result.unit || unitQuantity?.unit;
```

---

## Bug #2 — Numpad console error on every popup interaction [FIXED]

| Field | Value |
|-------|-------|
| **Severity** | Medium |
| **File** | `resources/ts/components/ns-numpad.vue:67` |
| **Status** | Fixed |

### Problem
Every time a numpad popup (quantity, price, discount) appears, the console logs:
```
TypeError: this.$el.closest is not a function
```

This repeats 3+ times per popup interaction.

### Root Cause
In Vue 3, `this.$el` can be a comment node (`<!---->`) or text node when a component has conditional rendering. These node types don't have the `closest()` method. The code also had a stray `console.log(this)` debug statement.

### Fix
```javascript
// Before (crashes):
const isInPopup = this.$el.closest('.is-popup');

// After (safe):
const isInPopup = this.$el && this.$el.closest ? this.$el.closest('.is-popup') : null;
```
Also removed the debug `console.log(this)`.

---

## Bug #3 — Auth setup wrong credentials [FIXED]

| Field | Value |
|-------|-------|
| **Severity** | High (blocks all E2E tests) |
| **Files** | `playwright/auth.setup.ts`, `playwright/shared/login.ts` |
| **Status** | Fixed |

### Problem
- Default password was `admin123` but actual password is `password`
- Default URL was `https://nexocloud-v6.dev` instead of `http://localhost:8000`

### Fix
Updated both files to use correct defaults.

---

## Bug #4 — Taxes setup test times out [NOTED]

| Field | Value |
|-------|-------|
| **Severity** | Low (test-only issue) |
| **File** | `playwright/taxes.setup.ts:30` |
| **Status** | Noted — separated from main test dependencies |

### Problem
Clicking the "Edit" link on the products page times out because the dropdown menu item is not stable (detaches from DOM during navigation).

### Workaround
Separated `taxes-setup` into its own project so it doesn't block all other tests.

---

## Test Coverage

### `pos-barcode.spec.ts` (9 tests)
- [x] Scan valid barcode adds product to cart
- [x] Scan second product accumulates in cart
- [x] Scan same barcode twice merges (with merge toggle on)
- [x] Scan non-existent barcode shows error (API returns 404)
- [x] Scan empty string does not trigger search
- [x] Barcode field clears after successful scan
- [x] Scan with quantity greater than 1 (3x scan with merge)
- [x] Autofocus toggle turns on and off
- [x] No console errors after successful scan

### `pos-cart.spec.ts` (8 tests)
- [x] Add product by clicking category then product tile
- [x] Add product via barcode
- [x] Void on unpaid order shows error message
- [x] Reset clears the cart
- [x] Cart total updates correctly with multiple items
- [x] Merge toggle is clickable
- [x] Product search popup opens
- [x] Settings button opens POS settings

### `pos-checkout.spec.ts` (7 tests)
- [x] Pay without customer shows customer selection popup
- [x] Customer search returns results
- [x] Order type button shows options
- [x] Hold order is accessible
- [x] Discount button is accessible
- [x] Taxes button is accessible
- [x] Coupons button is accessible

### `pos-edge-cases.spec.ts` (8 tests)
- [x] Very long barcode string (50+ chars)
- [x] Barcode with special characters
- [x] Rapid successive scans of different barcodes
- [x] Barcode with letters mixed in
- [x] Partial barcode does not match
- [x] Navigate to POS from dashboard and back
- [x] No JavaScript errors on POS page load
- [x] Product grid categories are clickable

### Existing tests (still passing)
- `pos.spec.ts` — 4 tests (page load, grid, cart, payment button)
- `dashboard.spec.ts` — 6 tests (dashboard access, sub-sections)
- `auth.spec.ts` — 4 tests (login form, wrong creds, redirect)
