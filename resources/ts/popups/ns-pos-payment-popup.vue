<script lang="ts">
import { nsSnackBar } from '~/bootstrap';
import resolveIfQueued from "~/libraries/popup-resolver";
import { Popup } from '~/libraries/popup';
import { __ } from '~/libraries/lang';
import CashPayment from "~/pages/dashboard/pos/payments/cash-payment.vue";
import CreditCardPayment from "~/pages/dashboard/pos/payments/creditcard-payment.vue";
import BankPayment from '~/pages/dashboard/pos/payments/bank-payment.vue';
import AccountPayment from '~/pages/dashboard/pos/payments/account-payment.vue';
import nsPosLoadingPopupVue from './ns-pos-loading-popup.vue';
import samplePaymentVue from '~/pages/dashboard/pos/payments/sample-payment.vue';
import nsSelectPopupVue from './ns-select-popup.vue';
import { nsCurrency, nsRawCurrency } from '~/filters/currency';
import { ref } from 'vue';
import { nsConfirmPopup } from '~/components/components';
import { HttpStatusResponse } from '~/interfaces/http-status-response';

declare const POS, nsHooks, nsCloseButton, nsButton, shallowRef;

export default {
    name: 'ns-pos-payment',
    props: [ 'popup' ],
    data() {
        return { 
            paymentTypesSubscription: null,
            paymentsType: [],
            activePayment: null,
            order: null,
            showPayment: false,
            orderSubscription: null,
            currentPaymentComponent: null,
            activePaymentSubscription: null,
            quickAmounts: [],
            selectedAmount: null,
            customAmount: 0,
            chargeAmount: 0,
            changeDue: 0,
        } 
    },
    computed: {
        expectedPayment() {
            const minimalPaymentPercent     =   this.order.customer.group.minimal_credit_payment;
            return ( this.order.total * minimalPaymentPercent ) / 100;
        }
    },
    mounted() {
        this.orderSubscription          =   POS.order.subscribe( order => {
            this.order  =   ref( order );
        });

        this.activePaymentSubscription  =   POS.selectedPaymentType.subscribe( activePayment => {
            this.activePayment = activePayment;
            if ( activePayment !== null ) {
                this.loadPaymentComponent( activePayment );
            }
        });
        this.paymentTypesSubscription   =   POS.paymentsType.subscribe( paymentsType => {
            const allowed = paymentsType.filter( payment => {
                const label = ( payment.label || '' ).toLowerCase();
                return payment.identifier === 'cash-payment' || label.includes( 'gcash' ) || label.includes( 'g-cash' );
            });
            this.paymentsType   =   allowed;
            allowed.filter( payment => {
                if ( payment.selected ) {
                    POS.selectedPaymentType.next( payment );
                }
            });
        });

        this.computeQuickAmounts();

        nsHooks.doAction( 'ns-pos-payment-mounted', this );
    },
    unmounted() {
        this.activePaymentSubscription.unsubscribe();
        this.paymentTypesSubscription.unsubscribe();
        this.orderSubscription.unsubscribe();

        nsHooks.doAction( 'ns-pos-payment-destroyed', this );
    },    
    methods: {
        __, 
        nsCurrency,
        
        resolveIfQueued,

        loadPaymentComponent( payment ) {
            switch( payment.identifier ) {
                case 'cash-payment':
                    this.currentPaymentComponent    =   shallowRef( CashPayment );
                break;
                case 'creditcard-payment':
                    this.currentPaymentComponent    =   shallowRef( CreditCardPayment );
                break;
                case 'bank-payment':
                    this.currentPaymentComponent    =   shallowRef( BankPayment );
                break;
                case 'account-payment':
                    this.currentPaymentComponent    =   shallowRef( AccountPayment );
                break;
                default: 
                    this.currentPaymentComponent    =   shallowRef( samplePaymentVue );
                break;
            }
        },
        async selectPayment() {
            try {
                const result    =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        label: __( 'Select Payment Gateway' ),
                        options: this.paymentsType.map( payment => {
                            return {
                                label: payment.label,
                                value: payment.identifier
                            }
                        }),
                        value: this.activePayment.identifier,
                        resolve, reject
                    })
                });

                this.select( this.paymentsType.filter( p => p.identifier === result[0].value )[0] );
            } catch( exception ) {
                // not necessary to throw an error.
            }
        },
        select( payment ) {
            this.showPayment    =   false;
            POS.setPaymentActive( payment );
        },
        closePopup() {
            console.log( this.popup );
            this.popup.close();
            POS.selectedPaymentType.next( null );
        },
        deletePayment( payment ) {
            POS.removePayment( payment );
        },
        selectPaymentAsActive( event ) {
            this.select( this.paymentsType.filter( payment => payment.identifier === event.target.value )[0] );
        },
        computeQuickAmounts() {
            const total = this.order?.total || 0;
            const suggestions = [];
            const denominations = [20, 50, 100, 200, 500, 1000];
            for (const denom of denominations) {
                if (denom > total) {
                    suggestions.push(denom);
                    if (suggestions.length >= 4) break;
                }
            }
            if (suggestions.length === 0 || suggestions[suggestions.length - 1] !== 1000) {
                suggestions.push(1000);
            }
            suggestions.push('exact');
            this.quickAmounts = suggestions;
        },
        setAmount(amount) {
            this.selectedAmount = amount;
            if (amount === 'exact') {
                this.chargeAmount = this.order?.total || 0;
                this.customAmount = 0;
            } else {
                this.chargeAmount = amount;
                this.customAmount = amount;
            }
            this.changeDue = Math.max(0, this.chargeAmount - (this.order?.total || 0));
        },
        onCustomAmountInput() {
            this.selectedAmount = null;
            this.chargeAmount = parseFloat(this.customAmount) || 0;
            this.changeDue = Math.max(0, this.chargeAmount - (this.order?.total || 0));
        },
        submitPayment() {
            if (this.chargeAmount > 0 && this.activePayment) {
                POS.addPayment({
                    identifier: this.activePayment.identifier,
                    value: this.chargeAmount,
                    label: this.activePayment.label,
                });
            }
            this.submitOrder();
        },
        getPaymentLabel( payment ) {
            const foundPayment = this.paymentsType.filter( p => p.identifier === payment.identifier )[0];

            if ( foundPayment ) {
                return foundPayment.label;
            }

            return payment.identifier;
        },
        submitOrder( data = {}) {
            const popup     =   Popup.show( nsPosLoadingPopupVue );
            
            try {

                const order     =   { ...POS.order.getValue(), ...data };

                POS.submitOrder( order ).then( result => {
                    // close spinner
                    popup.close();

                    nsSnackBar.success( result.message );

                    POS.printOrderReceipt( result.data.order, 'silent' );
    
                    // close payment popup
                    this.popup.close();
                }, ( error ) => {
                    // close loading popup
                    popup.close();
    
                    // show error message
                    nsSnackBar.error( error.message );
                });
            } catch( exception ) {
                popup.close();
    
                // show error message
                nsSnackBar.error( exception.message || __( 'An unexpected error occurred while submitting the order.' ) );
                console.log( exception );
            }
        }
    }
}
</script>
        <template>
    <div id="ns-payment-popup" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30" @click.self="closePopup()" v-if="order">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
            <div class="p-6">
                <!-- Total -->
                <div class="text-center mb-6">
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">{{ __( 'Total Due' ) }}</div>
                    <div class="text-3xl lg:text-4xl font-extrabold text-emerald-600">{{ nsCurrency( order?.total || 0 ) }}</div>
                </div>

                <!-- Payment Method Tabs -->
                <div class="flex gap-2 mb-6">
                    <div v-for="payment of paymentsType" :key="payment.identifier"
                        @click="select(payment)"
                        :class="activePayment?.identifier === payment.identifier ? 'bg-emerald-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
                        class="flex-1 text-center py-3 rounded-lg font-semibold text-sm cursor-pointer transition-colors">
                        {{ payment.label }}
                    </div>
                </div>

                <!-- Quick Amounts (shown for cash payment) -->
                <div v-if="activePayment?.identifier === 'cash-payment'" class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ __( 'Quick Amount' ) }}</div>
                    <div class="flex gap-2 flex-wrap">
                        <div v-for="amount in quickAmounts" :key="amount"
                            @click="setAmount(amount)"
                            :class="selectedAmount === amount ? 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300' : 'border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-emerald-300'"
                            class="px-4 py-2 border-2 rounded-lg font-bold text-sm cursor-pointer transition-colors">
                            ₱{{ amount === 'exact' ? 'Exact' : amount.toLocaleString() }}
                        </div>
                    </div>
                </div>

                <!-- Custom Amount Input -->
                <div class="mb-4">
                    <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __( 'Or enter amount' ) }}</div>
                    <input type="number" v-model="customAmount" @input="onCustomAmountInput"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-3 text-lg font-semibold bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none"
                        placeholder="₱0.00" />
                </div>

                <!-- Change Due -->
                <div v-if="changeDue > 0" class="flex justify-between items-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg mb-6">
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __( 'Change Due' ) }}</span>
                    <span class="text-xl font-extrabold text-emerald-600">₱{{ changeDue.toLocaleString() }}</span>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button @click="closePopup()" class="flex-1 py-3 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl font-semibold text-sm cursor-pointer hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        {{ __( 'Cancel' ) }}
                    </button>
                    <button @click="submitPayment()" :disabled="chargeAmount <= 0" class="flex-[2] py-3 bg-emerald-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl font-bold text-base cursor-pointer hover:bg-emerald-700 transition-colors">
                        {{ __( 'Charge' ) }} {{ chargeAmount > 0 ? nsCurrency(chargeAmount) : '' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
