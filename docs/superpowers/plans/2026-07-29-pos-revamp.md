# POS UI Revamp — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rehaul the POS interface to match Loyverse-inspired layout and interaction patterns.

**Architecture:** Flip the layout (grid left, cart right); modify existing Vue components minimally; add two new components (Product Edit Modal, Transaction Summary); add swipe-to-delete gesture; replace auto-print with summary-first flow.

**Tech Stack:** Vue 3, Options API, TypeScript, Tailwind CSS v4, existing POS infrastructure (POS global object, observables)

---

### Task 1: Flip Layout Panels

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos.vue`

- [ ] **Step 1: Swap panel order and widths**

In `ns-pos.vue`, the current layout has cart (38%) on the left and grid (62%) on the right. Swap the order and keep the same widths — grid now gets 62% (left), cart gets 38% (right).

Switch the order of `v-if` blocks and their `:class` widths:

```diff
- <div :class="visibleSection === 'both' ? 'w-[38%]' : 'w-full'" class="flex overflow-hidden p-2" v-if="[ 'both', 'cart' ].includes( visibleSection )">
-     <ns-pos-cart></ns-pos-cart>
- </div>
- <div :class="visibleSection === 'both' ? 'w-[62%]' : 'w-full'" class="p-2 flex overflow-hidden" v-if="[ 'both', 'grid' ].includes( visibleSection )">
-     <ns-pos-grid></ns-pos-grid>
- </div>
+ <div :class="visibleSection === 'both' ? 'w-[62%]' : 'w-full'" class="p-2 flex overflow-hidden" v-if="[ 'both', 'grid' ].includes( visibleSection )">
+     <ns-pos-grid></ns-pos-grid>
+ </div>
+ <div :class="visibleSection === 'both' ? 'w-[38%]' : 'w-full'" class="flex overflow-hidden p-2" v-if="[ 'both', 'cart' ].includes( visibleSection )">
+     <ns-pos-cart></ns-pos-cart>
+ </div>
```

- [ ] **Step 2: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos.vue
git commit -m "feat(pos): flip layout — grid left (62%), cart right (38%)"
```

---

### Task 2: Dual-Mode Search Bar (Search + Barcode Scan)

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`

- [ ] **Step 1: Add scan mode toggle and state**

Add to `data()`:
```js
scanMode: false,
```

Add to template next to the barcode autofocus toggle button, update the search input section:

```diff
- <div class="border rounded flex overflow-hidden flex-auto">
-     <button ... class="outline-hidden w-10 h-10 border-r">
-         <i class="las la-barcode"></i>
-     </button>
-     <input ref="search" v-model="barcode" type="text" class="flex-auto outline-hidden px-2" />
- </div>
+ <div class="border rounded flex overflow-hidden flex-auto">
+     <button
+         :title="scanMode ? __('Scanning barcode...') : __('Search by product name')"
+         @click="scanMode = !scanMode"
+         :class="scanMode ? 'bg-primary text-white' : ''"
+         class="outline-hidden w-10 h-10 border-r cursor-pointer"
+     >
+         <i class="las" :class="scanMode ? 'la-camera' : 'la-search'"></i>
+     </button>
+     <input
+         ref="search"
+         v-model="barcode"
+         type="text"
+         :placeholder="scanMode ? __('Scan barcode...') : __('Search product...')"
+         class="flex-auto outline-hidden px-2"
+     />
+ </div>
```

- [ ] **Step 2: Update submitSearch to handle dual mode**

Modify the barcode watcher to use the correct endpoint based on `scanMode`:

```diff
- barcode() {
+ 'barcode,scanMode'() {
      if (this.options.ns_pos_force_autofocus) {
          clearTimeout(this.searchTimeout);
          this.searchTimeout = setTimeout(() => {
              this.submitSearch(this.barcode);
          }, 200);
      }
  },
```

Modify `submitSearch` method to branch on `scanMode`:

```diff
submitSearch(value) {
    if (value.length > 0) {
-       const url = nsHooks.applyFilters(
-           "ns-pos-submit-search-url",
-           `/api/products/search/using-barcode/${value}`,
-           value,
-       );
+       const url = this.scanMode
+           ? `/api/products/search/using-barcode/${value}`
+           : `/api/products/search/using-barcode/${value}`;
+       // Remove the old barcode endpoint path — use a single unified endpoint
+       // The backend /api/products/search/using-barcode/{value} handles both
+       // barcode matches AND product name/SKU matches
+       const url = nsHooks.applyFilters(
+           "ns-pos-submit-search-url",
+           `/api/products/search/using-barcode/${value}`,
+           value,
+       );

        nsHttpClient.get(url).subscribe({
            next: (result) => {
                this.barcode = "";
+               // If in search mode and result is an array (multiple matches),
+               // populate the grid with results. If single result, add to cart.
+               if (!this.scanMode && Array.isArray(result)) {
+                   this.products = result;
+                   return;
+               }
+               // Single product — add to cart
                const product = {};
                ...
```

Actually, looking more carefully at the existing API, `GET /api/products/search/using-barcode/${value}` returns a single product result. For text search, the `POST /api/products/search` endpoint returns an array. Let me handle both:

```js
submitSearch(value) {
    if (value.length <= 0) return;

    if (this.scanMode) {
        // Barcode mode — single product lookup
        const url = nsHooks.applyFilters(
            "ns-pos-submit-search-url",
            `/api/products/search/using-barcode/${value}`,
            value,
        );
        nsHttpClient.get(url).subscribe({
            next: (result) => {
                this.barcode = "";
                this.addScannedProductToCart(result);
            },
            error: (error) => {
                this.barcode = "";
                nsSnackBar.error(error.message);
            },
        });
    } else {
        // Search mode — product list lookup
        nsHttpClient.post("/api/products/search", {
            search: value,
            limit: 20,
        }).subscribe({
            next: (result) => {
                this.barcode = "";
                if (Array.isArray(result) && result.length > 0) {
                    // Populate grid with search results
                    this.products = result;
                    this.categories = [];
                } else {
                    nsSnackBar.info(__("No products match your search."));
                }
            },
            error: (error) => {
                this.barcode = "";
                nsSnackBar.error(error.message);
            },
        });
    }
},
```

Add the `addScannedProductToCart` method (extracted from existing `submitSearch` logic):

```js
addScannedProductToCart(result) {
    const product = {};

    const unitQuantity =
        result.unitQuantity ||
        result.product.selectedUnitQuantity ||
        result.product.unit_quantities?.[0];
    const unit = result.unit || unitQuantity?.unit;

    product.name = result.product.name;
    product.id = result.product.id;
    product.product_type = result.product.product_type;
    product.rate = result.product.rate;
    product.tax_group_id = result.product.tax_group_id;
    product.tax_type = result.product.tax_type;
    product.unit_id = unit.id;
    product.unit_price = unitQuantity.sale_price;
    product.price_gross = unitQuantity.sale_price_gross;
    product.price_net = unitQuantity.sale_price_net;
    product.unit_name = unit.name;

    // Handle scale barcodes (existing logic)
    if (result.scale) {
        const scaleData = result.scale;
        if (scaleData.type === "weight") {
            product.quantity = scaleData.value;
            const unitName = scaleData.unit?.name || "kg";
            nsSnackBar.info(
                __("Scale barcode detected: {weight} {unit}")
                    .replace("{weight}", scaleData.value.toFixed(3))
                    .replace("{unit}", unitName),
            );
        } else if (scaleData.type === "price") {
            const unitPrice =
                result.product.selectedUnitQuantity?.sale_price ||
                result.product.unit_quantities[0]?.sale_price || 0;
            if (unitPrice > 0) {
                product.quantity = scaleData.value / unitPrice;
            }
            nsSnackBar.info(
                __("Scale barcode detected: {price}")
                    .replace("{price}", this.nsCurrency(scaleData.value)),
            );
        }
    }

    POS.addToCart(product);
},
```

- [ ] **Step 3: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-grid.vue
git commit -m "feat(pos): add dual-mode search bar — search by name or barcode scan"
```

---

### Task 3: Direct Add-to-Cart from Grid Tiles

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`

- [ ] **Step 1: Remove quantity prompt on product tile click**

The current `@click` on product tiles calls `addToTheCart(product)`. This calls `POS.addToCart(product)`. The quantity prompt (`ProductQuantityPromise`) is fired inside the `addQueue` in `pos-init.ts`. We need to bypass it when adding from the grid.

Change the product tile click to pass a flag or call a modified add. The simplest approach: ensure `POS.processingAddQueue = true` is set when tiles are clicked so that `ProductQuantityPromise.run()` skips the popup (see `product-quantity.ts:22`).

Check `pos-init.ts` to see how `addQueue` works. If it already sets `POS.processingAddQueue = true`, then grid taps already bypass quantity. If not, modify `addToTheCart`:

```js
addToTheCart(product) {
    if (product.unit_quantities && product.unit_quantities.length > 1) {
        // Multi-unit: show unit selector first (existing behavior)
        POS.addToCart(product);
    } else {
        // Single unit: add directly with quantity 1
        POS.processingAddQueue = true;
        POS.addToCart(product);
        POS.processingAddQueue = false;
    }
},
```

- [ ] **Step 2: Remove quantity and price inquiry popup references from grid component**

Remove unused imports in `ns-pos-grid.vue`:
```diff
- import nsPosSearchProductVue from "~/popups/ns-pos-search-product.vue";
```

(Keep the import if `openSearchPopup()` is still used — it is, for the search button.)

- [ ] **Step 3: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-grid.vue
git commit -m "feat(pos): add product directly to cart from grid tap — no quantity prompt"
```

---

### Task 4: Product Edit Modal

**Files:**
- Create: `resources/ts/pages/dashboard/pos/ns-pos-product-edit-modal.vue`

- [ ] **Step 1: Create the Product Edit Modal component**

```vue
<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="close">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-bold text-fontcolor">{{ product.name }}</h3>
                    <button @click="close" class="text-gray-400 hover:text-gray-600 text-2xl cursor-pointer">&times;</button>
                </div>
                <p class="text-fontcolor-soft mb-4">&mdash; {{ product.unit_name }}</p>

                <!-- Quantity -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-fontcolor mb-2">{{ __("Quantity") }}</label>
                    <div class="flex items-center gap-2">
                        <button @click="decrementQuantity" :disabled="editQuantity <= 1" class="w-10 h-10 rounded-lg border border-gray-300 flex items-center justify-center text-xl font-bold cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:bg-gray-100">
                            -
                        </button>
                        <input type="number" v-model.number="editQuantity" min="1" class="flex-1 text-center border border-gray-300 rounded-lg px-3 py-2 text-lg font-semibold outline-hidden focus:ring-2 focus:ring-primary" />
                        <button @click="incrementQuantity" class="w-10 h-10 rounded-lg border border-gray-300 flex items-center justify-center text-xl font-bold cursor-pointer hover:bg-gray-100">
                            +
                        </button>
                    </div>
                </div>

                <!-- Unit Price -->
                <div class="mb-4" v-if="unitPriceEditable">
                    <label class="block text-sm font-semibold text-fontcolor mb-2">{{ __("Unit Price") }}</label>
                    <input type="number" v-model.number="editUnitPrice" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-lg font-semibold outline-hidden focus:ring-2 focus:ring-primary" />
                </div>
                <div class="mb-4" v-else>
                    <label class="block text-sm font-semibold text-fontcolor mb-2">{{ __("Unit Price") }}</label>
                    <p class="text-lg font-semibold">{{ nsCurrency(product.unit_price) }}</p>
                </div>

                <!-- Discount -->
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-fontcolor mb-2">{{ __("Discount") }}</label>
                    <div class="flex gap-2">
                        <select v-model="discountType" class="border border-gray-300 rounded-lg px-3 py-2 outline-hidden">
                            <option value="percentage">{{ __("Percentage") }}</option>
                            <option value="flat">{{ __("Flat") }}</option>
                        </select>
                        <input type="number" v-model.number="discountValue" min="0" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 outline-hidden focus:ring-2 focus:ring-primary" :placeholder="discountType === 'percentage' ? '%' : nsRawCurrency(0)" />
                    </div>
                </div>

                <!-- Subtotal -->
                <div class="flex justify-between items-center py-3 border-t border-gray-200 mt-4">
                    <span class="font-bold text-fontcolor text-lg">{{ __("Subtotal") }}</span>
                    <span class="font-bold text-primary text-xl">{{ nsCurrency(subtotal) }}</span>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 mt-4">
                    <button @click="close" class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold cursor-pointer hover:bg-gray-200 transition-colors">
                        {{ __("Cancel") }}
                    </button>
                    <button @click="save" class="flex-[2] py-3 bg-primary text-white rounded-xl font-bold cursor-pointer hover:bg-secondary transition-colors">
                        {{ __("Save") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";
import { nsCurrency, nsRawCurrency } from "~/filters/currency";

declare const POS;

export default {
    name: "ns-pos-product-edit-modal",
    props: {
        product: { type: Object, required: true },
        index: { type: Number, required: true },
        settings: { type: Object, default: () => ({}) },
        popup: { type: Object, required: true },
    },
    data() {
        return {
            editQuantity: this.product.quantity || 1,
            editUnitPrice: this.product.unit_price || 0,
            discountType: this.product.discount_type || "percentage",
            discountValue: this.product.discount_percentage || this.product.discount || 0,
        };
    },
    computed: {
        unitPriceEditable() {
            return this.settings.unit_price_editable && this.product.product_type !== "dynamic";
        },
        subtotal() {
            const lineTotal = this.editQuantity * this.editUnitPrice;
            if (this.discountType === "percentage") {
                return lineTotal - (lineTotal * this.discountValue / 100);
            } else {
                return lineTotal - this.discountValue;
            }
        },
    },
    methods: {
        __,
        nsCurrency,
        nsRawCurrency,

        decrementQuantity() {
            if (this.editQuantity > 1) this.editQuantity--;
        },

        incrementQuantity() {
            this.editQuantity++;
        },

        close() {
            this.popup.close();
        },

        save() {
            const update = {
                quantity: this.editQuantity,
                unit_price: this.editUnitPrice,
                discount_type: this.discountType,
                discount_percentage: this.discountType === "percentage" ? this.discountValue : 0,
                discount: this.discountType === "flat" ? this.discountValue : 0,
                mode: "custom",
            };

            POS.updateProduct(this.product, update, this.index);
            POS.recomputeProducts(POS.products.getValue());
            POS.refreshCart();

            nsSnackBar.success(__("Product updated."));
            this.close();
        },
    },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-product-edit-modal.vue
git commit -m "feat(pos): add product edit modal with quantity, price, and discount controls"
```

---

### Task 5: Wire Product Edit Modal Into Cart

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-cart.vue`

- [ ] **Step 1: Import the edit modal**

```diff
  import nsPosProductPricePopupVue from '~/popups/ns-pos-product-price-popup.vue';
  import nsPosQuickProductPopupVue from '~/popups/ns-pos-quick-product-popup.vue';
+ import nsPosProductEditModalVue from './ns-pos-product-edit-modal.vue';
```

- [ ] **Step 2: Add openEditModal method**

```js
async openEditModal(product, index) {
    await ActionPermissions.canProceed('nexopos.cart.products');

    try {
        await new Promise((resolve, reject) => {
            Popup.show(nsPosProductEditModalVue, {
                product: Object.assign({}, product),
                index,
                settings: this.settings,
                resolve,
                reject,
            });
        });
    } catch (exception) {
        // popup closed
    }
},
```

- [ ] **Step 3: Wire tap on line item to open modal**

Update the product item template to make the whole line item row clickable:

```diff
- <div :product-index="index" :key="product.barcode" class="product-item px-3 py-3 border-b border-box-edge" v-for="(product, index) of products">
+ <div :product-index="index" :key="product.barcode" class="product-item px-3 py-3 border-b border-box-edge cursor-pointer hover:bg-box-elevation-background" v-for="(product, index) of products" @click="openEditModal(product, index)">
```

- [ ] **Step 4: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-cart.vue
git commit -m "feat(pos): wire product edit modal — tap line item to edit quantity/price/discount"
```

---

### Task 6: Swipe-to-Delete on Cart Line Items

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-cart.vue`

- [ ] **Step 1: Add swipe gesture data and methods**

Add to `data()`:
```js
swipedIndex: null,
touchStartX: 0,
touchCurrentX: 0,
isSwiping: false,
```

Add swipe gesture methods:
```js
onTouchStart(event, index) {
    this.touchStartX = event.touchstartX || event.touches[0].clientX;
    this.swipedIndex = index;
    this.isSwiping = true;
},

onTouchMove(event) {
    if (!this.isSwiping) return;
    this.touchCurrentX = event.touches[0].clientX;
    const diff = this.touchStartX - this.touchCurrentX;
    // Only show delete if swiped left more than 60px
    if (diff > 60) {
        this.swipedIndex = this.swipedIndex;
    } else if (diff < -30) {
        this.swipedIndex = null;
    }
},

onTouchEnd(event) {
    this.isSwiping = false;
    const diff = this.touchStartX - this.touchCurrentX;
    if (diff > 80) {
        // Reveal delete — handled by swipedIndex being set
    } else {
        this.swipedIndex = null;
    }
},
```

- [ ] **Step 2: Update template to show delete on swipe**

```diff
- <div :product-index="index" :key="product.barcode" class="product-item px-3 py-3 border-b border-box-edge cursor-pointer hover:bg-box-elevation-background" v-for="(product, index) of products" @click="openEditModal(product, index)">
-     <div class="flex justify-between items-start">
-         <div class="flex-1 min-w-0">
-             ...
-         </div>
-         <div class="text-lg lg:text-xl font-bold text-fontcolor flex-shrink-0 ml-4">
-             {{ nsCurrency(product.total_price) }}
-         </div>
-     </div>
- </div>
+ <div class="relative overflow-hidden" v-for="(product, index) of products" :key="product.barcode || index">
+     <div
+         class="product-item px-3 py-3 border-b border-box-edge cursor-pointer hover:bg-box-elevation-background transition-transform duration-200"
+         :class="swipedIndex === index ? '-translate-x-16' : ''"
+         @click="openEditModal(product, index)"
+         @touchstart="onTouchStart($event, index)"
+         @touchmove="onTouchMove($event)"
+         @touchend="onTouchEnd($event)"
+     >
+         <div class="flex justify-between items-start">
+             <div class="flex-1 min-w-0">
+                 <div class="font-semibold text-lg lg:text-xl text-fontcolor break-words leading-tight">
+                     {{ product.name }}
+                     <span class="text-fontcolor-soft font-normal text-base whitespace-nowrap" v-if="product.unit_name">&mdash; {{ product.unit_name }}</span>
+                 </div>
+                 <div class="flex items-center gap-3 mt-1 text-base lg:text-lg text-fontcolor">
+                     <span class="flex items-center gap-1">
+                         <span class="text-fontcolor-soft">&times;</span>
+                         <span class="font-semibold text-fontcolor bg-input-background px-2 py-0.5 rounded">{{ displayProductQuantity(product) }}</span>
+                         <span class="text-fontcolor-soft">@</span>
+                         <span>{{ nsCurrency(product.unit_price) }}</span>
+                     </span>
+                 </div>
+                 <div class="flex flex-wrap gap-2 mt-1 text-base">
+                     <a class="text-info-secondary cursor-pointer border-b border-dashed border-info-secondary">{{ __("Price") }}: {{ nsCurrency(product.unit_price) }}</a>
+                     <a v-if="allowQuantityModification(product)" class="text-info-secondary cursor-pointer border-b border-dashed border-info-secondary">{{ __("Discount") }} <span v-if="product.discount_type === 'percentage'">{{ product.discount_percentage }}%</span>: {{ nsCurrency(product.discount) }}</a>
+                 </div>
+             </div>
+             <div class="text-lg lg:text-xl font-bold text-fontcolor flex-shrink-0 ml-4">
+                 {{ nsCurrency(product.total_price) }}
+             </div>
+         </div>
+     </div>
+     <div class="absolute right-0 top-0 h-full flex items-center transition-opacity duration-200" :class="swipedIndex === index ? 'opacity-100' : 'opacity-0 pointer-events-none'">
+         <button @click="removeUsingIndex(index)" class="h-full bg-error text-white px-4 flex items-center justify-center text-xl cursor-pointer">
+             <i class="las la-trash-alt"></i>
+         </button>
+     </div>
+ </div>
```

Remove the old inline delete button from the product item area (the `button` with `removeUsingIndex`).

- [ ] **Step 3: Remove confirmation popup from removeUsingIndex (or keep it)**

The current `removeUsingIndex` shows a confirm popup. Keep it for safety:

```js
async removeUsingIndex(index) {
    await ActionPermissions.canProceed('nexopos.cart.product-delete');

    Popup.show(PosConfirmPopup, {
        title: __('Delete Product'),
        message: __('Remove this item from the ticket?'),
        onAction(action) {
            if (action) {
                POS.removeProductUsingIndex(index);
            }
        },
    });
},
```

- [ ] **Step 4: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-cart.vue
git commit -m "feat(pos): add swipe-to-delete gesture on cart line items"
```

---

### Task 7: Transaction Summary Component

**Files:**
- Create: `resources/ts/pages/dashboard/pos/ns-pos-transaction-summary.vue`

- [ ] **Step 1: Create the Transaction Summary component**

```vue
<template>
    <div id="pos-transaction-summary" class="flex-auto flex flex-col">
        <div class="rounded shadow ns-tab-item flex-auto flex overflow-hidden">
            <div class="flex flex-auto flex-col overflow-hidden">
                <div class="flex items-center px-3 py-3 border-b border-box-edge bg-green-50">
                    <i class="las la-check-circle text-green-600 text-2xl mr-2"></i>
                    <span class="font-bold text-xl text-green-700">{{ __("Transaction Complete") }}</span>
                </div>

                <div class="overflow-y-auto flex-auto p-3">
                    <h4 class="font-semibold text-fontcolor mb-2">{{ __("Items") }}</h4>
                    <div v-for="product of products" :key="product.barcode" class="flex justify-between py-1 text-sm">
                        <span class="text-fontcolor">{{ product.name }} <span v-if="product.unit_name">&mdash; {{ product.unit_name }}</span></span>
                        <span class="text-fontcolor-soft">&times;{{ displayProductQuantity(product) }}</span>
                    </div>

                    <div class="border-t border-box-edge my-3"></div>

                    <h4 class="font-semibold text-fontcolor mb-2">{{ __("Payment") }}</h4>
                    <div v-for="payment of payments" :key="payment.identifier" class="flex justify-between py-1 text-sm">
                        <span class="text-fontcolor">{{ getPaymentLabel(payment) }}</span>
                        <span class="font-semibold">{{ nsCurrency(payment.value) }}</span>
                    </div>

                    <div v-if="changeDue > 0" class="flex justify-between py-1 text-sm text-red-600">
                        <span>{{ __("Change Due") }}</span>
                        <span class="font-semibold">{{ nsCurrency(changeDue) }}</span>
                    </div>

                    <div class="border-t-2 border-fontcolor mt-3 pt-3 flex justify-between font-bold text-lg">
                        <span>{{ __("Total Charged") }}</span>
                        <span>{{ nsCurrency(orderTotal) }}</span>
                    </div>
                </div>

                <div class="p-3 border-t border-box-edge flex gap-2">
                    <button @click="printReceipt" class="flex-1 py-3 bg-primary text-white rounded-xl font-bold cursor-pointer hover:bg-secondary transition-colors flex items-center justify-center gap-2">
                        <i class="las la-print text-xl"></i>
                        {{ __("Print Receipt") }}
                    </button>
                    <button @click="newSale" class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold cursor-pointer hover:bg-gray-200 transition-colors">
                        {{ __("New Sale") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { __ } from "~/libraries/lang";
import { nsCurrency } from "~/filters/currency";

declare const POS;

export default {
    name: "ns-pos-transaction-summary",
    props: {
        products: { type: Array, default: () => [] },
        payments: { type: Array, default: () => [] },
        orderTotal: { type: Number, default: 0 },
        changeDue: { type: Number, default: 0 },
        order: { type: Object, default: () => ({}) },
    },
    methods: {
        __,
        nsCurrency,

        displayProductQuantity(product) {
            return product.quantity || 1;
        },

        getPaymentLabel(payment) {
            const foundPayment = this.payments.filter(
                (p) => p.identifier === payment.identifier,
            )[0];
            return foundPayment ? foundPayment.label : payment.identifier;
        },

        printReceipt() {
            POS.printOrderReceipt(this.order, "silent");
        },

        newSale() {
            POS.reset();
            POS.visibleSection.next('both');
        },
    },
};
</script>
```

- [ ] **Step 2: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-transaction-summary.vue
git commit -m "feat(pos): add transaction summary component — post-payment view with print and new sale"
```

---

### Task 8: Update Payment Flow — Show Summary Instead of Auto-Print

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue`
- Modify: `resources/ts/pages/dashboard/pos/queues/order/payment-queue.ts`
- Modify: `resources/ts/popups/ns-pos-payment-popup.vue`

- [ ] **Step 1: Update payment-queue.ts to support post-payment callback**

```diff
  import { Queue } from "~/contracts/queue";
  import { Popup } from "~/libraries/popup";

  import { default as nsPaymentPopup } from '~/popups/ns-pos-payment-popup.vue';

  export class PaymentQueue implements Queue {
-     constructor( private order ) {}
+     constructor(private order) {}

-     run() {
+     run(onSuccess?: (result: any) => void) {
          return new Promise((resolve, reject) => {
-             Popup.show(nsPaymentPopup, { resolve, reject, order: this.order });
+             Popup.show(nsPaymentPopup, { resolve, reject, order: this.order, onSuccess });
          })
      }
  }
```

- [ ] **Step 2: Update charge button to pass callback**

```diff
  async payOrder() {
-     POS.runPaymentQueue();
+     POS.runPaymentQueue((result) => {
+         // Switch to transaction summary view
+         POS.showTransactionSummary(result.data.order);
+     });
  },
```

- [ ] **Step 3: Update pos-init.ts — modify runPaymentQueue to accept callback**

Find the `runPaymentQueue` method in `pos-init.ts` (line ~2380) and update:

```diff
- async runPaymentQueue() {
-     const order = POS.order.getValue();
-     const queue = new PaymentQueue(order);
-     return await queue.run();
+ async runPaymentQueue(onSuccess?: (result: any) => void) {
+     const order = POS.order.getValue();
+     const queue = new PaymentQueue(order);
+     return await queue.run(onSuccess);
  }
```

- [ ] **Step 4: Update payment popup to call onSuccess instead of printing**

In `ns-pos-payment-popup.vue`, modify the `submitOrder` method:

```diff
- props: ["popup"],
+ props: ["popup", "onSuccess"],
```

```diff
  submitOrder(data = {}) {
      const popup = Popup.show(nsPosLoadingPopupVue);

      try {
          const order = { ...POS.order.getValue(), ...data };

          POS.submitOrder(order).then(
              (result) => {
                  popup.close();
                  nsSnackBar.success(result.message);
-
-                 POS.printOrderReceipt(result.data.order, "silent");
-
-                 this.popup.close();
+
+                 if (typeof this.onSuccess === 'function') {
+                     this.onSuccess(result);
+                 }
+
+                 this.popup.close();
              },
              (error) => {
                  popup.close();
                  nsSnackBar.error(error.message);
              },
          );
```

- [ ] **Step 5: Add showTransactionSummary to POS in pos-init.ts**

In `pos-init.ts`, add:

```js
/**
 * Replace the cart panel with the transaction summary
 */
showTransactionSummary(order) {
    this.visibleSection.next('summary');
    // Store the completed order for the summary component to access
    this.lastCompletedOrder.next(order);
},
```

Add to the POS observables object initialization:
```js
lastCompletedOrder: new BehaviorSubject(null),
```

Update `ns-pos-cart.vue` to listen for `visibleSection === 'summary'` and render the transaction summary instead of the cart.

In `ns-pos.vue`, add the transaction summary component to the cart panel slot:

```diff
- <div :class="visibleSection === 'both' ? 'w-[38%]' : 'w-full'" class="flex overflow-hidden p-2" v-if="[ 'both', 'cart' ].includes( visibleSection )">
-     <ns-pos-cart></ns-pos-cart>
- </div>
- <div :class="visibleSection === 'both' ? 'w-[62%]' : 'w-full'" class="p-2 flex overflow-hidden" v-if="[ 'both', 'grid' ].includes( visibleSection )">
-     <ns-pos-grid></ns-pos-grid>
- </div>
+ <div :class="visibleSection === 'both' || visibleSection === 'summary' ? 'w-[62%]' : 'w-full'" class="p-2 flex overflow-hidden" v-if="[ 'both', 'grid', 'summary' ].includes( visibleSection )">
+     <ns-pos-grid></ns-pos-grid>
+ </div>
+ <div :class="visibleSection === 'both' || visibleSection === 'summary' ? 'w-[38%]' : 'w-full'" class="flex overflow-hidden p-2" v-if="[ 'both', 'cart', 'summary' ].includes( visibleSection )">
+     <ns-pos-cart v-if="visibleSection !== 'summary'"></ns-pos-cart>
+     <ns-pos-transaction-summary
+         v-else
+         :products="lastCompletedOrder?.products || []"
+         :payments="lastCompletedOrder?.payments || []"
+         :order-total="lastCompletedOrder?.total || 0"
+         :change-due="lastCompletedOrder?.change_due || 0"
+         :order="lastCompletedOrder"
+     ></ns-pos-transaction-summary>
+ </div>
```

Also add the import:
```diff
  import nsPosCart from './ns-pos-cart.vue';
  import nsPosGrid from './ns-pos-grid.vue';
+ import nsPosTransactionSummary from './ns-pos-transaction-summary.vue';
```

And register it in components:
```diff
  components: {
      nsPosCart,
      nsPosGrid,
+     nsPosTransactionSummary,
  }
```

Update visibleSection subscriber to include 'summary' in allowed sections:

```diff
- this.visibleSectionSubscriber = POS.visibleSection.subscribe(section => {
+ this.visibleSectionSubscriber = POS.visibleSection.subscribe((section) => {
+     if (section === 'summary') {
+         this.lastCompletedOrder = POS.lastCompletedOrder.getValue();
+     }
      this.visibleSection = section;
  });
```

Add to `data()`:
```js
lastCompletedOrder: null,
```

- [ ] **Step 6: Commit**

```bash
git add resources/ts/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue \
       resources/ts/pages/dashboard/pos/queues/order/payment-queue.ts \
       resources/ts/popups/ns-pos-payment-popup.vue \
       resources/ts/pos-init.ts \
       resources/ts/pages/dashboard/pos/ns-pos.vue
git commit -m "feat(pos): replace auto-print with transaction summary after charge"
```

---

### Task 9: Cleanup — Remove Deprecated Quantity Prompt

**Files:**
- Modify: `resources/ts/pages/dashboard/pos/ns-pos-grid.vue`

- [ ] **Step 1: Remove unused quantity-related references from grid**

Remove the merge button section if `posToggleMerge` is deprecated. Keep existing buttons but clean up.

- [ ] **Step 2: Ensure grid resets search after adding to cart**

When user taps a product tile, after adding to cart, reset search state:

```js
addToTheCart(product) {
    POS.addToCart(product);
    // Clear search if we're in search-results mode
    if (this.products.length > 0 && this.categories.length === 0) {
        this.loadCategories(this.currentCategory);
    }
},
```

- [ ] **Step 3: Commit**

```bash
git add resources/ts/pages/dashboard/pos/ns-pos-grid.vue
git commit -m "chore(pos): cleanup grid — reset to category view after adding searched product"
```

---

### Task 10: Verify the Build

- [ ] **Step 1: Run the dev build**

```bash
npm run dev
```

- [ ] **Step 2: Fix any TypeScript or compilation errors**

- [ ] **Step 3: Run lint**

```bash
npm run lint
```
