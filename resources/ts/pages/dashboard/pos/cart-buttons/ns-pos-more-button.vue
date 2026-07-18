<template>
    <div class="relative flex-shrink-0" id="more-button">
        <div
            @click="toggleMenu($event)"
            class="flex items-center font-bold cursor-pointer justify-center rounded-lg bg-white text-fontcolor text-xl h-14 w-14 mx-1 border-2 border-box-edge hover:bg-gray-100"
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
                class="px-4 py-3 hover:bg-gray-100 cursor-pointer flex items-center gap-3 text-sm font-medium text-fontcolor border-b border-box-edge last:border-0"
            >
                <i :class="action.icon" class="text-lg text-fontcolor-soft"></i>
                <span>{{ action.label }}</span>
            </div>
        </div>
    </div>
</template>
<script lang="ts">
import { Popup } from "~/libraries/popup";
import nsPosDiscountPopupVue from "~/popups/ns-pos-discount-popup.vue";
import nsPosNotePopupVue from "~/popups/ns-pos-note-popup.vue";
import nsPosTaxPopupVue from "~/popups/ns-pos-tax-popup.vue";
import nsPosCouponsLoadPopupVue from "~/popups/ns-pos-coupons-load-popup.vue";
import nsPosOrderSettingsVue from "~/popups/ns-pos-order-settings.vue";
import nsPosQuickProductPopupVue from "~/popups/ns-pos-quick-product-popup.vue";
import nsPosConfirmPopup from "~/popups/ns-pos-confirm-popup.vue";
import nsPosOrderTypePopupVue from "~/popups/ns-pos-order-type-popup.vue";
import nsPosCustomerPopupVue from "~/popups/ns-pos-customer-select-popup.vue";
import nsPosShippingPopupVue from "~/popups/ns-pos-shipping-popup.vue";
import { nsSnackBar } from "~/bootstrap";

declare const POS, __;

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
                    action: () => {
                        const order = POS.order.getValue();
                        if (order.products.length === 0) {
                            return nsSnackBar.error(
                                __("Unable to hold an empty order."),
                            );
                        }
                        POS.holdOrder(order);
                    },
                });
                items.push({
                    label: __("Discount"),
                    icon: "las la-percent",
                    action: () => {
                        Popup.show(nsPosDiscountPopupVue, {
                            order: this.order,
                            type: "cart",
                        });
                    },
                });
                items.push({
                    label: __("Void"),
                    icon: "las la-trash",
                    action: () => {
                        Popup.show(nsPosConfirmPopup, {
                            title: __("Void Order"),
                            message: __(
                                "Would you like to void the entire order?",
                            ),
                            onAction: (action) => {
                                if (action) {
                                    POS.voidOrder();
                                }
                            },
                        });
                    },
                });
            }

            items.push({
                label: __("Comments"),
                icon: "las la-comment",
                action: () => {
                    Popup.show(nsPosNotePopupVue, { order: this.order });
                },
            });
            items.push({
                label: __("Taxes"),
                icon: "las la-balance-scale-left",
                action: () => {
                    Popup.show(nsPosTaxPopupVue, { order: this.order });
                },
            });
            items.push({
                label: __("Coupons"),
                icon: "las la-tags",
                action: () => {
                    Popup.show(nsPosCouponsLoadPopupVue, { order: this.order });
                },
            });
            items.push({
                label: __("Settings"),
                icon: "las la-tools",
                action: () => {
                    Popup.show(nsPosOrderSettingsVue, { order: this.order });
                },
            });
            items.push({
                label: __("Quick Product"),
                icon: "las la-plus",
                action: () => {
                    Popup.show(nsPosQuickProductPopupVue, {
                        order: this.order,
                    });
                },
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
    },
    mounted() {
        document.addEventListener("click", this.handleClickOutside);
    },
    unmounted() {
        document.removeEventListener("click", this.handleClickOutside);
    },
};
</script>
