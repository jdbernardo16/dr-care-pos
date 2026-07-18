# POS Transaction UI Redesign

**Date:** 2026-07-19
**Status:** Approved
**Inspiration:** Loyverse POS transaction screen

## Objective

Redesign the Dr. Care POS transaction/cart screen to match the cleanliness and usability of Loyverse's transaction UI while preserving all existing functionality (comments, taxes, coupons, holds, discounts, voids, customer selection, order type).

## Current Layout

- Left/right split (50/50) — cart on left, product grid on right
- Cart header has 5 toolbar buttons: Comments, Taxes, Coupons, Settings, Quick Product
- Product items shown in 3-column table: Product | Qty | Total
- Totals section: table with Customer, Sub Total, Discount, Shipping, Tax, Total
- Bottom action bar: 4 equal buttons — Pay, Hold, Discount, Void
- Top header bar: Customers, Order Type, Orders, Reset, Dashboard, Register

## Proposed Layout

### Cart Panel (left, ~38% width)

**Header:**
- "Ticket" label on the left
- Order type badge (e.g., "Walk-in") + ⋮ menu on the right
- No toolbar buttons visible — all cart header actions behind ⋮

**Item Rows (Loyverse-style):**
- Row layout: item name (with unit/variant) on first line
- Below: inline quantity display — `× 2 @ ₱4.50` with a trash icon
- Line total on the far right of the first line
- Tap item to adjust quantity via popup
- Swipe left to delete (optional enhancement)

**Totals Section:**
- Subtotal, Discount, Tax, Total
- Clean minimal styling, Total emphasized with bold + top border

**Bottom Actions:**
- "Charge ₱XX.XX" button: 3/4 width, green (`bg-emerald-600`), rounded
- ⋮ button: 1/4 width, gray, opens dropdown/menu for:
  - Hold
  - Discount
  - Void/Clear
  - Comments
  - Taxes
  - Coupons
  - Settings
  - Quick Product

### Product Grid (right, ~62% width)

**Minimal Header:**
- Category filter chips (pills): "All Items" (active/green), "Coffee", "Food", etc.
- Right side icons: search, customer selector, order type toggle

**Product Display:**
- Keep existing grid layout (image tiles with name + price)
- Category navigation breadcrumbs unchanged
- Scale barcode support unchanged

### Top Header Bar
- Keep but simplify to essential icons only: search, customers, order-type
- Remove redundant buttons (reset, dashboard, register — accessible elsewhere)

## Payment Popup Redesign

Replace the current full-screen payment modal with a compact dialog overlay inspired by Loyverse.

**Layout:**
- Compact dialog (not full-screen), max-width ~480px
- Total due prominently displayed at top (₱17.50)
- Payment method tabs: horizontal pill-style (Cash, Card, Bank, Account)
- Quick-select cash amounts: ₱20, ₱50, ₱100, ₱200, Exact (rounded from total)
- Custom amount input field below quick amounts
- Change due display (green highlight)
- Two bottom buttons: Cancel | Charge ₱XX

**Removed:**
- Layaway option — removed entirely
- Unpaid submission — removed
- Payment list sidebar — replaced by compact tabs
- Full-screen overlay — replaced by dialog

## Changes Summary

| Area | Current | New |
|------|---------|-----|
| Cart width | ~50% | ~38% |
| Cart header | 5 toolbar buttons | Minimal — actions behind ⋮ |
| Item rows | 3-column table (Prod/Qty/Total) | Name + inline qty + total |
| Totals | Prominent table with customer/shipping/etc | Compact: subtotal/discount/tax/total |
| Bottom buttons | 4 equal buttons (Pay/Hold/Disc/Void) | Charge 3/4 + ⋮ 1/4 |
| Category filter | Dropdown | Pill chips |
| Top bar | 6 buttons | Minimal icons |
| Payment popup | Full-screen modal | Compact dialog |
| Payment amounts | Manual entry only | Quick-select + manual |
| Layaway | Available | Removed |

## Preserved Functionality

All existing POS functionality is preserved:
- Customer selection (top bar icon)
- Order type (badge in cart header + top bar)
- Comments, Taxes, Coupons (⋮ menu)
- Hold, Discount, Void (⋮ menu)
- Quick product add (⋮ menu)
- Product search, category navigation
- Scale barcode support
- All popups (price, discount, quantity, etc.)
- Keyboard shortcuts

## Architecture

This is a **CSS and template-only change** to:
- `resources/ts/pages/dashboard/pos/ns-pos-cart.vue` — cart component layout
- `resources/ts/pages/dashboard/pos/ns-pos.vue` — parent layout (cart/grid proportions)
- `resources/ts/popups/ns-pos-payment-popup.vue` — payment dialog redesign
- `resources/css/light/_pos.css` — styling updates
- `resources/css/dark/_pos.css` — dark theme equivalent

No backend changes. The ⋮ menu reuses the existing popup/dropdown system. Existing button components (Pay, Hold, Discount, Void) are moved into the ⋮ dropdown rather than removed.

## Implementation Order

1. Update cart panel width and layout in `ns-pos.vue`
2. Restyle item rows in `ns-pos-cart.vue` (Loyverse-style)
3. Simplify totals section
4. Replace 4 bottom buttons with Charge + ⋮
5. Move cart header buttons into ⋮ dropdown
6. Redesign payment popup (`ns-pos-payment-popup.vue`): compact dialog, quick amounts, remove layaway
7. Update category filter to pill chips
8. Clean up top header bar
9. Update CSS for both themes
10. Test all interactions (charge, hold, discount, void, comments, taxes, coupons, payment)
