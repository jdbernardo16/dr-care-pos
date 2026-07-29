<template>
    <div class="h-full flex-auto flex flex-col" id="pos-container">
        <div class="flex overflow-hidden flex-shrink-0 px-2 pt-2">
            <div class="-mx-2 flex overflow-x-auto pb-1">
                <div class="header-buttons flex px-2 flex-shrink-0" :key="index" v-for="(component,index) of buttons">
                    <component :is="component"></component>
                </div>
            </div>
        </div>
        <div class="flex-auto overflow-hidden flex p-2">
            <div class="flex flex-auto overflow-hidden -m-2">
                <div :class="visibleSection === 'both' || visibleSection === 'summary' ? 'w-[62%]' : 'w-full'" class="p-2 flex overflow-hidden" v-if="[ 'both', 'grid', 'summary' ].includes( visibleSection )">
                    <ns-pos-grid></ns-pos-grid>
                </div>
                <div :class="visibleSection === 'both' || visibleSection === 'summary' ? 'w-[38%]' : 'w-full'" class="flex overflow-hidden p-2" v-if="[ 'both', 'cart', 'summary' ].includes( visibleSection )">
                    <ns-pos-cart v-if="visibleSection !== 'summary'"></ns-pos-cart>
                    <ns-pos-transaction-summary
                        v-else
                        :products="lastCompletedOrder?.products || []"
                        :payments="lastCompletedOrder?.payments || []"
                        :order-total="lastCompletedOrder?.total || 0"
                        :order="lastCompletedOrder"
                    ></ns-pos-transaction-summary>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import nsPosCart from './ns-pos-cart.vue';
import nsPosGrid from './ns-pos-grid.vue';
import nsPosTransactionSummary from './ns-pos-transaction-summary.vue';

export default {
    name: 'ns-pos',
    computed: {
        buttons() {
            return POS.header.buttons;
        }
    },
    mounted() {
        this.visibleSectionSubscriber   =   POS.visibleSection.subscribe( section => {
            if (section === 'summary') {
                const order = POS.lastCompletedOrder ? POS.lastCompletedOrder.getValue() : null;
                this.lastCompletedOrder = order;
            }
            this.visibleSection    =   section;
        });

        /**
         * Assuming all vue component
         * has been loaded
         */
        const loader    =   document.getElementById( 'loader' );
        loader.classList.remove( 'fade-in-entrance' );
        loader.classList.add( 'fade-out-exit' );
        
        setTimeout( () => {
            loader.remove();
            POS.reset();
        }, 500 ); 
        
    },
    unmounted() {
        this.visibleSectionSubscriber.unsubscribe();
    },
    data() {
        return {
            visibleSection: null,
            visibleSectionSubscriber: null,
            lastCompletedOrder: null,
        }
    },
    components: {
        nsPosCart,
        nsPosGrid,
        nsPosTransactionSummary,
    }
}
</script>