<template>
    <div class="relative flex-shrink-0" id="more-button">
        <div
            @click="toggleMenu($event)"
            class="flex items-center font-bold cursor-pointer justify-center rounded-lg bg-white text-fontcolor text-2xl h-16 w-16 mx-1 border-2 border-box-edge hover:bg-gray-100"
        >
            <span>⋮</span>
        </div>
        <div
            v-if="showMenu"
            :style="menuStyle"
            class="fixed bg-white rounded-lg shadow-xl border border-box-edge py-1 z-[9999] max-h-80 overflow-y-auto min-w-[200px]"
        >
            <div
                @click="handleAction(action)"
                v-for="action of menuActions"
                :key="action.label"
                class="px-4 py-3 hover:bg-gray-100 cursor-pointer flex items-center gap-3 text-base font-medium text-fontcolor border-b border-box-edge last:border-0"
            >
                <i :class="action.icon" class="text-xl text-fontcolor-soft"></i>
                <span>{{ action.label }}</span>
            </div>
        </div>
    </div>
</template>
<script lang="ts">
import { Popup } from "~/libraries/popup";
import { nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";
import ActionPermissions from "~/libraries/action-permissions";

import nsPosDiscountPopupVue from "~/popups/ns-pos-discount-popup.vue";
import nsPosNotePopupVue from "~/popups/ns-pos-note-popup.vue";
import nsPosTaxPopupVue from "~/popups/ns-pos-tax-popup.vue";
import nsPosCouponsLoadPopupVue from "~/popups/ns-pos-coupons-load-popup.vue";
import nsPosOrderSettingsVue from "~/popups/ns-pos-order-settings.vue";
import nsPosQuickProductPopupVue from "~/popups/ns-pos-quick-product-popup.vue";
import nsPosConfirmPopup from "~/popups/ns-pos-confirm-popup.vue";
import nsPosHoldOrdersPopupVue from "~/popups/ns-pos-hold-orders-popup.vue";
import nsPosLoadingPopupVue from "~/popups/ns-pos-loading-popup.vue";

import { ProductsQueue } from "~/pages/dashboard/pos/queues/order/products-queue";
import { CustomerQueue } from "~/pages/dashboard/pos/queues/order/customer-queue";
import { TypeQueue } from "~/pages/dashboard/pos/queues/order/type-queue";

declare const POS, nsHooks;

export default {
    inheritAttrs: false,
    props: {
        order: Object,
    },
    data: () => ({
        showMenu: false,
        menuActions: [],
        menuPosition: {},
    }),
    computed: {
        menuStyle() {
            return this.menuPosition;
        },
    },
    methods: {
        __,
        toggleMenu(e) {
            this.showMenu = !this.showMenu;
            if (this.showMenu) {
                this.buildMenu();
                const rect = e.currentTarget.getBoundingClientRect();
                this.menuPosition = {
                    bottom: (window.innerHeight - rect.top + 8) + 'px',
                    left: Math.max(8, rect.left) + 'px',
                    minWidth: Math.max(200, rect.width) + 'px',
                };
            }
        },
        buildMenu() {
            const items = [];

            if (
                this.order &&
                this.order.products &&
                this.order.products.length > 0
            ) {
                items.push({
                    label: __("Hold"),
                    icon: "las la-pause",
                    action: () => this.holdOrder(),
                });
                items.push({
                    label: __("Discount"),
                    icon: "las la-percent",
                    action: () => this.openDiscountPopup(),
                });
                items.push({
                    label: __("Void"),
                    icon: "las la-trash",
                    action: () => this.voidOrder(),
                });
            }

            items.push({
                label: __("Comments"),
                icon: "las la-comment",
                action: () => this.openNotePopup(),
            });
            items.push({
                label: __("Taxes"),
                icon: "las la-balance-scale-left",
                action: () => this.selectTaxGroup(),
            });
            items.push({
                label: __("Coupons"),
                icon: "las la-tags",
                action: () => this.selectCoupon(),
            });
            items.push({
                label: __("Settings"),
                icon: "las la-tools",
                action: () => this.defineOrderSettings(),
            });
            items.push({
                label: __("Quick Product"),
                icon: "las la-plus",
                action: () => this.openAddQuickProduct(),
            });

            this.menuActions = items;
        },
        handleAction(action) {
            this.showMenu = false;
            if (action.action) {
                action.action();
            }
        },
        handleClickOutside(e) {
            if (this.$el && !this.$el.contains(e.target)) {
                this.showMenu = false;
            }
        },
        async holdOrder() {
            const order = POS.order.getValue();

            if (order.products.length === 0) {
                return nsSnackBar.error(
                    __("Unable to hold an empty order."),
                );
            }

            /**
             * We'll check if the user has the right to hold an order.
             */
            await ActionPermissions.canProceed('nexopos.cart.hold');

            if (order.payment_status !== 'hold' && order.payments.length > 0) {
                return nsSnackBar.error(
                    __(
                        "Unable to hold an order which payment status has been updated already.",
                    ),
                );
            }

            const queues = nsHooks.applyFilters('ns-hold-queue', [
                ProductsQueue,
                CustomerQueue,
                TypeQueue,
            ]);

            for (let index in queues) {
                try {
                    const promise = new queues[index](order);
                    await promise.run();
                } catch (exception) {
                    /**
                     * in case there is something broken
                     * on the promise, we just stop the queue.
                     */
                    return false;
                }
            }

            /**
             * overriding hold popup
             * This will be useful to inject custom
             * hold popup.
             */
            const popup = nsHooks.applyFilters(
                'ns-override-hold-popup',
                () => {
                    const promise = new Promise((resolve, reject) => {
                        Popup.show(nsPosHoldOrdersPopupVue, {
                            resolve,
                            reject,
                            order,
                        });
                    });

                    promise
                        .then((result: any) => {
                            order.title = result.title;
                            order.payment_status = 'hold';
                            POS.order.next(order);

                            const popup = Popup.show(nsPosLoadingPopupVue);

                            POS.submitOrder().then(
                                (result) => {
                                    popup.close();
                                    nsSnackBar.success(result.message);
                                },
                                (error) => {
                                    popup.close();
                                    nsSnackBar.error(error.message);
                                },
                            );
                        })
                        .catch((exception) => {
                            console.log(exception);
                        });
                },
            );

            popup();
        },
        async openDiscountPopup() {
            const settings = POS.settings.getValue();

            if (!settings.cart_discount) {
                return nsSnackBar.error(
                    __(`You're not allowed to add a discount on the cart.`),
                );
            }

            await ActionPermissions.canProceed('nexopos.cart.discount');

            try {
                const reference = this.order;
                const type = "cart";

                await new Promise((resolve, reject) => {
                    Popup.show(nsPosDiscountPopupVue, {
                        reference,
                        resolve,
                        reject,
                        type,
                        onSubmit(response) {
                            if (
                                response.discount_type === "flat" &&
                                response.discount > reference.total_price
                            ) {
                                return nsSnackBar.error(
                                    __(
                                        "The discount amount can't exceed the total price of the product.",
                                    ),
                                );
                            }

                            if (type === "product") {
                                POS.updateProduct(reference, response);
                            } else if (type === "cart") {
                                POS.updateCart(reference, response);
                            }
                        },
                    });
                });
            } catch (exception) {
                // the popup might just be closed...
            }
        },
        voidOrder() {
            Popup.show(nsPosConfirmPopup, {
                title: __("Void Order"),
                message: __("Would you like to void the entire order?"),
                onAction: (action) => {
                    if (action) {
                        POS.voidOrder();
                    }
                },
            });
        },
        async openNotePopup() {
            /**
             * We'll ensure the user has the right to add comments to an order.
             */
            await ActionPermissions.canProceed('nexopos.cart.comments');

            try {
                const response = await new Promise((resolve, reject) => {
                    const note = this.order.note;
                    const note_visibility = this.order.note_visibility;
                    Popup.show(nsPosNotePopupVue, {
                        resolve,
                        reject,
                        note,
                        note_visibility,
                    });
                });

                const order = { ...this.order, ...response };
                POS.order.next(order);
            } catch (exception) {
                if (exception !== false) {
                    nsSnackBar.error(exception.message);
                }
            }
        },
        async selectTaxGroup(activeTab = 'settings') {
            /**
             * We'll check if the user has the right to manage taxes.
             */
            await ActionPermissions.canProceed('nexopos.cart.taxes');

            try {
                const response = await new Promise((resolve, reject) => {
                    const taxes = this.order.taxes;
                    const tax_group_id = this.order.tax_group_id;
                    const tax_type = this.order.tax_type;
                    Popup.show(nsPosTaxPopupVue, {
                        resolve,
                        reject,
                        taxes,
                        tax_group_id,
                        tax_type,
                        activeTab,
                    });
                });

                const order = { ...this.order, ...response };

                POS.order.next(order);
                POS.refreshCart();
            } catch (exception) {
                // popup is closed... not needed to log or do anything else
            }
        },
        async selectCoupon() {
            /**
             * We'll check if the user has the right to manage coupons.
             */
            await ActionPermissions.canProceed('nexopos.cart.coupons');

            try {
                await new Promise((resolve, reject) => {
                    Popup.show(nsPosCouponsLoadPopupVue, { resolve, reject });
                });
            } catch (exception) {
                // something happened
            }
        },
        async defineOrderSettings() {
            const settings = POS.settings.getValue();

            if (!settings.edit_settings) {
                return nsSnackBar.error(
                    __("You're not allowed to edit the order settings."),
                );
            }

            /**
             * We'll check if the user has the right to define order settings.
             */
            await ActionPermissions.canProceed('defineOrderSettings');

            try {
                const response = await new Promise((resolve, reject) => {
                    Popup.show(nsPosOrderSettingsVue, {
                        resolve,
                        reject,
                        order: this.order,
                    });
                });

                /**
                 * We'll update the order
                 */
                POS.order.next({ ...this.order, ...response });
            } catch (exception) {
                // we shouldn't catch any exception here.
            }
        },
        async openAddQuickProduct() {
            /**
             * We'll check if the user has the right to add a quick product.
             */
            await ActionPermissions.canProceed('nexopos.cart.products');

            try {
                await new Promise((resolve, reject) => {
                    Popup.show(nsPosQuickProductPopupVue, { resolve, reject });
                });
            } catch (exception) {
                // ...
            }
        },
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
    },
    unmounted() {
        document.removeEventListener("click", this.handleClickOutside);
    },
};
</script>
