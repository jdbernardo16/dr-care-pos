<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { Popup } from '~/libraries/popup';
import nsProcurementQuantityVue from '~/popups/ns-procurement-quantity.vue';
import nsPosConfirmPopupVue from '~/popups/ns-pos-confirm-popup.vue';
import nsPromptPopupVue from '~/popups/ns-prompt-popup.vue';
import { __ } from '~/libraries/lang';
import { forkJoin } from 'rxjs';
import { nsCurrency } from '~/filters/currency';
import nsSelectPopupVue from '~/popups/ns-select-popup.vue';

export default {
    name: 'ns-stock-adjustment',
    props: [ 'actions' ],
    data() {
        return {
            search: '',
            timeout: null,
            suggestions: [],
            products: [],
        }
    },
    mounted() {
        // ...
    },
    computed: {
        hasIncomplete() {
            return this.products.some( p => this.isIncomplete( p ) );
        },
        incompleteCount() {
            return this.products.filter( p => this.isIncomplete( p ) ).length;
        },
        hasProducts() {
            return this.products.length > 0;
        }
    },
    methods: {
        __,
        nsCurrency,
        searchProduct( argument ) {
            if ( argument.length > 0 ) {
                nsHttpClient.post( '/api/procurements/products/search-procurement-product', { argument })
                    .subscribe( result => {
                        if ( result.from === 'products' ) {
                            if ( result.products.length > 0 ) {
                                if ( result.products.length === 1 ) {
                                    this.addSuggestion( result.products[0] );
                                } else {
                                    this.suggestions    =   result.products;
                                }
                            } else {
                                this.closeSearch();
                                return nsSnackBar.error( __( 'Looks like no valid products matched the searched term.' ) );
                            }
                        } else if ( result.from === 'procurements' ) {
                            if ( result.product === null ) {
                                this.closeSearch();
                                return nsSnackBar.error( __( 'Looks like no valid products matched the searched term.' ) );
                            } else {
                                this.addProductToList( result.product );
                            }
                        }
                    })
            }
        },

        addProductToList( product ) {
            const exists    =   this.products
                .filter( __product => __product.procurement_product_id === product.id );

            if ( exists.length > 0 ) {
                this.closeSearch();
                return nsSnackBar.error( __( 'The product already exists on the table.' ) );
            }

            const finalProduct                  =   new Object;
            product.unit_quantity.unit          =   product.unit;
            finalProduct.selected               =   false;
            finalProduct.quantities             =   [ product.unit_quantity ];
            finalProduct.name                   =   product.name;
            finalProduct.adjust_unit            =   product.unit_quantity;

            finalProduct.adjust_quantity            =   null;
            finalProduct.adjust_action              =   '', // require user to choose operation explicitly
            finalProduct.adjust_reason              =   '',
            finalProduct.adjust_value               =   0;
            finalProduct.id                         =   product.product_id;
            finalProduct.accurate_tracking          =   1;
            finalProduct.available_quantity         =   product.available_quantity;
            finalProduct.procurement_product_id     =   product.id;
            finalProduct.procurement_history        =   [{
                label: `${product.procurement.name} (${product.available_quantity})`,
                value: product.id
            }]
 
            this.recalculateProduct( finalProduct );
            this.products.unshift( finalProduct );
            this.clearSearch();
        },

        addSuggestion( suggestion ) {
            const alreadyAdded    =   this.products
                .filter( product => product.id === suggestion.id ).length > 0;

            forkJoin([
                nsHttpClient.get( `/api/products/${suggestion.id}/units/quantities` ),
                // nsHttpClient.get( `/api/products/${suggestion.id}/procurements` )
            ]).subscribe( result => {
                    if ( result[0].length > 0 ) {

                        const defaultUnit = result[0].filter( unitQuantity => unitQuantity.unit.base_unit );

                        suggestion.selected                         =   false;
                        suggestion.quantities                       =   result[0];
                        suggestion.adjust_quantity                  =   null;
                        suggestion.adjust_action                    =   '',
                        suggestion.adjust_reason                    =   '',
                        suggestion.adjust_unit                      =   defaultUnit.length > 0 && ! alreadyAdded ? defaultUnit[0]: '',
                        suggestion.adjust_value                     =   0;
                        suggestion.procurement_product_id           =   0;

                        this.recalculateProduct( suggestion );
                        this.products.unshift( suggestion );
                        this.clearSearch();
                    } else {
                        return nsSnackBar.error( __( `This product doesn't have any stock to adjust.` ) );
                    }

                    if ( suggestion.accurate_tracking === 1 ) {
                        // suggestion.procurement_history      =   result[1].map( product => {
                        //     return {
                        //         label: `${product.procurement.name} (${product.available_quantity})`,
                        //         value: product.id
                        //     }
                        // })
                    }
                });
        },
        closeSearch() {
            this.$refs.searchField.select();
            this.suggestions    =   [];
        },
        clearSearch() {
            this.search         =   '';
            this.suggestions    =   [];
        },
        getBeforeQuantity( product ) {
            if ( product.accurate_tracking === 1 ) {
                return parseFloat( product.available_quantity ) || 0;
            }
            if ( product.adjust_unit && product.adjust_unit !== '' && product.adjust_unit.quantity !== undefined ) {
                return parseFloat( product.adjust_unit.quantity ) || 0;
            }
            return 0;
        },
        getAfterQuantity( product ) {
            if ( product.adjust_quantity === null || product.adjust_quantity === '' || product.adjust_quantity === undefined || ! product.adjust_action ) {
                return null;
            }
            const before = this.getBeforeQuantity( product );
            const qty = parseFloat( product.adjust_quantity );
            if ( isNaN( qty ) ) {
                return null;
            }
            if ( product.adjust_action === 'set' ) {
                return qty;
            }
            if ( product.adjust_action === 'added' ) {
                return before + qty;
            }
            if ([ 'deleted', 'defective', 'lost' ].includes( product.adjust_action ) ) {
                return before - qty;
            }
            return null;
        },
        isIncomplete( product ) {
            const qtyMissing = product.adjust_quantity === null || product.adjust_quantity === '' || product.adjust_quantity === undefined || isNaN( parseFloat( product.adjust_quantity ) );
            const actionMissing = ! product.adjust_action || product.adjust_action === '';
            const unitMissing = ! product.adjust_unit || product.adjust_unit === '';
            return qtyMissing || actionMissing || unitMissing;
        },
        getIncompleteProducts() {
            return this.products.filter( p => this.isIncomplete( p ) );
        },
        recalculateProduct( product ) {
            if ( product.adjust_unit !== '' ) {
                const qty = parseFloat( product.adjust_quantity );
                if ( isNaN( qty ) || product.adjust_quantity === null || product.adjust_quantity === '' ) {
                    product.adjust_value = 0;
                } else if ([ 'deleted', 'defective', 'lost' ].includes( product.adjust_action ) ) {
                    product.adjust_value        =   - ( qty * product.adjust_unit.sale_price );
                } else {
                    product.adjust_value        =   qty * product.adjust_unit.sale_price;
                }
            } else {
                product.adjust_value = 0;
            }
            this.$forceUpdate();
        },
        openQuantityPopup( product ) {
            const oldQuantity   =   product.quantity;
            const promise   =   new Promise( ( resolve, reject ) => {
                Popup.show( nsProcurementQuantityVue, { resolve, reject, quantity : product.adjust_quantity, action : product.adjust_action });
            });

            promise.then( result => {
                /**
                 * will check the stock if the adjustment
                 * reduce the stock.
                 */
                if ([ 'deleted', 'defective', 'lost' ].includes( product.adjust_action ) ) {
                    if ( product.accurate_tracking !== undefined && result.quantity > product.available_quantity ) {
                        return nsSnackBar.error( __( 'The specified quantity exceed the available quantity.' ) );
                    } else if ( result.quantity > product.adjust_unit.quantity ) {
                        return nsSnackBar.error( __( 'The specified quantity exceed the available quantity.' ) );
                    }
                }

                product.adjust_quantity     =   result.quantity;

                this.recalculateProduct( product );
            });
        },
        proceedStockAdjustment() {
            if ( this.products.length === 0 ) {
                return nsSnackBar.error( __( 'Unable to proceed as the table is empty.' ) );
            }

            const incomplete = this.getIncompleteProducts();
            if ( incomplete.length > 0 ) {
                const names = incomplete.map( p => p.name ).join( ', ' );
                return nsSnackBar.error( __( 'Please set quantity for all rows or remove them. Incomplete: ' ) + names );
            }

            // Validate quantities
            for ( const product of this.products ) {
                const qty = parseFloat( product.adjust_quantity );
                if ( isNaN( qty ) ) {
                    return nsSnackBar.error( __( 'Invalid quantity for product: ' ) + product.name );
                }
                if ( qty < 0 ) {
                    return nsSnackBar.error( __( 'The adjustment quantity can\'t be negative for the product ' ) + product.name );
                }
                if ( qty === 0 && product.adjust_action !== 'set' ) {
                    return nsSnackBar.error( __( 'Please provide a quantity greater than 0 for product: ' ) + product.name );
                }
                // Prevent negative stock for reduce actions
                if ([ 'deleted', 'defective', 'lost' ].includes( product.adjust_action ) ) {
                    const before = this.getBeforeQuantity( product );
                    if ( qty > before ) {
                        return nsSnackBar.error( __( 'The specified quantity exceed the available quantity for product: ' ) + product.name );
                    }
                }
            }

            // Build warning for destructive Set operations that reduce stock (wipe risk)
            const riskySetProducts = this.products.filter( p => {
                if ( p.adjust_action !== 'set' ) return false;
                const before = this.getBeforeQuantity( p );
                const after = this.getAfterQuantity( p );
                return after !== null && after < before;
            });

            let title = __( 'Confirm Your Action' );
            let message = __( 'The stock adjustment is about to be made. Would you like to confirm ?' );

            if ( riskySetProducts.length > 0 ) {
                const details = riskySetProducts.map( p => {
                    const before = this.getBeforeQuantity( p );
                    const after = this.getAfterQuantity( p );
                    return `${p.name} ${before} → ${after}`;
                }).join( ', ' );
                title = __( 'Warning: You are about to overwrite stock' );
                message = __( 'You are about to set: ' ) + details + __( '. Are you sure?' );
            } else {
                // Also show preview for all set operations (even non-destructive) + added/lost preview
                const allSet = this.products.filter( p => p.adjust_action === 'set' );
                if ( allSet.length > 0 ) {
                    const details = allSet.map( p => `${p.name} ${this.getBeforeQuantity(p)} → ${this.getAfterQuantity(p)}` ).join( ', ' );
                    message = __( 'You are about to set: ' ) + details + __( '. Would you like to confirm ?' );
                }
            }

            Popup.show( nsPosConfirmPopupVue, { 
                title,
                message,
                onAction: ( action ) => {
                    if ( action ) {
                        nsHttpClient.post( '/api/products/adjustments', { products: this.products })
                            .subscribe( result => {
                                nsSnackBar.success( result.message );
                                this.products       =   [];
                            }, error => {
                                nsSnackBar.error( error.message );
                            });
                    }
                }
            });
        },
        provideReason( product ) {
            const promise   =   new Promise( ( resolve, reject ) => {
                Popup.show( nsPromptPopupVue, {
                    title: __( 'More Details' ),
                    resolve,
                    reject,
                    message: __( 'Useful to describe better what are the reasons that leaded to this adjustment.' ),
                    input: product.adjust_reason,
                    onAction: ( input ) => {
                        if ( input !== false ) {
                            product.adjust_reason     =   input;
                        }
                    }
                });
            });

            promise.then( result => {
                nsSnackBar.success( __( 'The reason has been updated.' ) ).susbcribe();
            }).catch( error => {
                // ...
            })
        },
        async selectAdjustmentUnit( product ) {
            try {
                const result = await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        label: __( 'Select Unit' ),
                        resolve,
                        reject,
                        description: __( 'Select the unit that you want to adjust the stock with.' ),
                        name: 'adjust_unit',
                        options: product.quantities.map( quantity => {
                            return {
                                label: quantity.unit.name,
                                value: quantity
                            }
                        }),
                    });
                });

                const index             =   this.products.indexOf( product );
                const otherProducts     =   this.products.filter( ( product, _index ) => _index !== index );
                const exists    =   otherProducts
                    .filter( __product => __product.adjust_unit.unit.id === result.unit.id && __product.adjust_unit.product_id === result.product_id ).length > 0;

                if ( exists ) {
                    return nsSnackBar.error( __( 'A similar product with the same unit already exists.' ) );
                }
                
                product.adjust_unit    =   result;
                this.recalculateProduct( product );

            } catch ( error ) {
                // ...
            }
        },
        async selectProcurement( product ) {
            try {
                console.log( product );
                const result = await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        label: __( 'Select Procurement' ),
                        resolve,
                        reject,
                        description: __( 'Select the procurement that you want to adjust the stock with.' ),
                        name: 'adjust_procurement',
                        options: product.procurement_history,
                    });
                });

                product.procurement_product_id    =   result;
                
                this.recalculateProduct( product );
            } catch ( exception ) {
                throw exception;
            }
        },
        async selectStockAdjustementAction( product ) {
            try {
                const result = await new Promise( ( resolve, reject ) => {
                    Popup.show( nsSelectPopupVue, {
                        label: __( 'Select Action' ),
                        resolve,
                        reject,
                        description: __( 'Select the action that you want to perform on the stock.' ),
                        name: 'adjust_action',
                        options: this.actions,
                    });
                });

                product.adjust_action    =   result;
                this.recalculateProduct( product );
            } catch ( exception ) {
                throw exception;
            }
        },
        removeProduct( product ) {
            Popup.show( nsPosConfirmPopupVue, { 
                title: __( 'Confirm Your Action' ),
                message: __( 'Would you like to remove this product from the table ?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        const index     =   this.products.indexOf( product );
                        this.products.splice( index, 1 );
                    }
                }
            });
        },
        getAdjustActionLabel( action ) {
            const filtredAction =  this.actions.filter( _action => _action.value === action );

            if ( filtredAction.length > 0 ) {
                return filtredAction[0].label;
            }

            return __( 'N/A' );
        },
        deleteSelectedProducts() {
            Popup.show( nsPosConfirmPopupVue, { 
                title: __( 'Confirm Your Action' ),
                message: __( 'Would you like to remove the selected products from the table ?' ),
                onAction: ( action ) => {
                    if ( action ) {
                        this.products   =   this.products.filter( product => ! product.selected );
                    }
                }
            });
        }
    },
    watch: {
        search() {
            if ( this.search.length > 0 ) {
                clearTimeout( this.timeout );
                this.timeout    =   setTimeout( () => {
                    this.searchProduct( this.search );
                }, 500 );
            } else {
                this.closeSearch();
            }
        }
    }
}
</script>
<template>
    <div>
        <div class="input-field flex border-2 input-group rounded">
            <input @keyup.esc="closeSearch()" ref="searchField" v-model="search" type="text" class="p-2 flex-auto outline-hidden">
            <button class="px-3 py-2 rounded-none">{{ __( 'Search' ) }}</button>
        </div>
        <div class="h-0" v-if="suggestions.length > 0">
            <div class="">
                <ul class="shadow h-96 relative z-10 ns-vertical-menu zoom-in-entrance anim-duration-300 overflow-y-auto">
                    <li @click="addSuggestion( suggestion )" v-for="suggestion of suggestions" :key="suggestion.id" class="cursor-pointer border-b p-2 flex justify-between">
                        <span>{{ suggestion.name }}</span>
                    </li>
                </ul>
            </div>
        </div>
        <div v-if="hasIncomplete && products.length > 0" class="mt-2 p-2 bg-warning-primary border border-warning-secondary rounded text-sm text-fontcolor">
            <i class="las la-exclamation-triangle"></i>
            {{ __( 'Please set quantity for all rows or remove them.' ) }} 
            <span class="font-bold">{{ incompleteCount }} {{ __( 'incomplete' ) }}</span>
        </div>
        <div class="ns-box rounded shadow my-2 w-full ">
            <table class="table w-full ns-table">
                <thead class="border-b">
                    <tr>
                        <td class="p-2">{{ __( 'Product' ) }}</td>
                        <td width="110" class="p-2 text-center hidden md:table-cell">{{ __( 'Quantity' ) }}</td>
                        <td width="150" class="p-2 text-center hidden md:table-cell">{{ __( 'Stock Preview' ) }}</td>
                        <td width="110" class="p-2 text-center hidden md:table-cell">{{ __( 'Value' ) }}</td>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.length === 0">
                        <td class="p-2 border-b text-center hidden md:table-cell" colspan="6">{{ __( 'Search and add some products' ) }}</td>
                        <td class="p-2 border-b text-center table-cell md:hidden" colspan="4">{{ __( 'Search and add some products' ) }}</td>
                    </tr>
                    <tr :key="product.id" v-for="product of products" :class="isIncomplete(product) ? 'bg-warning-primary/30' : ''">
                        <td class="p-2 border">
                            <div class="flex justify-between">
                                <div>
                                    <h3 class="font-bold cursor-pointer" @click="product.selected  =   ! product.selected"><input type="checkbox" :checked="product.selected" name="" id=""> {{ product.name }} ({{ ( product.accurate_tracking === 1 ? product.available_quantity : product.adjust_unit.quantity ) || 0 }})</h3>
                                    <div class="text-xs mt-1">
                                        <template v-if="getAfterQuantity(product) !== null">
                                            <span :class="product.adjust_action === 'set' && getAfterQuantity(product) < getBeforeQuantity(product) ? 'text-error-tertiary font-bold' : 'text-fontcolor'">
                                                {{ __( 'Stock:' ) }} {{ getBeforeQuantity(product) }} → {{ getAfterQuantity(product) }}
                                            </span>
                                            <span v-if="product.adjust_action === 'set' && getAfterQuantity(product) < getBeforeQuantity(product)" class="ml-1 text-error-tertiary">
                                                ({{ getBeforeQuantity(product) - getAfterQuantity(product) }} {{ __( 'will be removed' ) }})
                                            </span>
                                            <span v-else-if="product.adjust_action === 'added'" class="ml-1 text-success-tertiary">
                                                (+{{ product.adjust_quantity }})
                                            </span>
                                            <span v-else-if="['deleted','defective','lost'].includes(product.adjust_action)" class="ml-1 text-error-tertiary">
                                                (-{{ product.adjust_quantity }})
                                            </span>
                                        </template>
                                        <template v-else>
                                            <span class="text-warning-tertiary font-semibold">
                                                {{ __( 'Stock:' ) }} {{ getBeforeQuantity(product) }} → — ({{ __( 'set quantity & operation' ) }})
                                            </span>
                                        </template>
                                    </div>
                                </div>
                                <div class="flex flex-col items-end">
                                    <span class="md:hidden cursor-pointer border-dashed border-b py-1 text-xs" :class="isIncomplete(product) ? 'border-warning-secondary text-warning-tertiary' : 'border-info-secondary'" @click="openQuantityPopup( product )">
                                        <template v-if="product.adjust_quantity === null || product.adjust_quantity === ''">
                                            {{ __( 'Tap to set quantity' ) }}
                                        </template>
                                        <template v-else>
                                            {{ __( 'Quantity' ) }} : {{ product.adjust_quantity }}
                                        </template>
                                    </span>
                                    <span class="md:hidden text-xs mt-1" v-if="getAfterQuantity(product) !== null" :class="product.adjust_action === 'set' && getAfterQuantity(product) < getBeforeQuantity(product) ? 'text-error-tertiary font-bold' : 'text-fontcolor-soft'">
                                        {{ getBeforeQuantity(product) }} → {{ getAfterQuantity(product) }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex -mx-2 md:flex-row flex-wrap">
                                <div class="px-2 w-1/2 md:w-auto" @click="selectAdjustmentUnit( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-info-secondary py-1">
                                        <span class="text-xs">{{ __( 'Unit:' ) }}</span>&nbsp;
                                        <span v-if="product.adjust_unit.unit" class="">{{ product.adjust_unit.unit.name }}</span>
                                        <span v-if="! product.adjust_unit.unit">{{ __( 'N/A' ) }}</span>
                                    </div>
                                </div>
                                <div class="px-2 w-1/2 md:w-auto" @click="selectStockAdjustementAction( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed py-1" :class="!product.adjust_action ? 'border-warning-secondary text-warning-tertiary font-bold' : 'border-info-secondary'">
                                        <span class="text-xs">{{ __( 'Operation:' ) }}</span>&nbsp;
                                        <span v-if="!product.adjust_action" class="">{{ __( 'Tap to choose' ) }}</span>
                                        <span v-else class="">
                                            {{ getAdjustActionLabel( product.adjust_action ) }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="product.accurate_tracking === 1" class="px-2 w-1/2 md:w-auto" @click="selectProcurement( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-info-secondary py-1">
                                        <span class="text-xs">{{ __( 'Procurement:' ) }}</span>&nbsp;
                                        <span class="">
                                            {{  product.procurement_history.filter( action => action.value === product.procurement_product_id )[0].label }}
                                        </span>
                                    </div>
                                </div>
                                <div class="px-2 w-1/2 md:w-auto" @click="provideReason( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-info-secondary py-1">
                                        <span class="text-xs">{{ __( 'Reason:' ) }}</span>&nbsp;
                                        <span v-if="product.adjust_reason">
                                            {{ __( 'Provided' ) }}
                                        </span>
                                        <span v-else="product.adjust_reason">
                                            {{ __( 'Not Provided' ) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="px-2 w-1/2 md:w-auto" @click="removeProduct( product )">
                                    <div class="text-xs cursor-pointer border-b border-dashed border-danger-secondary py-1">
                                        <span class="text-xs">{{ __( 'Remove' ) }}</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell" @click="openQuantityPopup( product )">
                            <div class="flex items-center justify-center cursor-pointer">
                                <span v-if="product.adjust_quantity === null || product.adjust_quantity === ''" class="border-b border-dashed border-warning-secondary text-warning-tertiary py-2 px-4 text-xs font-bold">{{ __( 'Tap to set' ) }}</span>
                                <span v-else class="border-b border-dashed border-info-secondary py-2 px-4">{{ product.adjust_quantity }}</span>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell">
                            <div class="flex items-center justify-center">
                                <template v-if="getAfterQuantity(product) !== null">
                                    <span class="py-2 px-2 text-sm" :class="product.adjust_action === 'set' && getAfterQuantity(product) < getBeforeQuantity(product) ? 'text-error-tertiary font-bold bg-error-primary rounded' : ''">{{ getBeforeQuantity(product) }} → {{ getAfterQuantity(product) }}</span>
                                </template>
                                <template v-else>
                                    <span class="py-2 px-2 text-sm text-warning-tertiary font-semibold">{{ getBeforeQuantity(product) }} → —</span>
                                </template>
                            </div>
                        </td>
                        <td class="p-2 border hidden md:table-cell">
                            <div class="flex items-center justify-center">
                                <span class="border-b border-dashed border-info-secondary py-2 px-4">{{ nsCurrency( product.adjust_value ) }}</span>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="ns-box-footer p-2 flex justify-between items-center">
                <div class="text-xs text-fontcolor-soft hidden md:block">
                    <span v-if="hasIncomplete" class="text-warning-tertiary font-bold">
                        <i class="las la-exclamation-circle"></i> {{ __( 'Please set quantity for all rows or remove them.' ) }}
                    </span>
                    <span v-else-if="hasProducts" class="text-success-tertiary">
                        <i class="las la-check-circle"></i> {{ __( 'All rows are ready to proceed.' ) }}
                    </span>
                </div>
                <div class="-mx-2 flex">
                    <div class="px-2">
                        <ns-button v-if="products.filter( p => p.selected ).length > 0" @click="deleteSelectedProducts()" type="error">
                            <div class="flex items-center justify-center h-6">
                                <i class="las la-trash"></i>
                            </div>
                            <span class="hidden md:inline-block">{{ __( 'Remove Selected' ) }}</span>
                        </ns-button>
                    </div>
                    <div class="px-2">
                        <ns-button @click="proceedStockAdjustment()" :disabled="hasIncomplete ? 'disabled' : false" type="info">{{ __( 'Proceed' ) }}</ns-button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>