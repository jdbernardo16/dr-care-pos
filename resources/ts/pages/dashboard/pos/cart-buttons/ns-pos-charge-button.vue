<template>
    <div @click="payOrder()" id="charge-button" class="flex-shrink-0 flex items-center font-bold cursor-pointer justify-center flex-auto rounded-lg bg-primary text-white text-lg lg:text-xl h-14 mx-1">
        <i class="mr-2 text-xl las la-cash-register"></i> 
        <span class="text-base lg:text-lg">{{ __( 'Charge' ) }} {{ nsCurrency( order.total ) }}</span>
    </div>
</template>
<script lang="ts">
declare const POS;
declare const nsShortcuts;
declare const nsHotPress;
declare const __;

import { nsCurrency } from '~/filters/currency';

export default {
    props: [ 'order' ],
    methods: {
        __,
        nsCurrency,
        async payOrder() {
            POS.runPaymentQueue();
        },
    },
    mounted() {
         for( let shortcut in nsShortcuts ) {
            if ([ 'ns_pos_keyboard_payment' ].includes( shortcut ) ) {
                nsHotPress
                    .create( 'ns_pos_keyboard_payment' )
                    .whenNotVisible([ '.is-popup' ])
                    .whenPressed( nsShortcuts[ shortcut ] !== null ? nsShortcuts[ shortcut ].join( '+' ) : null, ( event ) => {
                        event.preventDefault();
                        this.payOrder();
                });
            }
        }
    },
    unmounted() {
        nsHotPress.destroy( 'ns_pos_keyboard_payment' );
    }
}
</script>
