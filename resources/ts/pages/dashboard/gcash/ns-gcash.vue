<template>
    <div class="px-4">
        <div v-if="loading" class="text-center py-8 text-secondary">{{ __( 'Loading...' ) }}</div>

        <template v-if="!loading">
            <div v-if="error" class="flex items-center justify-center h-64">
                <div class="text-center">
                    <p class="text-xl text-error-primary mb-4">{{ error }}</p>
                    <div class="ns-button default inline-flex"><button class="px-4 py-2" @click="init">{{ __( 'Retry' ) }}</button></div>
                </div>
            </div>

            <template v-if="!error && !activeSession">
                <div class="flex items-center justify-center h-64">
                    <div class="text-center">
                        <h2 class="text-2xl font-bold mb-4">{{ __( 'No Active GCash Session' ) }}</h2>
                        <p class="text-secondary mb-6">{{ __( 'Open a GCash session to start tracking cash-in and cash-out transactions.' ) }}</p>
                        <div v-if="registerOpened" class="ns-button success inline-flex"><button class="px-6 py-3 text-lg" @click="openSession">{{ __( 'Open GCash Session' ) }}</button></div>
                        <p v-else class="text-warning-primary">{{ __( 'Please open the cash register first.' ) }}</p>
                    </div>
                </div>
            </template>

            <template v-if="!error && activeSession">
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'Opening' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.opening_balance ) }}</p>
                    </div>
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'GCash Payments' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.gcashPaymentsTotal || 0 ) }}</p>
                    </div>
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'Cash-In' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.cashInTotal || 0 ) }}</p>
                    </div>
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'Cash-Out' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.cashOutTotal || 0 ) }}</p>
                    </div>
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'Balance' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.computedBalance || session.current_balance ) }}</p>
                    </div>
                    <div class="rounded-lg shadow-sm bg-surface p-4 border border-edge">
                        <p class="text-xs text-secondary font-semibold uppercase tracking-wide mb-1">{{ __( 'Fees Earned' ) }}</p>
                        <p class="text-xl font-bold">{{ nsCurrency( session.total_fees ) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 mb-6">
                    <div class="ns-button success"><button class="px-5 py-2" @click="showCashInPopup">{{ __( 'Cash In' ) }}</button></div>
                    <div class="ns-button warning"><button class="px-5 py-2" @click="showCashOutPopup">{{ __( 'Cash Out' ) }}</button></div>
                    <div class="ns-button default ml-auto"><button class="px-5 py-2" @click="closeSession">{{ __( 'Close Session' ) }}</button></div>
                </div>

                <div class="ns-table-container">
                    <table class="ns-table">
                        <thead>
                            <tr>
                                <th>{{ __( 'Date' ) }}</th>
                                <th>{{ __( 'Type' ) }}</th>
                                <th class="text-right">{{ __( 'Amount' ) }}</th>
                                <th class="text-right">{{ __( 'Fee' ) }}</th>
                                <th>{{ __( 'Phone' ) }}</th>
                                <th>{{ __( 'Reference' ) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="(!session.transactions || session.transactions.length === 0)">
                                <td colspan="6" class="text-center py-8 text-secondary">{{ __( 'No transactions yet.' ) }}</td>
                            </tr>
                            <tr v-for="tx in session.transactions" :key="tx.id">
                                <td>{{ tx.created_at }}</td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                        :class="tx.type === 'cash_in' ? 'bg-success-secondary text-white' : 'bg-warning-secondary text-white'"
                                    >{{ tx.type === 'cash_in' ? __( 'Cash In' ) : __( 'Cash Out' ) }}</span>
                                </td>
                                <td class="text-right">{{ nsCurrency( tx.customer_amount ) }}</td>
                                <td class="text-right">{{ nsCurrency( tx.service_fee ) }}</td>
                                <td>{{ tx.customer_phone || '—' }}</td>
                                <td>{{ tx.reference_no || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </template>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';
import { nsCurrency } from '~/filters/currency';
import { Popup } from '~/libraries/popup';
import popupCloser from '~/libraries/popup-closer';
import nsGcashOpenPopupVue from './ns-gcash-open-popup.vue';
import nsGcashCashinPopupVue from './ns-gcash-cashin-popup.vue';
import nsGcashCashoutPopupVue from './ns-gcash-cashout-popup.vue';
import nsGcashClosePopupVue from './ns-gcash-close-popup.vue';

export default {
    name: 'ns-gcash',
    data() {
        return {
            loading: true,
            error: null,
            registerOpened: false,
            activeSession: false,
            session: {
                opening_balance: 0,
                current_balance: 0,
                total_fees: 0,
                transactions: [],
            },
            registerId: null,
            registerSubscription: null,
        };
    },
    mounted() {
        this.registerSubscription = window.POS?.settings?.subscribe?.( settings => {
            if ( settings?.register ) {
                this.registerId = settings.register.id;
                this.registerOpened = settings.register.status === 'opened';
            }
        });
        this.init();
    },
    unmounted() {
        this.registerSubscription?.unsubscribe?.();
    },
    methods: {
        __,
        nsCurrency,
        popupCloser,

        async init() {
            this.loading = true;
            this.error = null;

            try {
                await new Promise( ( resolve ) => {
                    nsHttpClient.get( '/api/cash-registers/used' ).subscribe({
                        next: ( result ) => {
                            const register = result?.data?.register || result?.register;
                            if ( register ) {
                                this.registerId = register.id;
                                this.registerOpened = register.status === 'opened';
                            }
                            resolve();
                        },
                        error: () => resolve(),
                    });
                });

                if ( !this.registerId ) {
                    this.error = __( 'No register assigned. Please open a register first.' );
                    this.loading = false;
                    return;
                }

                nsHttpClient.get( `/api/gcash/active-session/${this.registerId}` ).subscribe({
                    next: ( result ) => {
                        if ( result.status === 'success' && result.data ) {
                            this.activeSession = true;
                            this.session = {
                                id: result.data.session?.id,
                                opening_balance: result.data.session?.opening_balance || 0,
                                current_balance: result.data.session?.current_balance || 0,
                                total_fees: result.data.totalFees || result.data.session?.total_fees || 0,
                                total_cash_in: result.data.session?.total_cash_in || 0,
                                total_cash_out: result.data.session?.total_cash_out || 0,
                                cashInTotal: result.data.cashInTotal || 0,
                                cashOutTotal: result.data.cashOutTotal || 0,
                                gcashPaymentsTotal: result.data.gcashPaymentsTotal || 0,
                                computedBalance: result.data.computedBalance || result.data.session?.current_balance || 0,
                                transactions: result.data.transactions || [],
                            };
                        }
                        this.loading = false;
                    },
                    error: ( err ) => {
                        this.error = err.message || __( 'Failed to load GCash session.' );
                        this.loading = false;
                    },
                });
            } catch ( e ) {
                this.error = __( 'Please open a register first.' );
                this.loading = false;
            }
        },

        refresh() {
            if ( !this.registerId ) return;

            nsHttpClient.get( `/api/gcash/active-session/${this.registerId}` ).subscribe({
                next: ( result ) => {
                    if ( result.status === 'success' && result.data ) {
                        this.activeSession = true;
                        this.session = {
                            ...result.data.session,
                            transactions: result.data.transactions || [],
                            total_fees: result.data.totalFees || result.data.session?.total_fees || 0,
                            cashInTotal: result.data.cashInTotal || 0,
                            cashOutTotal: result.data.cashOutTotal || 0,
                            gcashPaymentsTotal: result.data.gcashPaymentsTotal || 0,
                            computedBalance: result.data.computedBalance || result.data.session?.current_balance || 0,
                        };
                    }
                },
            });
        },

        openSession() {
            Popup.show( nsGcashOpenPopupVue, {
                register: window.POS?.settings?.value?.register || { id: this.registerId },
                resolve: () => {
                    this.refresh();
                },
            });
        },

        showCashInPopup() {
            Popup.show( nsGcashCashinPopupVue, {
                session: this.session,
                resolve: () => {
                    this.refresh();
                },
            });
        },

        showCashOutPopup() {
            Popup.show( nsGcashCashoutPopupVue, {
                session: this.session,
                resolve: () => {
                    this.refresh();
                },
            });
        },

        closeSession() {
            Popup.show( nsGcashClosePopupVue, {
                session: this.session,
                resolve: () => {
                    this.activeSession = false;
                    this.init();
                },
            });
        },
    },
};
</script>
