<template>
    <div>
        <div class="shadow-lg w-[95vw] md:w-132 ns-box">
            <div class="border-b ns-box-header p-2 text-fontcolor flex justify-between items-center">
                <h3 class="font-semibold">{{ __( 'Close GCash Session' ) }}</h3>
                <div><ns-close-button @click="popup.close()"></ns-close-button></div>
            </div>
            <div class="p-4">
                <div class="mb-4 p-3 rounded-lg bg-surface border border-edge space-y-2">
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'Opening Balance' ) }}</span>
                        <span>{{ nsCurrency( session.opening_balance ) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'GCash Payments (orders)' ) }}</span>
                        <span>{{ nsCurrency( session.gcashPaymentsTotal || 0 ) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'Service Cash-In / Cash-Out' ) }}</span>
                        <span>{{ nsCurrency( (session.cashInTotal || 0) - (session.cashOutTotal || 0) ) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>{{ __( 'Total Fees Earned' ) }}</span>
                        <span>{{ nsCurrency( session.total_fees ) }}</span>
                    </div>
                    <div class="border-t border-edge pt-2 flex justify-between font-semibold">
                        <span>{{ __( 'Expected Balance' ) }}</span>
                        <span>{{ nsCurrency( systemBalance ) }}</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-secondary mb-1">{{ __( 'Actual GCash Wallet Balance' ) }}</label>
                    <input
                        v-model="closingBalance"
                        type="number"
                        step="0.01"
                        min="0"
                        class="outline-none rounded border-2 border-input-edge bg-surface h-12 w-full px-3 text-lg"
                        :placeholder="__( '0.00' )"
                    />
                    <p class="text-xs text-secondary mt-1">{{ __( 'Enter the actual GCash wallet balance as shown in the GCash app.' ) }}</p>
                </div>

                <div v-if="closingBalance !== null && closingBalance !== ''" class="mb-4 p-3 rounded-lg border"
                    :class="difference === 0 ? 'border-success-tertiary' : 'border-error-tertiary'"
                >
                    <div class="flex justify-between font-semibold">
                        <span>{{ __( 'Difference' ) }}</span>
                        <span>{{ nsCurrency( difference ) }}</span>
                    </div>
                    <p v-if="difference !== 0" class="text-xs text-secondary mt-1">{{ __( 'The difference may indicate unrecorded transactions.' ) }}</p>
                </div>

                <div class="flex justify-end gap-2">
                    <div class="ns-button default"><button class="px-4 py-2" @click="popup.close()">{{ __( 'Cancel' ) }}</button></div>
                    <div class="ns-button default"><button class="px-4 py-2" @click="submit" :disabled="submitting">{{ submitting ? __( 'Closing...' ) : __( 'Close Session' ) }}</button></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
import { nsCurrency } from '~/filters/currency';

export default {
    name: 'ns-gcash-close-popup',
    props: [ 'popup' ],
    data() {
        return {
            closingBalance: null,
            submitting: false,
        };
    },
    computed: {
        session() {
            return this.popup.params.session;
        },
        systemBalance() {
            const opening = Number( this.session.opening_balance ) || 0;
            const payments = Number( this.session.gcashPaymentsTotal ) || 0;
            const cashIn = Number( this.session.cashInTotal ) || 0;
            const cashOut = Number( this.session.cashOutTotal ) || 0;
            return opening + payments - cashIn + cashOut;
        },
        difference() {
            const closing = Number( this.closingBalance ) || 0;
            return closing - this.systemBalance;
        },
    },
    methods: {
        __,
        nsCurrency,
        submit() {
            if ( this.closingBalance === null || this.closingBalance === '' ) {
                nsSnackBar.error( __( 'Please enter the closing balance.' ) );
                return;
            }

            this.submitting = true;
            nsHttpClient.post( `/api/gcash/close/${this.session.id}`, {
                closing_balance: this.closingBalance,
            }).subscribe({
                next: ( result ) => {
                    nsSnackBar.success( result.message || __( 'GCash session closed.' ) );
                    this.popup.params.resolve?.();
                    this.popup.close();
                },
                error: ( err ) => {
                    nsSnackBar.error( err.message || __( 'Failed to close session.' ) );
                    this.submitting = false;
                },
            });
        },
    },
};
</script>
