# POS Transaction UI Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the POS transaction screen to match Loyverse's clean, tablet-optimized UI — left cart panel, simplified item rows, Charge + ⋮ bottom actions, compact payment dialog.

**Architecture:** Template and CSS changes to `ns-pos.vue`, `ns-pos-cart.vue`, and `ns-pos-payment-popup.vue`. Two new components: `ns-pos-charge-button.vue` and `ns-pos-more-button.vue`. Buttons are dynamically registered via `POS.cartButtons` and `POS.cartHeaderButtons` observables.

**Tech Stack:** Vue 3, TypeScript, Tailwind CSS v4, RxJS observables

---

### File Structure

| File                                                                     | Role                                                           |
| ------------------------------------------------------------------------ | -------------------------------------------------------------- |
| `resources/ts/pages/dashboard/pos/ns-pos.vue`                            | Parent layout: cart width `50%` → `38%`                        |
| `resources/ts/pages/dashboard/pos/ns-pos-cart.vue`                       | Cart component: item rows, totals, header, bottom actions      |
| `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue` | **New** — prominent Charge ₱XX button                          |
| `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue`   | **New** — ⋮ menu (Hold, Disc, Void, Comments, etc.)            |
| `resources/ts/popups/ns-pos-payment-popup.vue`                           | Payment dialog: compact overlay, quick amounts, remove layaway |
| `resources/css/light/_pos.css`                                           | Light theme styling updates                                    |
| `resources/css/dark/_pos.css`                                            | Dark theme styling updates                                     |

---

### Task 1: Update Parent Layout (Cart Width)

**Files:**

- Modify: `resources/ts/pages/dashboard/pos/ns-pos.vue:12-13`

- [ ] **Change cart width class from `w-1/2` to `w-[38%]`**

```diff
- <div :class="visibleSection === 'both' ? 'w-1/2' : 'w-full'" ...>
+ <div :class="visibleSection === 'both' ? 'w-[38%]' : 'w-full'" ...>
```

- [ ] **Change grid width class from `w-1/2` to `w-[62%]`**

```diff
- <div :class="visibleSection === 'both' ? 'w-1/2' : 'w-full'" ...>
+ <div :class="visibleSection === 'both' ? 'w-[62%]' : 'w-full'" ...>
```

- [ ] **Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos.vue
git commit -m "pos: adjust cart/grid split to 38/62 percent"
```

---

### Task 2: Create Charge Button Component

**Files:**

- Create: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue`

- [ ] **Create `ns-pos-charge-button.vue`**

```vue
<template>
    <div
        @click="payOrder()"
        id="charge-button"
        class="flex-shrink-0 flex items-center font-bold cursor-pointer justify-center flex-auto rounded-lg bg-emerald-600 text-white text-lg lg:text-xl h-14 mx-1"
    >
        <i class="mr-2 text-xl las la-cash-register"></i>
        <span class="text-base lg:text-lg"
            >{{ __("Charge") }} {{ nsCurrency(order.total) }}</span
        >
    </div>
</template>
<script lang="ts">
declare const POS;
declare const nsShortcuts;
declare const nsHotPress;
declare const __;

import { nsCurrency } from "~/filters/currency";

export default {
    props: ["order"],
    components: { nsCurrency },
    methods: {
        __,
        nsCurrency,
        async payOrder() {
            POS.runPaymentQueue();
        },
    },
    mounted() {
        for (let shortcut in nsShortcuts) {
            if (["ns_pos_keyboard_payment"].includes(shortcut)) {
                nsHotPress
                    .create("ns_pos_keyboard_payment")
                    .whenNotVisible([".is-popup"])
                    .whenPressed(
                        nsShortcuts[shortcut] !== null
                            ? nsShortcuts[shortcut].join("+")
                            : null,
                        (event) => {
                            event.preventDefault();
                            this.payOrder();
                        },
                    );
            }
        }
    },
    unmounted() {
        nsHotPress.destroy("ns_pos_keyboard_payment");
    },
};
</script>
```

- [ ] **Commit**

```bash
git add resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue
git commit -m "pos: create Charge button component with total display"
```

---

### Task 3: Create More (⋮) Menu Component

**Files:**

- Create: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue`

- [ ] **Create `ns-pos-more-button.vue`**

```vue
<template>
    <div class="relative flex-shrink-0" id="more-button">
        <div
            @click="toggleMenu()"
            class="flex items-center font-bold cursor-pointer justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xl h-14 w-14 mx-1 border border-gray-200 dark:border-gray-700"
        >
            <span>⋮</span>
        </div>
        <div
            v-if="showMenu"
            class="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
        >
            <div
                @click="action.click"
                v-for="action of menuActions"
                :key="action.label"
                class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 last:border-0"
            >
                <i :class="action.icon" class="text-lg"></i>
                <span>{{ action.label }}</span>
            </div>
        </div>
    </div>
</template>
<script lang="ts">
declare const POS, nsHooks, __;

export default {
    props: ["order", "settings"],
    data: () => ({
        showMenu: false,
        menuActions: [],
    }),
    methods: {
        __,
        toggleMenu() {
            this.showMenu = !this.showMenu;
        },
        closeMenu() {
            this.showMenu = false;
        },
        buildMenu() {
            const items = [];
            const order = this.order;

            if (order && order.products?.length > 0) {
                items.push({
                    label: __("Hold"),
                    icon: "las la-pause",
                    action: () => {
                        this.closeMenu(); /* nsHooks.doAction triggers hold */
                    },
                });
                items.push({
                    label: __("Discount"),
                    icon: "las la-percent",
                    action: () => {
                        this.closeMenu(); /* open discount popup */
                    },
                });
                items.push({
                    label: __("Void"),
                    icon: "las la-trash",
                    action: () => {
                        this.closeMenu(); /* void order */
                    },
                });
                items.push({
                    label: __("Comments"),
                    icon: "las la-comment",
                    action: () => {
                        this.closeMenu(); /* open comment popup */
                    },
                });
                items.push({
                    label: __("Taxes"),
                    icon: "las la-balance-scale-left",
                    action: () => {
                        this.closeMenu(); /* open tax popup */
                    },
                });
                items.push({
                    label: __("Coupons"),
                    icon: "las la-tags",
                    action: () => {
                        this.closeMenu(); /* open coupon popup */
                    },
                });
                items.push({
                    label: __("Settings"),
                    icon: "las la-tools",
                    action: () => {
                        this.closeMenu(); /* open settings popup */
                    },
                });
                items.push({
                    label: __("Quick Product"),
                    icon: "las la-plus",
                    action: () => {
                        this.closeMenu(); /* open quick product popup */
                    },
                });
            }

            this.menuActions = items;
        },
        handleClickOutside(e) {
            if (!this.$el.contains(e.target)) {
                this.showMenu = false;
            }
        },
    },
    mounted() {
        this.buildMenu();
        document.addEventListener("click", this.handleClickOutside);
    },
    unmounted() {
        document.removeEventListener("click", this.handleClickOutside);
    },
};
</script>
```

**Note:** The action handlers will be wired up with proper imports in a subsequent task. This creates the structure first.

- [ ] **Commit**

```bash
git add resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue
git commit -m "pos: create More (⋮) menu component for secondary actions"
```

---

### Task 4: Restyle Cart — Header, Item Rows, Totals, Bottom Actions

**Files:**

- Modify: `resources/ts/pages/dashboard/pos/ns-pos-cart.vue`

**This is the largest task. It restructures the cart template.**

- [ ] **Replace cart toolbox header with minimal "Ticket" header**

Remove lines 14-22 (the `#cart-toolbox` div with header buttons). Replace with:

```html
<div
    id="cart-header"
    class="flex items-center justify-between px-3 py-2 border-b border-gray-200 dark:border-gray-700"
>
    <span class="font-bold text-lg">{{ __( 'Ticket' ) }}</span>
    <div class="flex items-center gap-2">
        <span
            class="text-sm bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-3 py-1 rounded-full"
            >{{ order.type ? order.type.label : 'N/A' }}</span
        >
    </div>
</div>
```

- [ ] **Replace 3-column table header with minimal header**

Change lines 23-27:

```html
<div id="cart-table-header" class="hidden"></div>
```

- [ ] **Restyle item rows to Loyverse-style**

Replace lines 38-82 (the product item loop) with:

```html
<div
    :product-index="index"
    :key="product.barcode"
    class="product-item px-3 py-3 border-b border-gray-100 dark:border-gray-800"
    v-for="(product, index) of products"
>
    <div class="flex justify-between items-start">
        <div class="flex-1 min-w-0">
            <div
                class="font-semibold text-base lg:text-lg text-gray-900 dark:text-gray-100 truncate"
            >
                {{ product.name }}
                <span
                    class="text-gray-400 dark:text-gray-500 font-normal text-sm"
                    v-if="product.unit_name"
                    >&mdash; {{ product.unit_name }}</span
                >
            </div>
            <div
                class="flex items-center gap-3 mt-1 text-sm lg:text-base text-gray-500 dark:text-gray-400"
            >
                <span class="flex items-center gap-1">
                    <span class="text-gray-400">×</span>
                    <span
                        class="font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded"
                        >{{ displayProductQuantity(product) }}</span
                    >
                    <span class="text-gray-400"
                        >@ {{ nsCurrency(product.unit_price) }}</span
                    >
                </span>
                <button
                    @click="removeUsingIndex(index)"
                    class="text-red-400 hover:text-red-600 text-sm"
                >
                    <i class="las la-trash-alt"></i>
                </button>
            </div>
            <div class="flex flex-wrap gap-2 mt-1 text-sm">
                <a
                    @click="changeProductPrice(product)"
                    class="text-blue-500 hover:text-blue-700 cursor-pointer border-b border-dashed border-blue-300"
                    >{{ __( 'Price' ) }}: {{ nsCurrency(product.unit_price)
                    }}</a
                >
                <a
                    v-if="allowQuantityModification(product)"
                    @click="openDiscountPopup(product, 'product', index)"
                    class="text-blue-500 hover:text-blue-700 cursor-pointer border-b border-dashed border-blue-300"
                    >{{ __( 'Discount' ) }}
                    <span v-if="product.discount_type === 'percentage'"
                        >{{ product.discount_percentage }}%</span
                    >: {{ nsCurrency(product.discount) }}</a
                >
            </div>
        </div>
        <div
            class="text-base lg:text-lg font-bold text-gray-900 dark:text-gray-100 flex-shrink-0 ml-4"
        >
            {{ nsCurrency(product.total_price) }}
        </div>
    </div>
</div>
```

- [ ] **Simplify totals section**

Replace lines 87-223 (both totals tables) with:

```html
<div
    id="cart-products-summary"
    class="px-3 py-2 border-t border-gray-200 dark:border-gray-700"
>
    <div class="space-y-1 text-sm lg:text-base">
        <div class="flex justify-between text-gray-500 dark:text-gray-400">
            <span>{{ __( 'Subtotal' ) }}</span>
            <span>{{ nsCurrency(order.subtotal) }}</span>
        </div>
        <div
            class="flex justify-between text-gray-500 dark:text-gray-400"
            v-if="order.discount > 0"
        >
            <span
                >{{ __( 'Discount' ) }}<span
                    v-if="order.discount_type === 'percentage'"
                >
                    ({{ order.discount_percentage }}%)</span
                ></span
            >
            <span>-{{ nsCurrency(order.discount) }}</span>
        </div>
        <div
            class="flex justify-between text-gray-500 dark:text-gray-400"
            v-if="order.tax_value > 0"
        >
            <span>{{ __( 'Tax' ) }}</span>
            <span>{{ nsCurrency(order.tax_value) }}</span>
        </div>
        <div
            class="flex justify-between font-bold text-lg lg:text-xl text-gray-900 dark:text-gray-100 border-t-2 border-gray-900 dark:border-gray-100 pt-2 mt-2"
        >
            <span>{{ __( 'Total' ) }}</span>
            <span>{{ nsCurrency(order.total) }}</span>
        </div>
    </div>
</div>
```

- [ ] **Replace bottom buttons area**

Change lines 225-237 (the `#cart-bottom-buttons` div). Replace all content inside it with:

```html
<div
    class="flex items-center px-2 py-2 border-t border-gray-200 dark:border-gray-700 gap-1"
    id="cart-bottom-buttons"
>
    <template v-for="component of cartButtons">
        <component
            :is="component"
            :order="order"
            :settings="settings"
        ></component>
    </template>
</div>
```

- [ ] **Update defaultCartButtons to use new Charge + More**

Change lines 288-293 to:

```ts
defaultCartButtons: {
    nsPosChargeButton: markRaw( nsPosChargeButton ),
    nsPosMoreButton: markRaw( nsPosMoreButton ),
},
```

- [ ] **Remove cartHeaderButtons default registration and header button imports**

Remove lines 295-301 (defaultCartHeaderButtons) and remove header button imports (lines 256-260).

Remove the `cartHeaderButtons` and `cartHeaderButtonSubscriber` data/subscription entirely.

Remove the `POS.cartHeaderButtons.next()` call in the ns-before-cart-reset hook (line 368).

- [ ] **Update imports**

Replace:

```ts
import nsPosPayButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-pay-button.vue";
import nsPosHoldButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-hold-button.vue";
import nsPosDiscountButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-discount-button.vue";
import nsPosVoidButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-void-button.vue";
```

With:

```ts
import nsPosChargeButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue";
import nsPosMoreButton from "~/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue";
```

Remove header button imports (lines 256-260).

- [ ] **Wire up ⋮ menu actions with actual popup calls**

Add these imports to `ns-pos-cart.vue` script:

```ts
import { Popup } from "~/libraries/popup";
import nsPosConfirmPopup from "~/popups/ns-pos-confirm-popup.vue";
import nsPosDiscountPopupVue from "~/popups/ns-pos-discount-popup.vue";
import nsPosNotePopupVue from "~/popups/ns-pos-note-popup.vue";
import nsPosTaxPopupVue from "~/popups/ns-pos-tax-popup.vue";
import nsPosCouponsLoadPopupVue from "~/popups/ns-pos-coupons-load-popup.vue";
import nsPosOrderSettingsVue from "~/popups/ns-pos-order-settings.vue";
import nsPosQuickProductPopupVue from "~/popups/ns-pos-quick-product-popup.vue";
```

Pass these as props or use a ref-based approach. The simplest approach: pass the cart's action methods as props to ns-pos-more-button.

Actually, a cleaner approach is to have `ns-pos-cart.vue` define the menu actions and pass them. Let me update the approach:

In `ns-pos-cart.vue`, add a computed property `moreMenuActions` that returns the action list with actual handlers calling the cart's methods. Pass it to the More button component.

Modify `ns-pos-more-button.vue` to accept `actions` prop instead of building them internally.

- [ ] **Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-cart.vue resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue
git commit -m "pos: restyle cart with Loyverse-inspired item rows, totals, and Charge+More buttons"
```

---

### Task 5: Redesign Payment Popup

**Files:**

- Modify: `resources/ts/popups/ns-pos-payment-popup.vue`

- [ ] **Replace template with compact dialog layout**

```vue
<template>
    <div
        id="ns-payment-popup"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="closePopup()"
        v-if="order"
    >
        <div
            class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden"
        >
            <div class="p-6">
                <!-- Total -->
                <div class="text-center mb-6">
                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                        {{ __("Total Due") }}
                    </div>
                    <div
                        class="text-3xl lg:text-4xl font-extrabold text-emerald-600"
                    >
                        ₱{{ order.total }}
                    </div>
                </div>

                <!-- Payment Method Tabs -->
                <div class="flex gap-2 mb-6">
                    <div
                        v-for="payment of paymentsType"
                        :key="payment.identifier"
                        @click="select(payment)"
                        :class="
                            activePayment &&
                            activePayment.identifier === payment.identifier
                                ? 'bg-emerald-600 text-white'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300'
                        "
                        class="flex-1 text-center py-3 rounded-lg font-semibold text-sm cursor-pointer transition-colors"
                    >
                        {{ payment.label }}
                    </div>
                </div>

                <!-- Quick Amounts (shown for cash payment) -->
                <div
                    v-if="
                        activePayment &&
                        activePayment.identifier === 'cash-payment'
                    "
                    class="mb-4"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {{ __("Quick Amount") }}
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <div
                            v-for="amount in quickAmounts"
                            :key="amount"
                            @click="setAmount(amount)"
                            :class="
                                selectedAmount === amount
                                    ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300'
                                    : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300'
                            "
                            class="px-4 py-2 border-2 rounded-lg font-bold text-sm cursor-pointer hover:border-emerald-300 transition-colors"
                        >
                            ₱{{ formatAmount(amount) }}
                        </div>
                    </div>
                </div>

                <!-- Custom Amount Input -->
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                        {{ __("Or enter amount") }}
                    </div>
                    <input
                        type="number"
                        v-model="customAmount"
                        @input="onCustomAmountInput"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                        placeholder="₱0.00"
                    />
                </div>

                <!-- Change Due -->
                <div
                    v-if="changeDue > 0"
                    class="flex justify-between items-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg mb-6"
                >
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{
                        __("Change Due")
                    }}</span>
                    <span class="text-xl font-extrabold text-emerald-600"
                        >₱{{ formatAmount(changeDue) }}</span
                    >
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button
                        @click="closePopup()"
                        class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                    >
                        {{ __("Cancel") }}
                    </button>
                    <button
                        @click="submitPayment()"
                        class="flex-[2] py-3 bg-emerald-600 text-white rounded-xl font-bold text-base cursor-pointer hover:bg-emerald-700 transition-colors"
                    >
                        {{ __("Charge") }} ₱{{ formatAmount(chargeAmount) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
```

- [ ] **Update script section** — Add data for quick amounts, change due, amount handling. Remove layaway-related methods (`submiAsUnpaid`). Add `quickAmounts` computed that generates suggested denominations (₱20, ₱50, ₱100, ₱200, Exact) based on total.

Key data additions:

```ts
data() {
    return {
        // ... existing data
        quickAmounts: [],
        selectedAmount: null,
        customAmount: 0,
        chargeAmount: 0,
        changeDue: 0,
    }
},
computed: {
    // ... existing computed
    quickAmounts() {
        const total = this.order.total;
        const suggestions = [];
        const denominations = [20, 50, 100, 200, 500, 1000];
        for (const denom of denominations) {
            if (denom > total) {
                suggestions.push(denom);
                if (suggestions.length >= 4) break;
            }
        }
        suggestions.push('exact');
        return suggestions;
    }
},
methods: {
    // ... existing methods
    setAmount(amount) {
        this.selectedAmount = amount;
        if (amount === 'exact') {
            this.chargeAmount = this.order.total;
            this.customAmount = 0;
        } else {
            this.chargeAmount = amount;
        }
        this.changeDue = Math.max(0, this.chargeAmount - this.order.total);
    },
    submitPayment() {
        // Add payment and submit
        if (this.chargeAmount > 0) {
            POS.addPayment({
                identifier: this.activePayment.identifier,
                value: this.chargeAmount,
            });
        }
        this.submitOrder();
    },
}
```

- [ ] **Remove layaway button and unpaid submission**

Delete the `submiAsUnpaid()` method entirely. Remove any template references to layaway or unpaid.

- [ ] **Commit**

```bash
git add resources/ts/popups/ns-pos-payment-popup.vue
git commit -m "pos: redesign payment popup as compact dialog with quick amounts, remove layaway"
```

---

### Task 6: Update Category Filter to Pill Chips

**Files:**

- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`

- [ ] **Change category dropdown to pill chips**

Find the category filter element (likely a `<select>` or dropdown). Replace with:

```html
<div class="flex gap-1.5 overflow-x-auto pb-1">
    <span
        @click="selectCategory('all')"
        :class="activeCategory === 'all' ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
        class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium cursor-pointer hover:bg-emerald-500 hover:text-white transition-colors"
    >
        {{ __( 'All Items' ) }}
    </span>
    <span
        v-for="category of categories"
        :key="category.id"
        @click="selectCategory(category)"
        :class="activeCategory === category ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'"
        class="flex-shrink-0 px-4 py-1.5 rounded-full text-sm font-medium cursor-pointer hover:bg-emerald-500 hover:text-white transition-colors"
    >
        {{ category.name }}
    </span>
</div>
```

- [ ] **Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-grid.vue
git commit -m "pos: change category filter from dropdown to pill chips"
```

---

### Task 7: Clean Up Top Header Bar

**Files:**

- Modify: `resources/ts/pos-init.ts` (where header buttons are registered)

- [ ] **Find header button registration**

Search for where header buttons like Reset, Dashboard, Register are registered in `pos-init.ts`. Remove or comment out the non-essential ones (Reset, Dashboard, Register). Keep: Customers, Order Type.

- [ ] **Commit**

```bash
git add resources/ts/pos-init.ts
git commit -m "pos: simplify top header bar to essential buttons only"
```

---

### Task 8: Update CSS for Both Themes

**Files:**

- Modify: `resources/css/light/_pos.css`
- Modify: `resources/css/dark/_pos.css`

- [ ] **Increase base font sizes in POS cart**

Add to `_pos.css`:

```css
/* Tablet-optimized POS typography */
#pos-cart .font-semibold.text-base {
    font-size: 1.05rem;
}
#pos-cart .product-item {
    font-size: 1rem;
}
#pos-cart .product-item .font-bold {
    font-size: 1.1rem;
}
#cart-products-summary .text-lg {
    font-size: 1.15rem;
}
#charge-button {
    font-size: 1.15rem;
}
```

For dark theme, ensure contrast is maintained.

- [ ] **Commit**

```bash
git add resources/css/light/_pos.css resources/css/dark/_pos.css
git commit -m "pos: increase font sizes for tablet readability"
```

---

### Task 9: Remove Unused Old Button Files

**Files:**

- Delete: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-pay-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-hold-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-discount-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-void-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-comment-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-taxes-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-coupons-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-settings-button.vue`
- Delete: `resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-quick-product-button.vue`

**Note:** Only delete these after verifying no other component references them. Search for imports before deleting.

- [ ] **Verify no other references**

Run: `rg "ns-pos-pay-button\|ns-pos-hold-button\|ns-pos-discount-button\|ns-pos-void-button" --include="*.ts" --include="*.vue"`

- [ ] **Delete unused button files**

```bash
rm resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-pay-button.vue
rm resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-hold-button.vue
rm resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-discount-button.vue
rm resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-void-button.vue
rm resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-comment-button.vue
rm resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-taxes-button.vue
rm resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-coupons-button.vue
rm resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-settings-button.vue
rm resources/ts/pages/dashboard/pos/cart-header-buttons/ns-pos-cart-quick-product-button.vue
```

- [ ] **Commit**

```bash
git add -A && git commit -m "pos: remove unused old button components"
```

---

### Task 10: End-to-End Testing

- [ ] **Start dev server**

Run: `npm run dev`

- [ ] **Open POS in browser** and verify:
    1. Cart panel is ~38% width, grid is ~62%
    2. Cart shows "Ticket" header with order type badge
    3. Item rows show name + inline qty + total (Loyverse-style)
    4. Tapping item opens quantity popup
    5. Delete icon removes item
    6. Totals section shows subtotal/discount/tax/total
    7. Bottom has Charge ₱XX button (green, prominent) + ⋮ button
    8. ⋮ menu opens dropdown with Hold/Discount/Void/Comments/Taxes/Coupons/Settings/Quick Product
    9. Tapping Charge opens compact payment dialog
    10. Payment dialog shows quick cash amounts
    11. Selecting quick amount shows change due
    12. Tapping "Charge" submits payment
    13. Category filter shows pill chips
    14. Top header has minimal buttons
    15. All fonts are larger and readable on tablet

- [ ] **Fix any issues found**

- [ ] **Commit any fixes**

```bash
git add -A && git commit -m "fix: address POS UI issues found during testing"
```
