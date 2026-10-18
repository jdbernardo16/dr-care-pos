# POS UI Rehaul — Loyverse-Inspired Design

**Date:** 2026-07-29
**Status:** Draft

## 1. Objective

Rehaul the POS interface at `/dashboard/pos` to match the layout, interaction patterns, and transaction flow of Loyverse POS. The current POS has several UX gaps identified:

- Barcode scanner input only accepts barcodes (no product name search)
- After charge, receipt prints immediately without a transaction summary
- No inline quantity editing in the ticket panel
- No way to view product price without adding to cart
- Delete button is always visible (no swipe gesture)
- Layout is cart-left / grid-right (reversed vs Loyverse)

## 2. Layout Structure

```
┌─────────────────────────────────────────────────────────┐
│  Header Bar (Registers | Pending | Dashboard | Order    │
│            Type | Customers | Reset)                    │
├──────────────────────────────┬──────────────────────────┤
│                              │                          │
│   Product Grid (62%)         │   Ticket Panel (38%)     │
│                              │                          │
│   ┌─ Search Bar ──────────┐  │  ┌─ Ticket Header ──┐   │
│   │ [🔍 text input] [📷]  │  │  │ Ticket    [Save] │   │
│   └───────────────────────┘  │  └──────────────────┘   │
│                              │                          │
│   ┌─ Categories / Products   │  │  Item 1           ×N  │
│   │  (grid of tiles)    ──┐  │  │  Item 2      ₱XXX   │
│   │                       │  │  │  (swipe → delete)    │
│   │  [img] [img] [img]    │  │  │                      │
│   │  name  name   name    │  │  │  ──────────────────  │
│   │  ₱XX   ₱XX    ₱XX    │  │  │  Subtotal    ₱XXX    │
│   │                       │  │  │  Discount    -₱XX    │
│   └───────────────────────┘  │  │  Tax          ₱XX    │
│                              │  │  Total        ₱XXX   │
│                              │  │  ──────────────────  │
│                              │  │  [Charge ₱XXX] [⋯]   │
│                              │  │                       │
└──────────────────────────────┴──────────────────────────┘
```

### 2.1 Panels

- **Left panel (62%)**: Product Grid — categories, product tiles, search
- **Right panel (38%)**: Ticket Panel — added items, summary, charge button

This is the reverse of the current layout (`ns-pos.vue` line 12: currently cart is 38% left, grid is 62% right).

### 2.2 Responsive Behavior

- On mobile/small screens: single-panel layout with tab switching (Cart / Products tabs)
- On tablet/desktop: split-panel layout as shown above

## 3. Header Bar

Keeps existing header buttons (abstracted as `POS.header.buttons`):
- Registers button
- Pending Orders button
- Dashboard button
- Order Type button
- Customers button
- Reset button

No changes needed to the header components.

## 4. Product Grid (Left Panel)

### 4.1 Search & Barcode Scanner

- **Default mode**: Text input searches by product name or SKU (search-as-you-type with 200ms debounce)
- **Barcode mode**: Toggle via 📷 (barcode) icon button next to the search field. When active:
  - Visual indicator (e.g., icon highlight + "Scanning..." placeholder text)
  - Input accepts barcode scans, matches against `/api/products/search/using-barcode/{value}`
  - Scanned product auto-adds to cart
- The current implementation in `ns-pos-grid.vue` lines 55-61 and 701-791 handles barcode submission — needs to be extended for dual-mode (search + scan)

### 4.2 Category Navigation

- Categories displayed as grid tiles (same as current)
- Clicking a category drills into its subcategories or reveals products
- Breadcrumb trail for navigation back to parent categories

### 4.3 Product Tiles

- Square tiles showing: product thumbnail, name, price
- **Tapping a tile adds the product directly to cart** (no quantity prompt, resolves price inquiry issue)
- Out-of-stock products show "OOS" badge and are disabled
- Single-unit products show price directly; multi-unit products use the default unit

### 4.4 Pinned Products

- Keep the existing pinned products strip at the top of the grid section
- Toggle via `ns_pos_enable_pinned_products` setting

## 5. Ticket Panel (Right Panel)

### 5.1 Ticket Header

- "Ticket" title
- Save button / Open Tickets dropdown (for saving orders to complete later)

### 5.2 Line Items

Each item in the ticket shows:
```
Product Name — unit_name
× quantity @ unit_price    total_price
                    [Price] [Discount]
```

- **Swipe-left gesture** on a line item reveals a red **Delete** button at the right
- **Tap on a line item** opens the Product Edit Modal (see §6)

### 5.3 Summary Section (Sticky Bottom)

```
──────────────────
Subtotal    ₱XXX
Discount   -₱XX      (if applicable, shows % if percentage)
Tax         ₱XX      (if applicable)
──────────────────
Total       ₱XXX     (bold, large)
```

### 5.4 Bottom Buttons

- **Charge** (primary, big button): shows `Charge ₱{total}` — opens payment popup
- **More** (secondary): opens options menu (discount, tax, note, shipping, etc.)

## 6. Product Edit Modal

Opened when user taps a product line item in the ticket panel.

### 6.1 Layout

```
┌──────────────────────────────────┐
│  Edit Product          [✕]      │
│                                  │
│  Product Name                     │
│  ─ unit_name                      │
│                                  │
│  Quantity:                        │
│     [-] [  2  ] [+]              │
│                                  │
│  Unit Price: ₱XXX.XX             │
│  (editable if setting allows)    │
│                                  │
│  Discount:                        │
│     [Percentage ▼] [ 0% ]        │
│     or                           │
│     [Flat ▼]      [ ₱0.00 ]     │
│                                  │
│  Subtotal: ₱XXX.XX               │
│                                  │
│  [Cancel]        [Save]          │
└──────────────────────────────────┘
```

### 6.2 Quantity Control

- `[ - ]` decrements (minimum 1)
- `[ + ]` increments
- Center: editable number input for direct entry
- Respects stock management limits

### 6.3 Actions within Modal

- **Save**: Updates the product in the cart (calls `POS.updateProduct`)
- **Cancel**: Closes modal, no changes

## 7. Transaction Flow

### 7.1 Charge → Payment

1. User taps **Charge** button
2. Payment popup opens (current `ns-pos-payment-popup.vue` reused with minimal changes):
   - Total Due display
   - Payment method tabs (Cash, Card, GCash, etc.)
   - Quick amount buttons
   - Custom amount input
   - Change due display
   - Charge button

### 7.2 Payment → Transaction Summary

After payment is processed successfully:

1. **Cart panel is replaced** by the **Transaction Summary** component
2. The Transaction Summary replaces the cart panel in the same slot (right panel, 38%)
3. Shows:

```
┌────────────────────────────┐
│  Transaction Complete ✓    │
│                            │
│  ── Items ──               │
│  Item 1            ×2      │
│  Item 2            ×1      │
│                            │
│  ── Payment ──             │
│  Cash          ₱500.00     │
│  Change Due    ₱ 42.00     │
│                            │
│  Total Charged  ₱458.00    │
│                            │
│  [Print Receipt]           │
│  [New Sale]                │
└────────────────────────────┘
```

### 7.3 Post-Transaction Actions

- **Print Receipt**: Calls `POS.printOrderReceipt()` — manual, user-initiated
- **New Sale**: Clears the cart, resets the POS for a new transaction, switches cart panel back to default empty state

No auto-print prompt. No browser print dialog forced.

## 8. Swipe-to-Delete

### 8.1 Implementation

- Implement touch gesture detection on ticket line items
- Swipe left on a line item reveals a **Delete** button anchored to the right edge
- Delete button: red background, trash icon, or "Delete" label
- Tap delete → confirmation popup (existing `ns-pos-confirm-popup.vue`) → removes item from cart
- Swipe right or tap elsewhere to hide the delete button

### 8.2 Mouse Support

- On desktop (no touch): swipe can be simulated via click-and-drag, or keep the current hover behavior
- Alternatively: show the delete button on hover for mouse users (both gestures active)

## 9. Components to Create/Modify

### 9.1 New Components

| Component | Description |
|-----------|-------------|
| `ns-pos-transaction-summary.vue` | Replaces cart panel after payment — shows receipt breakdown, print/new sale buttons |
| `ns-pos-product-edit-modal.vue` | Modal for editing product quantity, price, discount in cart |

### 9.2 Modified Components

| Component | Changes |
|-----------|---------|
| `ns-pos.vue` | Flip panel order (grid to left, cart to right) |
| `ns-pos-grid.vue` | Add search-input + barcode-toggle dual mode; direct-add-to-cart on tap (no quantity prompt) |
| `ns-pos-cart.vue` | Swipe-to-delete; tap-to-edit opens modal; update summary display |
| `ns-pos-charge-button.vue` | After payment, trigger transaction summary instead of direct receipt print |
| `ns-pos-payment-popup.vue` | After `submitOrder`, show transaction summary instead of printing receipt |

### 9.3 Payment Queue (`payment-queue.ts`)

- Currently opens payment popup only
- Add a post-payment callback or state to trigger transaction summary

## 10. Data Flow

```
User taps product tile
  → POS.addToCart(product)
  → POS.products observable updates
  → Cart panel re-renders with new item

User taps line item in cart
  → Product Edit Modal opens
  → User edits quantity/price/discount
  → On save: POS.updateProduct() + POS.refreshCart()
  → Cart re-renders

User swipes left on line item
  → Delete button revealed
  → Tap delete → POS.removeProductUsingIndex(index)

User taps Charge
  → PaymentQueue runs
  → Payment popup opens
  → User enters payment → POS.addPayment() + POS.submitOrder()
  → On success: Payment popup closes
  → Cart panel swaps to Transaction Summary
  → User clicks Print or New Sale
```

## 11. Files Referenced

- `resources/ts/pages/dashboard/pos/ns-pos.vue` — Main layout
- `resources/ts/pages/dashboard/pos/ns-pos-cart.vue` — Cart/ticket panel
- `resources/ts/pages/dashboard/pos/ns-pos-grid.vue` — Product grid
- `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue` — Charge button
- `resources/ts/pages/dashboard/pos/queues/order/payment-queue.ts` — Payment flow
- `resources/ts/popups/ns-pos-payment-popup.vue` — Payment popup
- `resources/ts/popups/ns-pos-search-product.vue` — Product search popup

## 12. Scope

**In scope:**
- Layout flip and visual restyle
- Search bar with dual-mode (search + barcode scan)
- Direct add-to-cart from grid tiles
- Product Edit Modal (tap line item to edit)
- Swipe-to-delete on line items
- Transaction Summary replacing cart after charge
- Manual print receipt (no auto-prompt)

**Out of scope (separate project):**
- Open Tickets management (save/load orders) — wireframe only
- Split payment
- Refunds
- Kitchen display / Customer display
- Inventory management within POS
- Back Office settings changes
