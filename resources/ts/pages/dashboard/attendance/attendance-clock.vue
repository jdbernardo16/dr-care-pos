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
        <div class="mb-6">
            <span
                :class="
                    isClockedIn
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500'
                "
                class="px-4 py-2 rounded-full text-sm font-semibold"
            >
                {{ isClockedIn ? __("Clocked In") : __("Not Clocked In") }}
            </span>
        </div>
        <div class="mb-4">
            <textarea
                v-model="note"
                :placeholder="__('Add a note (optional)...')"
                class="ns-input w-full border rounded p-2 text-sm text-primary bg-surface"
                rows="2"
            ></textarea>
        </div>
        <button
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
        <div v-if="lastRecord" class="mt-6 text-left border-t pt-4">
            <h4 class="font-semibold text-secondary mb-2">
                {{ __("Last Record") }}
            </h4>
            <div class="grid grid-cols-2 gap-2 text-sm">
                <div>
                    <span class="text-secondary">{{ __("Clock In") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ formatDateTime(lastRecord.clock_in_at) }}
                    </span>
                </div>
                <div>
                    <span class="text-secondary">{{ __("Clock Out") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ formatDateTime(lastRecord.clock_out_at) }}
                    </span>
                </div>
                <div v-if="lastRecord.total_hours">
                    <span class="text-secondary">{{ __("Total Hours") }}:</span>
                    <span class="font-semibold ml-1">
                        {{ lastRecord.total_hours }}
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
            loading: false,
            note: "",
            lastRecord: null,
            currentTime: "",
            currentDate: "",
            clockInterval: null,
        };
    },
    mounted() {
        this.fetchStatus();
        this.startClock();
    },
    beforeUnmount() {
        if (this.clockInterval) {
            clearInterval(this.clockInterval);
        }
    },
    methods: {
        __,
        formatDateTime(dateString) {
            if (!dateString) return __("N/A");
            return window.moment(dateString).format("YYYY-MM-DD HH:mm:ss");
        },
        startClock() {
            this.updateTime();
            this.clockInterval = setInterval(this.updateTime, 1000);
        },
        updateTime() {
            const now = window.moment();
            this.currentTime = now.format("HH:mm:ss");
            this.currentDate = now.format("dddd, MMMM D, YYYY");
        },
        async fetchStatus() {
            try {
                const response = await new Promise((resolve, reject) => {
                    nsHttpClient
                        .get("/api/attendance/current-status")
                        .subscribe({
                            next: (response) => resolve(response),
                            error: (error) => reject(error),
                        });
                });
                this.isClockedIn = response.data.is_clocked_in;
                this.lastRecord = response.data.record;
            } catch (error) {
                nsSnackBar.error(
                    error.message || __("Unable to fetch attendance status."),
                );
            }
        },
        async toggleClock() {
            this.loading = true;
            try {
                if (this.isClockedIn) {
                    const response = await new Promise((resolve, reject) => {
                        nsHttpClient
                            .post("/api/attendance/clock-out", {
                                note: this.note,
                            })
                            .subscribe({
                                next: (response) => resolve(response),
                                error: (error) => reject(error),
                            });
                    });
                    this.isClockedIn = false;
                    this.lastRecord = response.data.attendance;
                    nsSnackBar.success(
                        response.message || __("Clocked out successfully."),
                    );
                } else {
                    const response = await new Promise((resolve, reject) => {
                        nsHttpClient
                            .post("/api/attendance/clock-in", {
                                note: this.note,
                            })
                            .subscribe({
                                next: (response) => resolve(response),
                                error: (error) => reject(error),
                            });
                    });
                    this.isClockedIn = true;
                    this.lastRecord = response.data.attendance;
                    nsSnackBar.success(
                        response.message || __("Clocked in successfully."),
                    );
                }
                this.note = "";
            } catch (error) {
                nsSnackBar.error(error.message || __("An error occurred."));
            } finally {
                this.loading = false;
            }
        },
    },
};
</script>
