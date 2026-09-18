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
            destroyed: false,
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

        if ( this.destroyed || ! context ) {
            return;
        }

        this.subscription = context.connectivity.status$.subscribe( ( status ) => {
            this.status = status;

            if ( status === "offline" ) {
                this.loadSnapshotAge( context );
            }
        } );

        /**
         * The component could have been unmounted while we were waiting
         * for the initialization to resolve.
         */
        if ( this.destroyed ) {
            this.subscription.unsubscribe();
            this.subscription = null;
        }
    },
    unmounted() {
        this.destroyed = true;
        this.subscription?.unsubscribe();
    },
    methods: {
        __,
        async loadSnapshotAge( context ) {
            try {
                const meta = await context.database.getMeta( "snapshot" );

                if ( meta?.fetched_at ) {
                    this.snapshotAge = Math.round( ( Date.now() - meta.fetched_at ) / 60000 );
                }
            } catch ( exception ) {
                console.warn( "Unable to read the offline snapshot age", exception );
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
        <span>{{ __( "You can browse the cached catalog; checkout is unavailable until the connection is back." ) }}</span>
        <span v-if="snapshotAge !== null">
            ({{ __( "catalog cached" ) }} {{ snapshotAge }} min ago)
        </span>
    </div>
</template>
