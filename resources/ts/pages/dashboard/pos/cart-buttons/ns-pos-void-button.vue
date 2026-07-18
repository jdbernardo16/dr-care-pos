<template>
    <div @click="voidOngoingOrder( order )" id="void-button" class="flex-shrink-0 w-1/4 flex items-center font-bold cursor-pointer justify-center flex-auto">
        <i class="mr-2 text-xl las la-trash"></i> 
        <span class="text-base">{{ __( 'Void' ) }}</span>
    </div>
</template>
<script>
export default {
    props: [ 'order', 'settings' ],
    methods: {
        __,
        voidOngoingOrder() {
            POS.voidOrder( this.order );
        },
    }
}
</script>
