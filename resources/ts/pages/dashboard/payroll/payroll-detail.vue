<template>
    <div id="payroll-detail" class="px-4">
        <div v-if="loading" class="flex items-center justify-center h-48">
            <ns-spinner></ns-spinner>
        </div>

        <template v-if="!loading && run">
            <div class="ns-box rounded-lg shadow mb-4">
                <div class="ns-box-header p-2 border-b flex justify-between items-center">
                    <h3 class="font-semibold text-primary">{{ __( 'Payroll Run Detail' ) }}</h3>
                    <span
                        :class="statusClass"
                        class="px-3 py-1 rounded-full text-white text-xs font-semibold"
                    >{{ statusLabel }}</span>
                </div>
                <div class="ns-box-body p-4">
                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Employee' ) }}</p>
                            <p class="font-semibold text-primary">{{ run.user?.username || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Period Type' ) }}</p>
                            <p class="font-semibold text-primary">{{ run.period_type }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Period' ) }}</p>
                            <p class="font-semibold text-primary">{{ formatDate( run.period_start ) }} — {{ formatDate( run.period_end ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Hourly Rate' ) }}</p>
                            <p class="font-semibold text-primary">{{ currency( run.hourly_rate ) }}/hr</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Total Hours' ) }}</p>
                            <p class="font-semibold text-primary">{{ run.total_hours }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Regular Pay' ) }}</p>
                            <p class="font-semibold text-primary">{{ currency( sum( 'regular_pay' ) ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Overtime Hours' ) }}</p>
                            <p class="font-semibold text-primary">{{ sum( 'overtime_hours' ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Overtime Pay' ) }}</p>
                            <p class="font-semibold text-primary">{{ currency( sum( 'overtime_pay' ) ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Holiday Premium' ) }}</p>
                            <p class="font-semibold text-primary">{{ currency( sum( 'holiday_pay' ) ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Night Differential' ) }}</p>
                            <p class="font-semibold text-primary">{{ currency( sum( 'night_diff_pay' ) ) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-secondary mb-1">{{ __( 'Gross Pay' ) }}</p>
                            <p class="font-bold text-lg text-green-600">{{ currency( run.gross_pay ) }}</p>
                        </div>
                    </div>

                    <div v-if="run.transaction" class="mb-4 p-3 bg-blue-50 rounded border border-blue-200">
                        <p class="text-sm">
                            <span class="font-semibold">{{ __( 'Accounting Entry' ) }}:</span>
                            <a :href="'/dashboard/accounting/transactions/edit/' + run.transaction.id" class="text-blue-600 underline ml-1">{{ run.transaction.name }}</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="ns-box rounded-lg shadow">
                <div class="ns-box-header p-2 border-b">
                    <h3 class="font-semibold text-primary">{{ __( 'Attendance Records' ) }}</h3>
                </div>
                <div class="ns-box-body p-4">
                    <div class="ns-table-container">
                        <table class="ns-table">
                            <thead>
                                <tr>
                                    <th>{{ __( 'Date' ) }}</th>
                                    <th>{{ __( 'Clock In' ) }}</th>
                                    <th>{{ __( 'Clock Out' ) }}</th>
                                    <th class="text-right">{{ __( 'Break' ) }}</th>
                                    <th class="text-right">{{ __( 'Net Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'Reg Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'Reg Pay' ) }}</th>
                                    <th class="text-right">{{ __( 'OT Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'OT Pay' ) }}</th>
                                    <th class="text-right">{{ __( 'Holiday' ) }}</th>
                                    <th class="text-right">{{ __( 'Night Diff' ) }}</th>
                                    <th class="text-right">{{ __( 'Total' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in run.items" :key="item.id">
                                    <td>{{ formatDate( item.clock_in ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( item.clock_in ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( item.clock_out ) }}</td>
                                    <td class="text-right">{{ item.break_deduction || '0.00' }}</td>
                                    <td class="text-right">{{ item.hours_worked }}</td>
                                    <td class="text-right">{{ item.regular_hours || '0.00' }}</td>
                                    <td class="text-right">{{ currency( item.regular_pay ) }}</td>
                                    <td class="text-right">{{ item.overtime_hours || '0.00' }}</td>
                                    <td class="text-right">{{ currency( item.overtime_pay ) }}</td>
                                    <td class="text-right">{{ currency( item.holiday_pay ) }}</td>
                                    <td class="text-right">{{ currency( item.night_diff_pay ) }}</td>
                                    <td class="text-right font-semibold">{{ currency( item.pay_amount ) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold text-primary">
                                    <td colspan="3" class="text-right">{{ __( 'Total' ) }}</td>
                                    <td class="text-right">{{ sum( 'break_deduction' ) }}</td>
                                    <td class="text-right">{{ run.total_hours }}</td>
                                    <td></td>
                                    <td class="text-right">{{ currency( sum( 'regular_pay' ) ) }}</td>
                                    <td></td>
                                    <td class="text-right">{{ currency( sum( 'overtime_pay' ) ) }}</td>
                                    <td class="text-right">{{ currency( sum( 'holiday_pay' ) ) }}</td>
                                    <td class="text-right">{{ currency( sum( 'night_diff_pay' ) ) }}</td>
                                    <td class="text-right">{{ currency( run.gross_pay ) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 mt-4" v-if="run.status === 'draft' && run.gross_pay > 0">
                <button
                    @click="postRun"
                    class="rounded bg-green-500 text-white shadow py-2 px-6 text-sm font-semibold hover:bg-green-600 transition-colors duration-200"
                >{{ __( 'Post Pay Run' ) }}</button>
            </div>
            <div class="flex gap-2 mt-4" v-if="run.status === 'posted'">
                <button
                    @click="voidRun"
                    class="rounded bg-red-500 text-white shadow py-2 px-6 text-sm font-semibold hover:bg-red-600 transition-colors duration-200"
                >{{ __( 'Void Pay Run' ) }}</button>
            </div>
        </template>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { nsCurrency } from '~/filters/currency';
import { __ } from '~/libraries/lang';

export default {
    name: 'nsPayrollDetail',
    props: {
        runId: { type: [ String, Number ], required: true },
        userName: { type: String, default: '' },
    },
    data() {
        return {
            run: null,
            loading: true,
        };
    },
    computed: {
        statusClass() {
            return { draft: 'bg-gray-500', posted: 'bg-green-500', void: 'bg-red-500' }[ this.run?.status ] || 'bg-gray-500';
        },
        statusLabel() {
            return { draft: __( 'Draft' ), posted: __( 'Posted' ), void: __( 'Void' ) }[ this.run?.status ] || this.run?.status;
        },
    },
    mounted() {
        this.loadRun();
    },
    methods: {
        __,
        currency( value ) { return nsCurrency( value ); },
        sum( field ) {
            if ( ! this.run || ! this.run.items ) return '0.00';
            return this.run.items.reduce( ( acc, item ) => acc + ( parseFloat( item[ field ] ) || 0 ), 0 ).toFixed( 2 );
        },
        formatDate( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD' );
        },
        formatDateTime( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD HH:mm:ss' );
        },
        loadRun() {
            nsHttpClient.get( `/api/payroll/runs/${this.runId}` ).subscribe({
                next: (result) => {
                    this.run = result.data?.run || result;
                    this.loading = false;
                },
                error: (e) => {
                    console.error( e );
                    this.loading = false;
                },
            });
        },
        postRun() {
            if ( ! confirm( __( 'Post this payroll run? This will create an accounting entry.' ) ) ) return;

            nsHttpClient.post( `/api/payroll/runs/${this.runId}/post`, {} ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Payroll run posted.' ) );
                        this.loadRun();
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to post.' ) );
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'An error occurred.' ) ); },
            });
        },
        voidRun() {
            if ( ! confirm( __( 'Void this payroll run? The accounting entry will be removed.' ) ) ) return;

            nsHttpClient.post( `/api/payroll/runs/${this.runId}/void`, {} ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Payroll run voided.' ) );
                        this.loadRun();
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to void.' ) );
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'An error occurred.' ) ); },
            });
        },
    },
};
</script>
