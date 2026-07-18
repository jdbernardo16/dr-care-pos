<template>
    <div>
        <div class="shadow-lg w-[95vw] md:w-132 ns-box">
            <div class="border-b ns-box-header p-2 text-fontcolor flex justify-between items-center">
                <h3 class="font-semibold">{{ __( 'Open GCash Session' ) }}</h3>
                <div><ns-close-button @click="popup.close()"></ns-close-button></div>
            </div>
            <div class="p-4">
                <p class="text-secondary mb-4">{{ __( 'Enter the current GCash wallet balance to start tracking.' ) }}</p>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-secondary mb-1">{{ __( 'Opening Balance' ) }}</label>
                    <input
                        v-model="openingBalance"
                        type="number"
                        step="0.01"
                        min="0"
                        class="outline-none rounded border-2 border-input-edge bg-surface h-12 w-full px-3 text-lg"
                        :placeholder="__( '0.00' )"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <div class="ns-button default"><button class="px-4 py-2" @click="popup.close()">{{ __( 'Cancel' ) }}</button></div>
                    <div class="ns-button success"><button class="px-4 py-2" @click="submit" :disabled="submitting">{{ submitting ? __( 'Opening...' ) : __( 'Open Session' ) }}</button></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'ns-gcash-open-popup',
    props: [ 'popup' ],
    data() {
        return {
            openingBalance: 0,
            submitting: false,
        };
    },
    methods: {
        __,
        submit() {
            if ( this.openingBalance <= 0 ) {
                nsSnackBar.error( __( 'Please enter a valid opening balance.' ) );
                return;
            }

            this.submitting = true;
            nsHttpClient.post( `/api/gcash/open/${this.popup.params.register.id}`, {
                opening_balance: this.openingBalance,
            }).subscribe({
                next: ( result ) => {
                    nsSnackBar.success( result.message || __( 'GCash session opened.' ) );
                    this.popup.params.resolve?.();
                    this.popup.close();
                },
                error: ( err ) => {
                    nsSnackBar.error( err.message || __( 'Failed to open GCash session.' ) );
                    this.submitting = false;
                },
            });
        },
    },
};
</script>
