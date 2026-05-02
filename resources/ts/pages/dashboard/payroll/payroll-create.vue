<template>
    <div id="payroll-create" class="px-4">
        <div class="ns-box rounded-lg shadow">
            <div class="ns-box-header p-2 border-b flex justify-between items-center">
                <h3 class="font-semibold text-primary">{{ __( 'Create Pay Run' ) }}</h3>
            </div>
            <div class="ns-box-body p-4">
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Employee' ) }}</label>
                        <select
                            v-model="form.user_id"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                        >
                            <option value="">{{ __( 'Select Employee' ) }}</option>
                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="user.id"
                            >{{ user.username }} ({{ __( 'Rate' ) }}: {{ user.hourly_rate }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Pay Period Type' ) }}</label>
                        <select
                            v-model="form.period_type"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                            @change="computePeriod"
                        >
                            <option value="weekly">{{ __( 'Weekly' ) }}</option>
                            <option value="bi-weekly">{{ __( 'Bi-Weekly' ) }}</option>
                            <option value="monthly">{{ __( 'Monthly' ) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Period Start' ) }}</label>
                        <input
                            v-model="form.period_start"
                            type="date"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                        />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Period End' ) }}</label>
                        <input
                            v-model="form.period_end"
                            type="date"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                        />
                    </div>
                </div>

                <div class="flex gap-2 mb-6">
                    <button
                        @click="computePeriod"
                        class="rounded bg-blue-500 text-white shadow py-2 px-4 text-sm font-semibold hover:bg-blue-600 transition-colors duration-200"
                    >{{ __( 'Compute Period' ) }}</button>
                    <button
                        @click="previewAttendance"
                        :disabled="!form.user_id || !form.period_start || !form.period_end"
                        class="rounded bg-info-tertiary text-white shadow py-2 px-4 text-sm font-semibold hover:bg-blue-700 transition-colors duration-200 disabled:opacity-50"
                    >{{ __( 'Preview Attendance' ) }}</button>
                </div>

                <div v-if="attendanceRecords.length > 0" class="mb-6">
                    <h3 class="font-semibold mb-2 text-primary">{{ __( 'Attendance Records' ) }}</h3>
                    <div class="ns-table-container">
                        <table class="ns-table">
                            <thead>
                                <tr>
                                    <th>{{ __( 'Date' ) }}</th>
                                    <th>{{ __( 'Clock In' ) }}</th>
                                    <th>{{ __( 'Clock Out' ) }}</th>
                                    <th>{{ __( 'Break Start' ) }}</th>
                                    <th>{{ __( 'Break End' ) }}</th>
                                    <th class="text-right">{{ __( 'Break' ) }}</th>
                                    <th class="text-right">{{ __( 'Net Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'Reg Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'Reg Pay' ) }}</th>
                                    <th class="text-right">{{ __( 'OT Hrs' ) }}</th>
                                    <th class="text-right">{{ __( 'Est. OT Pay' ) }}</th>
                                    <th class="text-right">{{ __( 'Est. Total' ) }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="record in attendanceRecords" :key="record.id">
                                    <td>{{ formatDate( record.clock_in_at ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( record.clock_in_at ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( record.clock_out_at ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( record.break_start ) }}</td>
                                    <td class="text-sm">{{ formatDateTime( record.break_end ) }}</td>
                                    <td class="text-right">{{ record.break_hours || '0.00' }}</td>
                                    <td class="text-right">{{ record.net_hours || record.total_hours }}</td>
                                    <td class="text-right">{{ computeRegHrs( record ) }}</td>
                                    <td class="text-right font-semibold">{{ computeRegPay( record ) }}</td>
                                    <td class="text-right">{{ computeOtHrs( record ) }}</td>
                                    <td class="text-right">{{ computeOtPay( record ) }}</td>
                                    <td class="text-right font-bold">{{ computeTotalPay( record ) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="font-bold text-primary">
                                    <td colspan="6" class="text-right">{{ __( 'Totals' ) }}</td>
                                    <td class="text-right">{{ totalPreviewHours }}</td>
                                    <td></td>
                                    <td class="text-right">{{ totalRegPay }}</td>
                                    <td></td>
                                    <td class="text-right">{{ totalOtPay }}</td>
                                    <td class="text-right">{{ totalEstPay }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="createRun"
                        :disabled="submitting || !form.user_id"
                        class="rounded bg-green-500 text-white shadow py-2 px-6 text-sm font-semibold hover:bg-green-600 transition-colors duration-200 disabled:opacity-50"
                    >{{ submitting ? __( 'Creating...' ) : __( 'Create Pay Run' ) }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'nsPayrollCreate',
    data() {
        return {
            users: [],
            form: {
                user_id: '',
                period_type: 'bi-weekly',
                period_start: '',
                period_end: '',
            },
            attendanceRecords: [],
            hourlyRate: 0,
            submitting: false,
        };
    },
    computed: {
        totalPreviewHours() {
            return this.attendanceRecords.reduce( ( sum, r ) => sum + ( parseFloat( r.net_hours || r.total_hours ) || 0 ), 0 ).toFixed( 2 );
        },
        totalRegPay() {
            return this.attendanceRecords.reduce( ( sum, r ) => sum + parseFloat( this.computeRegPay( r ) || 0 ), 0 ).toFixed( 2 );
        },
        totalOtPay() {
            return this.attendanceRecords.reduce( ( sum, r ) => sum + parseFloat( this.computeOtPay( r ) || 0 ), 0 ).toFixed( 2 );
        },
        totalEstPay() {
            return ( parseFloat( this.totalRegPay ) + parseFloat( this.totalOtPay ) ).toFixed( 2 );
        },
    },
    mounted() {
        this.loadUsers();
    },
    methods: {
        __,
        formatDate( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD' );
        },
        formatDateTime( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD HH:mm:ss' );
        },
        loadUsers() {
            nsHttpClient.get( '/api/payroll/eligible-users' ).subscribe({
                next: (users) => { this.users = users; },
                error: () => { nsSnackBar.error( __( 'Failed to load employees.' ) ); },
            });
        },
        computePeriod() {
            nsHttpClient.get( `/api/payroll/period-boundaries?period_type=${this.form.period_type}` ).subscribe({
                next: (result) => {
                    this.form.period_start = result.start;
                    this.form.period_end = result.end;
                },
                error: () => { nsSnackBar.error( __( 'Failed to compute period.' ) ); },
            });
        },
        previewAttendance() {
            const selectedUser = this.users.find( u => u.id == this.form.user_id );
            this.hourlyRate = selectedUser ? parseFloat( selectedUser.hourly_rate ) || 0 : 0;

            nsHttpClient.get( `/api/payroll/unpaid-attendance?user_id=${this.form.user_id}&period_start=${this.form.period_start}&period_end=${this.form.period_end}` ).subscribe({
                next: (records) => { this.attendanceRecords = records; },
                error: () => {
                    nsSnackBar.error( __( 'Failed to load attendance records.' ) );
                    this.attendanceRecords = [];
                },
            });
        },
        netHrs( record ) {
            return parseFloat( record.net_hours || record.total_hours || 0 );
        },
        computeRegHrs( record ) {
            return Math.min( this.netHrs( record ), 8 ).toFixed( 2 );
        },
        computeRegPay( record ) {
            return ( parseFloat( this.computeRegHrs( record ) ) * this.hourlyRate ).toFixed( 2 );
        },
        computeOtHrs( record ) {
            return Math.max( 0, this.netHrs( record ) - 8 ).toFixed( 2 );
        },
        computeOtPay( record ) {
            return ( parseFloat( this.computeOtHrs( record ) ) * this.hourlyRate * 1.25 ).toFixed( 2 );
        },
        computeTotalPay( record ) {
            return ( parseFloat( this.computeRegPay( record ) ) + parseFloat( this.computeOtPay( record ) ) ).toFixed( 2 );
        },
        createRun() {
            this.submitting = true;

            nsHttpClient.post( '/api/payroll/runs', this.form ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Payroll draft created!' ) );
                        this.attendanceRecords = [];
                        this.form = { user_id: '', period_type: 'bi-weekly', period_start: '', period_end: '' };
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to create payroll run.' ) );
                    }
                    this.submitting = false;
                },
                error: (error) => {
                    nsSnackBar.error( error.message || __( 'An error occurred.' ) );
                    this.submitting = false;
                },
            });
        },
    },
};
</script>
