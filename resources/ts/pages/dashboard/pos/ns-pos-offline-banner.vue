<script lang="ts">
import { __ } from "~/libraries/lang";
import { getOfflineContext, initializeOfflineMode } from "~/libraries/offline/offline";

export default {
    name: "ns-pos-offline-banner",
    data() {
        return {
            status: "online",
            snapshotAge: null,
            subscription: null,
        };
    },
    async mounted() {
        /**
         * The banner mounts before the POS page (and therefore before
         * initializeOfflineMode has been called), so we wait for the
         * shared initialization to resolve when no context exists yet.
         */
        const context =
            getOfflineContext() ?? ( await initializeOfflineMode().catch( () => null ) );

        if ( ! context ) {
            return;
        }

        this.subscription = context.connectivity.status$.subscribe( ( status ) => {
            this.status = status;

            if ( status === "offline" ) {
                this.loadSnapshotAge( context );
            }
        } );
    },
    unmounted() {
        this.subscription?.unsubscribe();
    },
    methods: {
        __,
        async loadSnapshotAge( context ) {
            const meta = await context.database.getMeta( "snapshot" );

            if ( meta?.fetched_at ) {
                this.snapshotAge = Math.round( ( Date.now() - meta.fetched_at ) / 60000 );
            }
        },
    },
};
</script>
<template>
    <div
        v-if="status === 'offline'"
        id="ns-pos-offline-banner"
        class="flex items-center justify-center gap-2 px-3 py-2 text-sm font-semibold text-white bg-red-600"
    >
        <i class="las la-unlink"></i>
        <span>{{ __( "OFFLINE MODE" ) }} —</span>
        <span>{{ __( "You can browse the cached catalog; sales are disabled until the connection is back." ) }}</span>
        <span v-if="snapshotAge !== null">
            ({{ __( "catalog cached" ) }} {{ snapshotAge }} min ago)
        </span>
    </div>
</template>
