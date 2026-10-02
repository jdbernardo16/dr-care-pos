<template>
    <div id="payroll-list" class="px-4">
        <div class="flex -mx-2 mb-4">
            <div class="px-2">
                <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Start Date' ) }}</label>
                <input
                    v-model="filters.period_start"
                    type="date"
                    class="outline-none rounded border-2 border-blue-400 border-gray-200 bg-surface h-10 px-2 text-sm"
                    @change="loadRuns"
                />
            </div>
            <div class="px-2">
                <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'End Date' ) }}</label>
                <input
                    v-model="filters.period_end"
                    type="date"
                    class="outline-none rounded border-2 border-blue-400 border-gray-200 bg-surface h-10 px-2 text-sm"
                    @change="loadRuns"
                />
            </div>
            <div class="px-2">
                <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Status' ) }}</label>
                <select
                    v-model="filters.status"
                    class="outline-none rounded border-2 border-blue-400 border-gray-200 bg-surface h-10 px-2 text-sm pr-8"
                    @change="loadRuns"
                >
                    <option value="">{{ __( 'All Status' ) }}</option>
                    <option value="draft">{{ __( 'Draft' ) }}</option>
                    <option value="posted">{{ __( 'Posted' ) }}</option>
                    <option value="void">{{ __( 'Void' ) }}</option>
                </select>
            </div>
            <div class="px-2">
                <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Employee' ) }}</label>
                <select
                    v-model="filters.user_id"
                    class="outline-none rounded border-2 border-blue-400 border-gray-200 bg-surface h-10 px-2 text-sm pr-8"
                    @change="loadRuns"
                >
                    <option value="">{{ __( 'All Employees' ) }}</option>
                    <option
                        v-for="user in users"
                        :key="user.id"
                        :value="user.id"
                    >{{ user.username }}</option>
                </select>
            </div>
        </div>

        <div class="ns-table-container">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>{{ __( 'Employee' ) }}</th>
                        <th>{{ __( 'Period' ) }}</th>
                        <th class="text-right">{{ __( 'Hours' ) }}</th>
                        <th class="text-right">{{ __( 'Rate' ) }}</th>
                        <th class="text-right">{{ __( 'Gross Pay' ) }}</th>
                        <th>{{ __( 'Status' ) }}</th>
                        <th>{{ __( 'Created' ) }}</th>
                        <th>{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="8" class="text-center py-8 text-secondary">{{ __( 'Loading...' ) }}</td>
                    </tr>
                    <tr v-else-if="runs.length === 0">
                        <td colspan="8" class="text-center py-8 text-secondary">{{ __( 'No payroll runs found.' ) }}</td>
                    </tr>
                    <tr v-for="run in runs" :key="run.id">
                        <td>{{ run.user_username || run.user?.username || '—' }}</td>
                        <td>{{ formatDate( run.period_start ) }} — {{ formatDate( run.period_end ) }}</td>
                        <td class="text-right">{{ run.total_hours }}</td>
                        <td class="text-right">{{ run.hourly_rate }}</td>
                        <td class="text-right font-semibold">{{ run.gross_pay }}</td>
                        <td>
                            <span
                                :class="statusClass( run.status )"
                                class="px-2 py-1 rounded-full text-white text-xs font-semibold"
                            >{{ statusLabel( run.status ) }}</span>
                        </td>
                        <td class="text-sm text-secondary">{{ formatDateTime( run.created_at ) }}</td>
                        <td class="flex gap-1">
                            <a
                                :href="'/dashboard/payroll/' + run.id"
                                class="rounded bg-input-button shadow py-1 px-2 text-xs font-semibold text-fontcolor hover:bg-info-tertiary hover:text-white transition-colors duration-200"
                            >{{ __( 'View' ) }}</a>
                            <button
                                v-if="run.status === 'draft' && run.gross_pay > 0"
                                @click="postRun( run )"
                                class="rounded bg-green-500 shadow py-1 px-2 text-xs font-semibold text-white hover:bg-green-600 transition-colors duration-200"
                            >{{ __( 'Post' ) }}</button>
                            <button
                                v-if="run.status === 'posted'"
                                @click="voidRun( run )"
                                class="rounded bg-red-500 shadow py-1 px-2 text-xs font-semibold text-white hover:bg-red-600 transition-colors duration-200"
                            >{{ __( 'Void' ) }}</button>
                            <button
                                v-if="run.status === 'draft'"
                                @click="deleteDraft( run )"
                                class="rounded bg-gray-200 shadow py-1 px-2 text-xs font-semibold text-gray-700 hover:bg-red-500 hover:text-white transition-colors duration-200"
                            >{{ __( 'Delete' ) }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex justify-end mt-4 gap-1" v-if="lastPage > 1">
            <button
                v-for="page in lastPage"
                :key="page"
                @click="loadRuns( page )"
                :class="currentPage === page ? 'bg-blue-500 text-white' : 'bg-input-button shadow text-fontcolor'"
                class="rounded w-8 h-8 text-sm font-semibold"
            >{{ page }}</button>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'nsPayrollList',
    data() {
        return {
            runs: [],
            users: [],
            loading: false,
            currentPage: 1,
            lastPage: 1,
            filters: { period_start: '', period_end: '', status: '', user_id: '' },
        };
    },
    mounted() {
        this.loadUsers();
        this.loadRuns();
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
                next: (result) => { this.users = result; },
                error: (e) => { console.error( e ); },
            });
        },
        loadRuns( page = 1 ) {
            this.loading = true;
            this.currentPage = page;

            let url = `/api/payroll/runs?page=${page}`;
            if ( this.filters.period_start ) url += `&period_start=${this.filters.period_start}`;
            if ( this.filters.period_end ) url += `&period_end=${this.filters.period_end}`;
            if ( this.filters.status ) url += `&status=${this.filters.status}`;
            if ( this.filters.user_id ) url += `&user_id=${this.filters.user_id}`;

            nsHttpClient.get( url ).subscribe({
                next: (result) => {
                    if ( result.data ) {
                        this.runs = result.data;
                        this.lastPage = result.last_page || 1;
                    } else if ( Array.isArray( result ) ) {
                        this.runs = result;
                    } else {
                        this.runs = [];
                    }
                    this.loading = false;
                },
                error: (e) => {
                    console.error( e );
                    this.runs = [];
                    this.loading = false;
                },
            });
        },
        postRun( run ) {
            if ( ! confirm( __( 'Post this payroll run? This will create an accounting entry.' ) ) ) return;

            nsHttpClient.post( `/api/payroll/runs/${run.id}/post`, {} ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Payroll run posted.' ) );
                        this.loadRuns( this.currentPage );
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to post.' ) );
                    }
                },
                error: (error) => { nsSnackBar.error( error.message || __( 'An error occurred.' ) ); },
            });
        },
        voidRun( run ) {
            if ( ! confirm( __( 'Void this payroll run? The accounting entry will be removed.' ) ) ) return;

            nsHttpClient.post( `/api/payroll/runs/${run.id}/void`, {} ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Payroll run voided.' ) );
                        this.loadRuns( this.currentPage );
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to void.' ) );
                    }
                },
                error: (error) => { nsSnackBar.error( error.message || __( 'An error occurred.' ) ); },
            });
        },
        deleteDraft( run ) {
            if ( ! confirm( __( 'Delete this draft payroll run? This cannot be undone.' ) ) ) return;

            nsHttpClient.delete( `/api/payroll/runs/${run.id}` ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Draft deleted.' ) );
                        this.loadRuns( this.currentPage );
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to delete.' ) );
                    }
                },
                error: (error) => { nsSnackBar.error( error.message || __( 'An error occurred.' ) ); },
            });
        },
        statusClass( status ) {
            return {
                draft: 'bg-gray-500',
                posted: 'bg-green-500',
                void: 'bg-red-500',
            }[ status ] || 'bg-gray-500';
        },
        statusLabel( status ) {
            return { draft: __( 'Draft' ), posted: __( 'Posted' ), void: __( 'Void' ) }[ status ] || status;
        },
    },
};
</script>
