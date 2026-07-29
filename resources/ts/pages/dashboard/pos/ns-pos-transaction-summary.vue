<template>
    <div id="pos-transaction-summary" class="flex-auto flex flex-col">
        <div class="rounded shadow ns-tab-item flex-auto flex overflow-hidden">
            <div class="flex flex-auto flex-col overflow-hidden">
                <div class="flex items-center px-3 py-3 border-b border-box-edge bg-green-50">
                    <i class="las la-check-circle text-green-600 text-2xl mr-2"></i>
                    <span class="font-bold text-xl text-green-700">{{ __("Transaction Complete") }}</span>
                </div>

                <div class="overflow-y-auto flex-auto p-4 space-y-4">
                    <div>
                        <h4 class="font-bold text-fontcolor mb-2 text-base uppercase tracking-wide">{{ __("Items") }}</h4>
                        <div v-for="(product, index) of products" :key="index" class="flex justify-between py-2 text-base border-b border-box-edge last:border-b-0">
                            <span class="text-fontcolor flex-1 min-w-0 truncate">{{ product.name }} <span v-if="product.unit_name" class="text-fontcolor-soft">&mdash; {{ product.unit_name }}</span></span>
                            <span class="text-fontcolor-soft ml-2 flex-shrink-0">&times;{{ displayProductQuantity(product) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-box-edge pt-2"></div>

                    <div>
                        <h4 class="font-bold text-fontcolor mb-2 text-base uppercase tracking-wide">{{ __("Payment") }}</h4>
                        <div v-for="(payment, index) of payments" :key="index" class="flex justify-between py-2 text-base">
                            <span class="text-fontcolor font-medium">{{ getPaymentLabel(payment) }}</span>
                            <span class="font-bold">{{ nsCurrency(payment.value) }}</span>
                        </div>
                    </div>

                    <div v-if="changeDue > 0" class="flex justify-between py-2 text-base text-red-600 bg-red-50 px-3 rounded-lg">
                        <span class="font-semibold">{{ __("Change Due") }}</span>
                        <span class="font-bold">{{ nsCurrency(changeDue) }}</span>
                    </div>

                    <div class="border-t-2 border-fontcolor pt-3 flex justify-between font-bold text-lg">
                        <span>{{ __("Total Charged") }}</span>
                        <span class="text-primary">{{ nsCurrency(orderTotal) }}</span>
                    </div>
                </div>

                <div class="p-3 border-t border-box-edge flex gap-2">
                    <button @click="newSale" class="flex-1 py-3 bg-red-600 text-white rounded-xl font-bold cursor-pointer hover:bg-red-700 transition-colors flex items-center justify-center gap-2 h-16">
                        {{ __("New Sale") }}
                    </button>
                    <button @click="printReceipt" class="flex-1 py-3 bg-white text-black border-2 border-gray-300 rounded-xl font-bold cursor-pointer hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 h-16">
                        <i class="las la-print text-black text-2xl"></i>
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
            if (payment.label) return payment.label;
            return payment.identifier;
        },

        printReceipt() {
            POS.printOrderReceipt(this.order, "silent");
        },

        newSale() {
            POS.reset();
        },
    },
};
</script>
