<template>
    <div id="overtime-list" class="px-4">
        <div class="flex -mx-2 mb-4">
            <div class="px-2">
                <select
                    v-model="filters.status"
                    class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm pr-8"
                    @change="loadRequests"
                >
                    <option value="">{{ __( 'All Status' ) }}</option>
                    <option value="pending">{{ __( 'Pending' ) }}</option>
                    <option value="approved">{{ __( 'Approved' ) }}</option>
                    <option value="rejected">{{ __( 'Rejected' ) }}</option>
                </select>
            </div>
            <div class="px-2">
                <input
                    v-model="filters.start_date"
                    type="date"
                    class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm"
                    @change="loadRequests"
                />
            </div>
            <div class="px-2">
                <input
                    v-model="filters.end_date"
                    type="date"
                    class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm"
                    @change="loadRequests"
                />
            </div>
        </div>

        <div class="ns-table-container">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>{{ __( 'Employee' ) }}</th>
                        <th>{{ __( 'Date' ) }}</th>
                        <th>{{ __( 'Hours' ) }}</th>
                        <th>{{ __( 'Reason' ) }}</th>
                        <th>{{ __( 'Status' ) }}</th>
                        <th>{{ __( 'Filed' ) }}</th>
                        <th>{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="text-center py-8 text-secondary">{{ __( 'Loading...' ) }}</td>
                    </tr>
                    <tr v-else-if="requests.length === 0">
                        <td colspan="7" class="text-center py-8 text-secondary">{{ __( 'No overtime requests.' ) }}</td>
                    </tr>
                    <tr v-for="req in requests" :key="req.id">
                        <td>{{ req.user?.username || '—' }}</td>
                        <td>{{ formatDate( req.date ) }}</td>
                        <td>{{ req.total_hours }}</td>
                        <td class="max-w-xs truncate" :title="req.reason">{{ req.reason }}</td>
                        <td>
                            <span :class="statusClass( req.status )" class="px-2 py-1 rounded-full text-white text-xs font-semibold">
                                {{ statusLabel( req.status ) }}
                            </span>
                        </td>
                        <td class="text-sm text-secondary">{{ formatDateTime( req.created_at ) }}</td>
                        <td>
                            <template v-if="req.status === 'pending'">
                                <button
                                    @click="approve( req )"
                                    class="rounded bg-green-500 text-white shadow py-1 px-2 text-xs font-semibold hover:bg-green-600 transition-colors duration-200"
                                >{{ __( 'Approve' ) }}</button>
                                <button
                                    @click="reject( req )"
                                    class="rounded bg-red-500 text-white shadow py-1 px-2 text-xs font-semibold hover:bg-red-600 transition-colors duration-200 ml-1"
                                >{{ __( 'Reject' ) }}</button>
                            </template>
                            <span v-else-if="req.approver" class="text-xs text-secondary">
                                {{ __( 'by' ) }} {{ req.approver.username }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'nsOvertimeList',
    data() {
        return {
            requests: [],
            loading: false,
            filters: { status: 'pending', start_date: '', end_date: '' },
        };
    },
    mounted() {
        this.loadRequests();
    },
    methods: {
        __,
        formatDate( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD' );
        },
        formatDateTime( value ) {
            if ( ! value ) return '—';
            return window.moment( value ).format( 'YYYY-MM-DD HH:mm' );
        },
        statusClass( status ) {
            return { pending: 'bg-yellow-500', approved: 'bg-green-500', rejected: 'bg-red-500' }[ status ] || 'bg-gray-500';
        },
        statusLabel( status ) {
            return { pending: __( 'Pending' ), approved: __( 'Approved' ), rejected: __( 'Rejected' ) }[ status ] || status;
        },
        loadRequests() {
            this.loading = true;
            let url = '/api/overtime/requests?';
            if ( this.filters.status ) url += `status=${this.filters.status}&`;
            if ( this.filters.start_date ) url += `start_date=${this.filters.start_date}&`;
            if ( this.filters.end_date ) url += `end_date=${this.filters.end_date}&`;

            nsHttpClient.get( url ).subscribe({
                next: (result) => {
                    this.requests = result.data || [];
                    this.loading = false;
                },
                error: () => { this.requests = []; this.loading = false; },
            });
        },
        approve( req ) {
            nsHttpClient.post( `/api/overtime/requests/${req.id}/approve`, {} ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Overtime approved.' ) );
                        this.loadRequests();
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed.' ) );
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'Error.' ) ); },
            });
        },
        reject( req ) {
            const note = prompt( __( 'Reason for rejection (optional):' ) ) || '';
            nsHttpClient.post( `/api/overtime/requests/${req.id}/reject`, { admin_note: note } ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Overtime rejected.' ) );
                        this.loadRequests();
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'Error.' ) ); },
            });
        },
    },
};
</script>
