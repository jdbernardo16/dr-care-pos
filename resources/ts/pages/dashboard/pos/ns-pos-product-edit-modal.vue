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
                        <input type="number" v-model.number="discountValue" min="0" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 outline-hidden focus:ring-2 focus:ring-primary" :placeholder="discountType === 'percentage' ? '%' : '0.00'" />
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
import { nsCurrency } from "~/filters/currency";

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
