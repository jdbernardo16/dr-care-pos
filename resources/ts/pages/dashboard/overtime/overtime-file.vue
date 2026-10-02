<template>
    <div id="overtime-file" class="px-4 max-w-lg mx-auto">
        <div class="ns-box rounded-lg shadow">
            <div class="ns-box-header p-2 border-b">
                <h3 class="font-semibold text-primary">
                    {{ __("File Overtime Request") }}
                </h3>
            </div>
            <div class="ns-box-body p-4">
                <div class="mb-4">
                    <label
                        class="block text-xs mb-1 font-semibold text-secondary"
                    >
                        {{ __("Date") }}
                    </label>
                    <input
                        v-model="form.date"
                        type="date"
                        class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label
                            class="block text-xs mb-1 font-semibold text-secondary"
                        >
                            {{ __("Start Time") }}
                        </label>
                        <input
                            v-model="form.start_time"
                            type="time"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-xs mb-1 font-semibold text-secondary"
                        >
                            {{ __("End Time") }}
                        </label>
                        <input
                            v-model="form.end_time"
                            type="time"
                            class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full"
                        />
                    </div>
                </div>
                <div class="mb-4">
                    <label
                        class="block text-xs mb-1 font-semibold text-secondary"
                    >
                        {{ __("Reason") }}
                    </label>
                    <textarea
                        v-model="form.reason"
                        class="outline-none rounded border-2 border-gray-200 bg-surface p-2 text-sm w-full"
                        rows="3"
                        :placeholder="__('Why is overtime needed?')"
                    ></textarea>
                </div>

                <div
                    v-if="computedHours > 0"
                    class="mb-4 p-3 bg-blue-50 rounded border border-blue-200 text-sm"
                >
                    <span class="font-semibold">
                        {{ __("Overtime Hours") }}:
                    </span>
                    {{ computedHours }}
                </div>

                <div class="flex gap-2">
                    <button
                        @click="submitRequest"
                        :disabled="submitting || !isFormValid"
                        class="rounded bg-green-500 text-white shadow py-2 px-6 text-sm font-semibold hover:bg-green-600 transition-colors duration-200 disabled:opacity-50"
                    >
                        {{
                            submitting
                                ? __("Submitting...")
                                : __("Submit Request")
                        }}
                    </button>
                </div>

                <div
                    v-if="lastRequest"
                    class="mt-4 p-3 rounded text-sm"
                    :class="requestStatusClass"
                >
                    <strong>{{ __("Status") }}:</strong>
                    {{ requestStatusLabel }}
                </div>
            </div>
        </div>

        <!-- My Recent Requests -->
        <div class="ns-box rounded-lg shadow mt-4">
            <div class="ns-box-header p-2 border-b">
                <h3 class="font-semibold text-primary">
                    {{ __("My Recent Requests") }}
                </h3>
            </div>
            <div class="ns-box-body p-4">
                <div class="ns-table-container text-center">
                    <table class="ns-table">
                        <thead>
                            <tr>
                                <th>{{ __("Date") }}</th>
                                <th>{{ __("Hours") }}</th>
                                <th>{{ __("Reason") }}</th>
                                <th>{{ __("Status") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="myRequests.length === 0">
                                <td
                                    colspan="4"
                                    class="text-center text-secondary py-4"
                                >
                                    {{ __("No requests yet.") }}
                                </td>
                            </tr>
                            <tr v-for="req in myRequests" :key="req.id">
                                <td>{{ formatDate(req.date) }}</td>
                                <td>{{ req.total_hours }}</td>
                                <td class="max-w-xs truncate">
                                    {{ req.reason }}
                                </td>
                                <td>
                                    <span
                                        :class="statusClass(req.status)"
                                        class="px-2 py-1 rounded-full text-white text-xs font-semibold"
                                    >
                                        {{ statusLabel(req.status) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";

export default {
    name: "nsOvertimeFile",
    data() {
        return {
            form: {
                date: "",
                start_time: "",
                end_time: "",
                reason: "",
            },
            myRequests: [],
            lastRequest: null,
            submitting: false,
        };
    },
    computed: {
        isFormValid() {
            return (
                this.form.date &&
                this.form.start_time &&
                this.form.end_time &&
                this.form.reason
            );
        },
        computedHours() {
            if ( ! this.form.date || ! this.form.start_time || ! this.form.end_time ) return 0;
            const start = window.moment( `${this.form.date} ${this.form.start_time}`, 'YYYY-MM-DD HH:mm' );
            const end = window.moment( `${this.form.date} ${this.form.end_time}`, 'YYYY-MM-DD HH:mm' );
            if ( end <= start ) return 0;
            return ( end.diff( start, 'minutes' ) / 60 ).toFixed( 2 );
        },
        requestStatusClass() {
            if (!this.lastRequest) return "";
            return (
                {
                    pending:
                        "bg-yellow-50 text-yellow-800 border border-yellow-200",
                    approved:
                        "bg-green-50 text-green-800 border border-green-200",
                    rejected: "bg-red-50 text-red-800 border border-red-200",
                }[this.lastRequest.status] || ""
            );
        },
        requestStatusLabel() {
            if (!this.lastRequest) return "";
            return (
                {
                    pending: __("Pending Approval"),
                    approved: __("Approved"),
                    rejected: __("Rejected"),
                }[this.lastRequest.status] || this.lastRequest.status
            );
        },
    },
    mounted() {
        this.loadMyRequests();
        // Pre-fill today's date
        this.form.date = window.moment().format("YYYY-MM-DD");
    },
    methods: {
        __,
        formatDate(value) {
            if (!value) return "—";
            return window.moment(value).format("YYYY-MM-DD");
        },
        statusClass(status) {
            return (
                {
                    pending: "bg-yellow-500",
                    approved: "bg-green-500",
                    rejected: "bg-red-500",
                }[status] || "bg-gray-500"
            );
        },
        statusLabel(status) {
            return (
                {
                    pending: __("Pending"),
                    approved: __("Approved"),
                    rejected: __("Rejected"),
                }[status] || status
            );
        },
        loadMyRequests() {
            nsHttpClient.get("/api/overtime/my-requests").subscribe({
                next: (result) => {
                    this.myRequests = result.data || [];
                },
                error: () => {},
            });
        },
        submitRequest() {
            this.submitting = true;

            // Build full datetime from date + time
            const payload = {
                date: this.form.date,
                start_time: `${this.form.date} ${this.form.start_time}`,
                end_time: `${this.form.date} ${this.form.end_time}`,
                reason: this.form.reason,
            };

            nsHttpClient.post('/api/overtime/requests', payload).subscribe({
                next: (result) => {
                    if (result.status === 'success') {
                        nsSnackBar.success( __( 'Overtime request filed!' ) );
                        this.lastRequest = result.data.request;
                        this.form = {
                            date: window.moment().format( 'YYYY-MM-DD' ),
                            start_time: '',
                            end_time: '',
                            reason: '',
                        };
                        this.loadMyRequests();
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed to submit.' ) );
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
