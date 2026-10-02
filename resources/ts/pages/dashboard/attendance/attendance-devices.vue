<template>
    <div class="ns-attendance-devices ns-box rounded-lg border shadow bg-surface p-6 max-w-3xl mx-auto my-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-primary">{{ __( "Clock-in Devices" ) }}</h2>
            <button
                @click="generateCode"
                :disabled="generating"
                class="bg-blue-500 hover:bg-blue-600 text-white transition-colors duration-200 px-4 py-2 rounded-lg text-sm font-semibold"
            >
                {{ generating ? __( "Generating..." ) : __( "Generate Enrollment Code" ) }}
            </button>
        </div>

        <div v-if="enrollmentCode" class="mb-4 border border-blue-300 bg-blue-50 rounded-lg p-4">
            <h3 class="font-bold text-blue-700 mb-1">{{ __( "Enrollment Code" ) }}</h3>
            <p class="text-3xl font-mono font-bold text-blue-800 tracking-widest mb-1">{{ enrollmentCode }}</p>
            <p class="text-xs text-blue-600">{{ __( "Expires" ) }}: {{ formatDateTime( expiresAt ) }}</p>
            <p class="text-xs text-blue-600">{{ __( "Enter this on the tablet to register it. Single use." ) }}</p>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b text-secondary">
                    <th class="text-left py-2">{{ __( "Device" ) }}</th>
                    <th class="text-left py-2">{{ __( "Enrolled" ) }}</th>
                    <th class="text-left py-2">{{ __( "Last Used" ) }}</th>
                    <th class="text-left py-2">{{ __( "Status" ) }}</th>
                    <th class="text-right py-2">{{ __( "Actions" ) }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="devices.length === 0">
                    <td colspan="5" class="py-6 text-center text-secondary">
                        {{ __( "No devices enrolled yet." ) }}
                    </td>
                </tr>
                <tr v-for="device in devices" :key="device.id" class="border-b">
                    <td class="py-2">
                        <span class="font-semibold">{{ device.label }}</span>
                        <span class="block text-xs text-secondary">{{ device.device_id }}</span>
                    </td>
                    <td class="py-2 text-secondary">{{ formatDateTime( device.enrolled_at ) }}</td>
                    <td class="py-2 text-secondary">{{ device.last_used_at ? formatDateTime( device.last_used_at ) : __( "Never" ) }}</td>
                    <td class="py-2">
                        <span
                            :class="device.active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            class="px-2 py-1 rounded-full text-xs font-semibold"
                        >
                            {{ device.active ? __( "Active" ) : __( "Deactivated" ) }}
                        </span>
                    </td>
                    <td class="py-2 text-right">
                        <button
                            @click="toggleDevice( device )"
                            class="text-blue-500 hover:text-blue-700 mr-3 cursor-pointer"
                        >
                            {{ device.active ? __( "Deactivate" ) : __( "Activate" ) }}
                        </button>
                        <button
                            @click="deleteDevice( device )"
                            class="text-red-500 hover:text-red-700 cursor-pointer"
                        >
                            {{ __( "Delete" ) }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";

export default {
    name: "nsAttendanceDevices",
    data() {
        return {
            devices: [],
            enrollmentCode: "",
            expiresAt: "",
            generating: false,
        };
    },
    mounted() {
        this.fetchDevices();
    },
    methods: {
        __,
        formatDateTime( dateString ) {
            if ( ! dateString ) return __( "N/A" );
            return window.moment( dateString ).format( "YYYY-MM-DD HH:mm:ss" );
        },
        async fetchDevices() {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .get( "/api/attendance/devices" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                this.devices = response.data || [];
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "Unable to load devices." ) );
            }
        },
        async generateCode() {
            this.generating = true;
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .post( "/api/attendance/generate-enrollment-code" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                this.enrollmentCode = response.data.code;
                this.expiresAt = response.data.expires_at;
                nsSnackBar.success( response.message || __( "Code generated." ) );
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            } finally {
                this.generating = false;
            }
        },
        async toggleDevice( device ) {
            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .post( "/api/attendance/devices/" + device.id + "/toggle" )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                nsSnackBar.success( response.message || __( "Device updated." ) );
                this.fetchDevices();
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            }
        },
        async deleteDevice( device ) {
            if ( ! confirm( __( "Remove this device? It will no longer be able to clock in." ) ) ) {
                return;
            }

            try {
                const response = await new Promise( ( resolve, reject ) => {
                    nsHttpClient
                        .delete( "/api/attendance/devices/" + device.id )
                        .subscribe( {
                            next: ( response ) => resolve( response ),
                            error: ( error ) => reject( error ),
                        } );
                } );
                nsSnackBar.success( response.message || __( "Device removed." ) );
                this.fetchDevices();
            } catch ( error ) {
                nsSnackBar.error( error.message || __( "An error occurred." ) );
            }
        },
    },
};
</script>