<template>
    <div>
        <div class="shadow-lg w-[95vw] md:w-132 ns-box">
            <div class="border-b ns-box-header p-2 text-fontcolor flex justify-between items-center">
                <h3 class="font-semibold">{{ __( 'GCash Cash In' ) }}</h3>
                <div><ns-close-button @click="popup.close()"></ns-close-button></div>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-secondary mb-1">{{ __( 'Customer Amount (GCash)' ) }}</label>
                    <input
                        v-model="amount"
                        type="number"
                        step="1"
                        min="1"
                        class="outline-none rounded border-2 border-input-edge bg-surface h-12 w-full px-3 text-lg"
                        :placeholder="__( '0' )"
                        @input="computeFee"
                    />
                    <p class="text-xs text-secondary mt-1">{{ __( 'The amount the customer wants to send via GCash.' ) }}</p>
                </div>

                <div v-if="amount > 0" class="mb-4 p-3 rounded-lg bg-surface border border-edge space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'Service Fee' ) }}</span>
                        <span class="font-semibold">+ {{ nsCurrency( computedFee ) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'Principal' ) }}</span>
                        <span>{{ nsCurrency( Number( amount ) ) }}</span>
                    </div>
                    <div class="border-t border-edge pt-2 flex justify-between font-bold">
                        <span>{{ __( 'Customer Pays (Cash)' ) }}</span>
                        <span>{{ nsCurrency( Number( amount ) + computedFee ) }}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-secondary mb-1">{{ __( 'Customer Phone (optional)' ) }}</label>
                    <input
                        v-model="customerPhone"
                        type="text"
                        class="outline-none rounded border-2 border-input-edge bg-surface h-10 w-full px-3"
                        :placeholder="__( '0917xxxxxxx' )"
                    />
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-secondary mb-1">{{ __( 'Reference No. (optional)' ) }}</label>
                    <input
                        v-model="referenceNo"
                        type="text"
                        class="outline-none rounded border-2 border-input-edge bg-surface h-10 w-full px-3"
                        :placeholder="__( 'GCash reference number' )"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <div class="ns-button default"><button class="px-4 py-2" @click="popup.close()">{{ __( 'Cancel' ) }}</button></div>
                    <div class="ns-button success"><button class="px-4 py-2" @click="submit" :disabled="submitting || !amount">{{ submitting ? __( 'Processing...' ) : __( 'Confirm Cash In' ) }}</button></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
import { nsCurrency } from '~/filters/currency';

const FEE_PER_BLOCK = 10;
const BLOCK_SIZE = 500;

export default {
    name: 'ns-gcash-cashin-popup',
    props: [ 'popup' ],
    data() {
        return {
            amount: 0,
            computedFee: 0,
            customerPhone: '',
            referenceNo: '',
            submitting: false,
        };
    },
    methods: {
        __,
        nsCurrency,
        computeFee() {
            const amt = Number( this.amount );
            this.computedFee = amt > 0 ? Math.ceil( amt / BLOCK_SIZE ) * FEE_PER_BLOCK : 0;
        },
        submit() {
            if ( !this.amount || Number( this.amount ) <= 0 ) {
                nsSnackBar.error( __( 'Please enter a valid amount.' ) );
                return;
            }

            this.submitting = true;
            nsHttpClient.post( `/api/gcash/cash-in/${this.popup.params.session.id}`, {
                amount: this.amount,
                customer_phone: this.customerPhone || null,
                reference_no: this.referenceNo || null,
            }).subscribe({
                next: ( result ) => {
                    nsSnackBar.success( result.message || __( 'Cash-in processed.' ) );
                    this.popup.params.resolve?.();
                    this.popup.close();
                },
                error: ( err ) => {
                    nsSnackBar.error( err.message || __( 'Failed to process cash-in.' ) );
                    this.submitting = false;
                },
            });
        },
    },
};
</script>
