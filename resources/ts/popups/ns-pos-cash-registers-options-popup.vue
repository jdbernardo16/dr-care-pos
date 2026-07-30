<script>
import popupCloser from '~/libraries/popup-closer';
import nsPosCashRegistersActionPopupVue from './ns-pos-cash-registers-action-popup.vue';
import nsPosCashRegistersHistoryVue from './ns-pos-cash-registers-history-popup.vue';
import popupResolver from '~/libraries/popup-resolver';
import { __ } from '~/libraries/lang';
import { nsCurrency } from '~/filters/currency';
import { nsSnackBar } from '~/bootstrap';

export default {
    props: [ 'popup' ],
    mounted() {
        this.settingsSubscriber     =   POS.settings.subscribe( settings => {
            this.settings   =   settings;
        });

        this.popupCloser();

        this.loadRegisterSummary();
    },
    beforeDestroy() {
        this.settingsSubscriber.unsubscribe();
    },
    data() {
        return {
            settings: null,
            settingsSubscriber: null,
            register: {},
            sessionSummary: null,
            loadingSummary: false,
        }
    },
    methods: {
        __,
        nsCurrency,
        popupResolver,
        popupCloser,

        loadRegisterSummary() {
            if ( this.settings.register === undefined ) {
                setTimeout( () => {
                    this.popup.close();
                }, 500 );
                
                return nsSnackBar.error( __( 'The register is not yet loaded.' ) );
            }

            this.loadingSummary  =   true;

            nsHttpClient.get( `/api/cash-registers/${this.settings.register.id}` )
                .subscribe( result => {
                    this.register   =   result;
                });

            nsHttpClient.get( `/api/cash-registers/${this.settings.register.id}/session-summary` )
                .subscribe( result => {
                    this.sessionSummary  =   result;
                    this.loadingSummary  =   false;
                });
        },

        closePopup() {
            this.popupResolver({
                status: 'error',
                button: 'close_popup'
            });
        },

        async closeCashRegister( register ) {
            try {
                const response  =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosCashRegistersActionPopupVue, {
                        title: __( 'Close Register' ),
                        action: 'close',
                        identifier: 'ns.cash-registers-closing',
                        register,
                        resolve, 
                        reject
                    })
                });

                /**
                 * if the register has been successfully
                 * closed, we need to delete the registe reference
                 */
                POS.unset( 'register' );
                
                this.popupResolver({
                    button: 'close_register',
                    ...response
                });
            } catch( exception ) {
                throw exception;
            }
        },

        async cashIn( register ) {
            try {
                const response  =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosCashRegistersActionPopupVue, {
                        title: __( 'Cash In' ),
                        action: 'register-cash-in',
                        register,
                        identifier: 'ns.cash-registers-cashing',
                        resolve, 
                        reject
                    })
                });

                /**
                 * if the register has been successfully
                 * closed, we need to delete the registe reference
                 */
                this.popupResolver({
                    button: 'close_register',
                    ...response
                });
            } catch( exception ) {
                console.log({exception});
            }
        },

        async cashOut( register ) {
            try {
                const response  =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosCashRegistersActionPopupVue, {
                        title: __( 'Cash Out' ),
                        action: 'register-cash-out',
                        register,
                        identifier: 'ns.cash-registers-cashout',
                        resolve, 
                        reject
                    })
                });

                /**
                 * if the register has been successfully
                 * closed, we need to delete the registe reference
                 */
                this.popupResolver({
                    button: 'close_register',
                    ...response
                });
            } catch( exception ) {
                throw exception;
            }
        },

        async historyPopup( register ) {
            try {
                const response  =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosCashRegistersHistoryVue, { resolve, reject, register });
                });
            } catch( exception ) {
                throw exception;
            }
        }
    }
}
</script>
<template>
    <div class="shadow-lg w-95vw md:w-[60vw] lg:w-half ns-box">
        <div class="p-2 border-b ns-box-header flex items-center justify-between">
            <h3>{{ __( 'Register Options' ) }}</h3>
            <div>
                <ns-close-button @click="closePopup()"></ns-close-button>
            </div>
        </div>
        <div v-if="sessionSummary && !loadingSummary">
            <div class="flex">
                <!-- Cash Summary -->
                <div class="w-1/2 border-r border-box-edge">
                    <div class="p-2 bg-success-primary success border-b border-box-edge">
                        <h3 class="font-bold text-sm uppercase tracking-wide">{{ __( 'Cash Summary' ) }}</h3>
                    </div>
                    <div class="text-sm">
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Starting Cash' ) }}</span>
                            <span class="font-semibold">{{ nsCurrency( sessionSummary.cash_summary.starting_cash ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Cash Payments' ) }}</span>
                            <span class="font-semibold">{{ nsCurrency( sessionSummary.cash_summary.cash_payments ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Paid In' ) }}</span>
                            <span class="font-semibold text-success-tertiary">{{ nsCurrency( sessionSummary.cash_summary.paid_in ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Paid Out' ) }}</span>
                            <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.cash_summary.paid_out ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Change' ) }}</span>
                            <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.cash_summary.change ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-2 bg-success-secondary success font-bold">
                            <span>{{ __( 'Expected Cash' ) }}</span>
                            <span>{{ nsCurrency( sessionSummary.cash_summary.expected_cash ) }}</span>
                        </div>
                    </div>
                </div>
                <!-- Sales Summary -->
                <div class="w-1/2">
                    <div class="p-2 bg-info-primary info border-b border-box-edge">
                        <h3 class="font-bold text-sm uppercase tracking-wide">{{ __( 'Sales Summary' ) }}</h3>
                    </div>
                    <div class="text-sm">
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Gross Sales' ) }}</span>
                            <span class="font-semibold">{{ nsCurrency( sessionSummary.sales_summary.gross_sales ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Discounts' ) }}</span>
                            <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.sales_summary.discounts ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-1.5 border-b border-box-edge">
                            <span>{{ __( 'Refunds' ) }}</span>
                            <span class="font-semibold text-error-primary">{{ nsCurrency( sessionSummary.sales_summary.refunds ) }}</span>
                        </div>
                        <div class="flex justify-between px-3 py-2 bg-info-primary info font-bold border-b border-box-edge">
                            <span>{{ __( 'Net Sales' ) }}</span>
                            <span>{{ nsCurrency( sessionSummary.sales_summary.net_sales ) }}</span>
                        </div>
                        <div v-for="payment in sessionSummary.sales_summary.payment_breakdown" :key="payment.label" class="flex justify-between px-3 py-1.5 border-b border-box-edge pl-6">
                            <span>{{ payment.label }}</span>
                            <span class="font-semibold">{{ nsCurrency( payment.value ) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="h-32 ns-box-body border-b py-1 flex items-center justify-center" v-if="loadingSummary">
            <div>
                <ns-spinner border="4" size="16"></ns-spinner>
            </div>
        </div>
        <div class="grid grid-cols-2 text-font">
            <div @click="closeCashRegister( register )" class="border-r border-b py-4 ns-numpad-key info cursor-pointer px-2 flex items-center justify-center flex-col">
                <i class="las la-sign-out-alt text-6xl"></i>
                <h3 class="text-xl font-bold">{{ __( 'Close' ) }}</h3>
            </div>
            <div @click="cashIn( register )" class="ns-numpad-key success border-r border-b py-4 cursor-pointer px-2 flex items-center justify-center flex-col">
                <i class="las la-plus-circle text-6xl"></i>
                <h3 class="text-xl font-bold">{{ __( 'Cash In' ) }}</h3>
            </div>
            <div @click="cashOut( register )" class="ns-numpad-key error border-r border-b py-4 cursor-pointer px-2 flex items-center justify-center flex-col">
                <i class="las la-minus-circle text-6xl"></i>
                <h3 class="text-xl font-bold">{{ __( 'Cash Out' ) }}</h3>
            </div>
            <div @click="historyPopup( register )" class="ns-numpad-key info border-r border-b py-4 cursor-pointer px-2 flex items-center justify-center flex-col">
                <i class="las la-history text-6xl"></i>
                <h3 class="text-xl font-bold">{{ __( 'History' ) }}</h3>
            </div>
        </div>
    </div>
</template>