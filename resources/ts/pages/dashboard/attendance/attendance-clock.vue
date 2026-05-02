<template>
    <div
        class="ns-attendance-clock ns-box rounded-lg border shadow bg-surface p-6 max-w-md mx-auto text-center my-8"
    >
        <div class="text-5xl font-bold text-primary mb-2">
            {{ currentTime }}
        </div>
        <div class="text-lg text-secondary mb-6">
            {{ currentDate }}
        </div>

        <!-- Status Badge -->
        <div class="mb-4 flex justify-center gap-2">
            <span
                :class="statusBadgeClass"
                class="px-4 py-2 rounded-full text-sm font-semibold"
            >
                {{ statusLabel }}
            </span>
        </div>

        <!-- Today's Time Summary (when clocked in) -->
        <div v-if="isClockedIn && lastRecord" class="mb-4 grid grid-cols-3 gap-2 text-sm">
            <div class="bg-gray-50 rounded p-2">
                <span class="text-secondary block text-xs">{{ __("Hours") }}</span>
                <span class="font-bold text-primary">{{ todayTotalHours }}</span>
            </div>
            <div class="bg-gray-50 rounded p-2">
                <span class="text-secondary block text-xs">{{ __("Break") }}</span>
                <span class="font-bold text-primary">{{ todayBreakHours }}</span>
            </div>
            <div class="bg-gray-50 rounded p-2">
                <span class="text-secondary block text-xs">{{ __("Net") }}</span>
                <span class="font-bold text-primary">{{ todayNetHours }}</span>
            </div>
        </div>

        <!-- Quick OT Link -->
        <div class="mb-3">
            <a
                href="/dashboard/overtime/file"
                class="text-sm text-blue-500 hover:text-blue-700 underline"
            >{{ __( 'File Overtime Request' ) }}</a>
        </div>

        <!-- Note -->
        <div class="mb-4">
            <textarea
                v-model="note"
                :placeholder="__('Add a note (optional)...')"
                class="ns-input w-full border rounded p-2 text-sm text-primary bg-surface"
                rows="2"
            ></textarea>
        </div>

        <!-- Main Action: Clock In / Clock Out -->
        <button
            v-if="!isOnBreak"
            @click="toggleClock"
            :class="
                isClockedIn
                    ? 'bg-red-500 hover:bg-red-600'
                    : 'bg-green-500 hover:bg-green-600'
            "
            class="text-white transition-colors duration-200 px-8 py-4 rounded-lg text-xl font-bold w-full"
            :disabled="loading"
        >
            <span v-if="loading">{{ __("Processing...") }}</span>
            <span v-else>
                {{ isClockedIn ? __("Clock Out") : __("Clock In") }}
            </span>
        </button>

        <!-- Break In/Out Buttons (only when clocked in) -->
        <div v-if="isClockedIn" class="flex gap-2 mt-3">
            <button
                v-if="!isOnBreak"
                @click="toggleBreak"
                class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white transition-colors duration-200 px-4 py-3 rounded-lg text-base font-semibold"
                :disabled="breakLoading"
            >
                {{ breakLoading ? __("Processing...") : __("Break In") }}
            </button>
            <button
                v-if="isOnBreak"
                @click="toggleBreak"
                class="flex-1 bg-blue-500 hover:bg-blue-600 text-white transition-colors duration-200 px-4 py-3 rounded-lg text-base font-semibold"
                :disabled="breakLoading"
            >
                {{ breakLoading ? __("Processing...") : __("Break Out") }}
            </button>
        </div>

        <!-- Last Completed Record -->
        <div v-if="lastCompleted" class="mt-5 text-left border-t pt-4">
            <h4 class="font-semibold text-secondary mb-2">
                {{ __("Last Completed Shift") }}
            </h4>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <span class="text-secondary">{{ __("Clock In") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ formatDateTime(lastCompleted.clock_in_at) }}
                    </span>
                </div>
                <div>
                    <span class="text-secondary">{{ __("Clock Out") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ formatDateTime(lastCompleted.clock_out_at) }}
                    </span>
                </div>
                <div v-if="lastCompleted.total_hours">
                    <span class="text-secondary">{{ __("Total Hours") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ lastCompleted.total_hours }}
                    </span>
                </div>
                <div v-if="lastCompleted.break_hours">
                    <span class="text-secondary">{{ __("Break") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ lastCompleted.break_hours }}
                    </span>
                </div>
                <div v-if="lastCompleted.net_hours">
                    <span class="text-secondary">{{ __("Net Hours") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ lastCompleted.net_hours }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";

export default {
    name: "nsAttendanceClock",
    data() {
        return {
            isClockedIn: false,
            isOnBreak: false,
            loading: false,
            breakLoading: false,
            note: "",
            lastRecord: null,
            lastCompleted: null,
            currentTime: "",
            currentDate: "",
            clockInterval: null,
        };
    },
    computed: {
        statusBadgeClass() {
            if ( this.isOnBreak ) return 'bg-yellow-100 text-yellow-700';
            if ( this.isClockedIn ) return 'bg-green-100 text-green-700';
            return 'bg-gray-100 text-gray-500';
        },
        statusLabel() {
            if ( this.isOnBreak ) return __( 'On Break' );
            if ( this.isClockedIn ) return __( 'Clocked In' );
            return __( 'Not Clocked In' );
        },
        todayTotalHours() {
            if ( ! this.lastRecord ) return '0.00';
            const clockIn = window.moment( this.lastRecord.clock_in_at );
            const now = window.moment();
            return ( now.diff( clockIn, 'minutes' ) / 60 ).toFixed( 2 );
        },
        todayBreakHours() {
            if ( ! this.lastRecord || ! this.lastRecord.break_hours ) return '0.00';
            return this.lastRecord.break_hours;
        },
        todayNetHours() {
            if ( ! this.lastRecord ) return '0.00';
            const total = parseFloat( this.todayTotalHours );
            const brk = parseFloat( this.todayBreakHours );
            return ( total - brk ).toFixed( 2 );
        },
    },
    mounted() {
        this.fetchStatus();
        this.startClock();
    },
    beforeUnmount() {
        if ( this.clockInterval ) {
            clearInterval( this.clockInterval );
        }
    },
    methods: {
        __,
        formatDateTime( dateString ) {
            if ( ! dateString ) return __( "N/A" );
            return window.moment( dateString ).format( "YYYY-MM-DD HH:mm:ss" );
        },
        startClock() {
            this.updateTime();
            this.clockInterval = setInterval( this.updateTime, 1000 );
        },
        updateTime() {
            const now = window.moment();
            this.currentTime = now.format( "HH:mm:ss" );
            this.currentDate = now.format( "dddd, MMMM D, YYYY" );
        },
        async fetchStatus() {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .get( "/api/attendance/current-status" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                this.isClockedIn = response.data.is_clocked_in;
                this.isOnBreak = response.data.is_on_break;
                this.lastRecord = response.data.record;

                // Find the most recent completed shift (previous day)
                if ( ! this.isClockedIn ) {
                    this.fetchLastCompleted();
                }
            } catch ( error ) {
                nsSnackBar.error(
                    error.message || __( "Unable to fetch attendance status." ),
                );
            }
        },
        async fetchLastCompleted() {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .get( "/api/attendance/history?limit=1" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                if ( response.data && response.data.length > 0 ) {
                    this.lastCompleted = response.data[ 0 ];
                }
            } catch ( e ) {
                // silent
            }
        },
        async toggleClock() {
            this.loading = true;
            try {
                if ( this.isClockedIn ) {
                    const response = await new Promise( ( resolve, reject ) => {
                        nsHttpClient
                            .post( "/api/attendance/clock-out", {
                                note: this.note,
                            } )
                            .subscribe( {
                                next: ( response ) => resolve( response ),
                                error: ( error ) => reject( error ),
                            } );
                    } );
                    this.isClockedIn = false;
                    this.isOnBreak = false;
                    this.lastCompleted = response.data.attendance;
                    this.lastRecord = null;
                    nsSnackBar.success(
                        response.message || __( "Clocked out successfully." ),
                    );
                } else {
                    const response = await new Promise( ( resolve, reject ) => {
                        nsHttpClient
                            .post( "/api/attendance/clock-in", {
                                note: this.note,
                            } )
                            .subscribe( {
                                next: ( response ) => resolve( response ),
                                error: ( error ) => reject( error ),
                            } );
                    } );
                    this.isClockedIn = true;
                    this.isOnBreak = false;
                    this.lastRecord = response.data.attendance;
                    nsSnackBar.success(
                        response.message || __( "Clocked in successfully." ),
                    );
                }
                this.note = "";
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            } finally {
                this.loading = false;
            }
        },
        async toggleBreak() {
            this.breakLoading = true;
            try {
                if ( this.isOnBreak ) {
                    const response = await new Promise( ( resolve, reject ) => {
                        nsHttpClient
                            .post( "/api/attendance/break-out", {} )
                            .subscribe( {
                                next: ( response ) => resolve( response ),
                                error: ( error ) => reject( error ),
                            } );
                    } );
                    this.isOnBreak = false;
                    this.lastRecord = response.data.record;
                    nsSnackBar.success(
                        response.message || __( "Break ended." ),
                    );
                } else {
                    const response = await new Promise( ( resolve, reject ) => {
                        nsHttpClient
                            .post( "/api/attendance/break-in", {
                                note: this.note,
                            } )
                            .subscribe( {
                                next: ( response ) => resolve( response ),
                                error: ( error ) => reject( error ),
                            } );
                    } );
                    this.isOnBreak = true;
                    this.lastRecord = response.data.record;
                    nsSnackBar.success(
                        response.message || __( "Break started." ),
                    );
                }
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            } finally {
                this.breakLoading = false;
            }
        },
    },
};
</script>
