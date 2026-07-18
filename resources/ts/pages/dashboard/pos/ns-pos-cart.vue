<template>
    <div id="pos-cart" class="flex-auto flex flex-col">
        <div id="tools" class="flex pl-2 ns-tab" v-if="visibleSection === 'cart'">
            <div @click="switchTo( 'cart' )" class="flex cursor-pointer rounded-tl-lg rounded-tr-lg px-3 py-2 font-semibold active tab">
                <span>{{ __( 'Cart' ) }}</span>
                <span v-if="order" class="flex items-center justify-center text-sm rounded-full h-6 w-6 bg-green-500 text-white ml-1">{{ products.length }}</span>
            </div>
            <div @click="switchTo( 'grid' )" class="cursor-pointer rounded-tl-lg rounded-tr-lg px-3 py-2 border-t border-r border-l inactive tab">
                {{ __( 'Products' ) }}
            </div>
        </div>
        <div class="rounded shadow ns-tab-item flex-auto flex overflow-hidden">
            <div class="cart-table flex flex-auto flex-col overflow-hidden">
                <div id="cart-header" class="flex items-center px-3 py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="font-bold text-lg">{{ __( 'Ticket' ) }}</span>
                </div>
                <div id="cart-table-header" class="hidden"></div>
                <div id="cart-products-table" class="flex flex-auto flex-col overflow-auto">
                    
                    <!-- Loop Procuts On Cart -->

                    <div class="text-fontcolor flex" v-if="products.length === 0">
                        <div class="w-full text-center py-4 border-b">
                            <h3>{{ __( 'No products added...' ) }}</h3>
                        </div>
                    </div>

                    <div :product-index="index" :key="product.barcode" class="product-item px-3 py-3 border-b border-gray-100 dark:border-gray-800" v-for="(product, index) of products">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-base lg:text-lg text-gray-900 dark:text-gray-100 truncate">
                                    {{ product.name }}
                                    <span class="text-gray-600 dark:text-gray-400 font-normal text-sm" v-if="product.unit_name">&mdash; {{ product.unit_name }}</span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-sm lg:text-base text-gray-700 dark:text-gray-300">
                                    <span class="flex items-center gap-1">
                                        <span class="text-gray-600">&times;</span>
                                        <span class="font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">{{ displayProductQuantity(product) }}</span>
                                        <span class="text-gray-600">@</span>
                                        <span>{{ nsCurrency(product.unit_price) }}</span>
                                    </span>
                                    <button @click="removeUsingIndex(index)" class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="las la-trash-alt"></i>
                                    </button>
                                </div>
                                <div class="flex flex-wrap gap-2 mt-1 text-sm">
                                    <a @click="changeProductPrice(product)" class="text-blue-500 hover:text-blue-700 cursor-pointer border-b border-dashed border-blue-300">{{ __( 'Price' ) }}: {{ nsCurrency(product.unit_price) }}</a>
                                    <a v-if="allowQuantityModification(product)" @click="openDiscountPopup(product, 'product', index)" class="text-blue-500 hover:text-blue-700 cursor-pointer border-b border-dashed border-blue-300">{{ __( 'Discount' ) }} <span v-if="product.discount_type === 'percentage'">{{ product.discount_percentage }}%</span>: {{ nsCurrency(product.discount) }}</a>
                                </div>
                            </div>
                            <div class="text-base lg:text-lg font-bold text-gray-900 dark:text-gray-100 flex-shrink-0 ml-4">
                                {{ nsCurrency(product.total_price) }}
                            </div>
                        </div>
                    </div>

                </div>
                <div id="cart-products-summary" class="px-3 py-2 border-t border-gray-200 dark:border-gray-700">
                    <div class="space-y-1 text-sm lg:text-base">
                        <div class="flex justify-between text-gray-700 dark:text-gray-300">
                            <span>{{ __( 'Subtotal' ) }}</span>
                            <span>{{ nsCurrency(order.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-700 dark:text-gray-300" v-if="order.discount > 0">
                            <span>{{ __( 'Discount' ) }}<span v-if="order.discount_type === 'percentage'"> ({{ order.discount_percentage }}%)</span></span>
                            <span>-{{ nsCurrency(order.discount) }}</span>
                        </div>
                        <div class="flex justify-between text-gray-700 dark:text-gray-300" v-if="order.tax_value > 0">
                            <span>{{ __( 'Tax' ) }}</span>
                            <span>{{ nsCurrency(order.tax_value) }}</span>
                        </div>
                        <div class="flex justify-between font-bold text-lg lg:text-xl text-gray-900 dark:text-gray-100 border-t-2 border-gray-900 dark:border-gray-100 pt-2 mt-2">
                            <span>{{ __( 'Total' ) }}</span>
                            <span>{{ nsCurrency(order.total) }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center px-2 py-2 border-t border-gray-200 dark:border-gray-700 gap-1" id="cart-bottom-buttons">
                    <template v-for="button of (new Array(4)).fill()" v-if="Object.keys( cartButtons ).length === 0"> 
                        <div class="animate-pulse flex-shrink-0 w-1/4 flex items-center font-bold cursor-pointer justify-center border-r flex-auto">
                            <i class="mx-4 rounded-full bg-slate-300 h-5 w-5"></i>
                            <div class="text-lg mr-4 hidden md:flex md:flex-auto lg:text-2xl">
                                <div class="h-2 flex-auto bg-slate-200 rounded"></div>
                            </div>
                        </div>
                    </template>
                    <template v-for="component of cartButtons">
                        <component :is="component" :order="order" :settings="settings"></component>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
<script lang="ts">
import { nsSnackBar } from '~/bootstrap';
import { Popup } from '~/libraries/popup';
import { nsCurrency } from '~/filters/currency';
import { __ } from '~/libraries/lang';
import switchTo from "~/libraries/pos-section-switch";

import { ProductQuantityPromise } from "./queues/products/product-quantity";

import nsPosChargeButton from '~/pages/dashboard/pos/cart-buttons/ns-pos-charge-button.vue';
import nsPosMoreButton from '~/pages/dashboard/pos/cart-buttons/ns-pos-more-button.vue';

import nsPosDiscountPopupVue from '~/popups/ns-pos-discount-popup.vue';
import PosConfirmPopup from '~/popups/ns-pos-confirm-popup.vue';
import nsPosOrderTypePopupVue from '~/popups/ns-pos-order-type-popup.vue';
import nsPosCustomerPopupVue from '~/popups/ns-pos-customer-select-popup.vue';
import nsPosShippingPopupVue from '~/popups/ns-pos-shipping-popup.vue';
import nsPosNotePopupVue from '~/popups/ns-pos-note-popup.vue';
import nsPosTaxPopupVue from '~/popups/ns-pos-tax-popup.vue';
import nsPosCouponsLoadPopupVue from '~/popups/ns-pos-coupons-load-popup.vue';
import nsPosOrderSettingsVue from '~/popups/ns-pos-order-settings.vue';
import nsPosProductPricePopupVue from '~/popups/ns-pos-product-price-popup.vue';
import nsPosQuickProductPopupVue from '~/popups/ns-pos-quick-product-popup.vue';

declare const POS, nsShortcuts, nsHotPress, nsHooks;

import { ref, markRaw } from '@vue/reactivity';
import { Order } from '~/interfaces/order';
import { defineAsyncComponent, Ref } from 'vue';
import ActionPermissions from '~/libraries/action-permissions';

export default {
    name: 'ns-pos-cart',
    data: () => {
        return {
            popup : null,
            cartButtons: {},
            products: [],
            defaultCartButtons: {
                nsPosChargeButton: markRaw( nsPosChargeButton ),
                nsPosMoreButton: markRaw( nsPosMoreButton ),
            },
            visibleSection: null,
            visibleSectionSubscriber: null,
            cartButtonsSubscriber: null,

            optionsSubscriber: null,
            options: {},
            typeSubscribe: null,
            orderSubscribe: null,
            productSubscribe: null,
            settingsSubscribe: null,
            settings: {},
            types: [],
            order: ref({}) as Ref<Order>,
        }
    },
    computed: {
        selectedType() {
            return this.order.type ? this.order.type.label : 'N/A';
        },
        isVisible() {
            return this.visibleSection === 'cart';
        },
        customerName() {
            return this.order.customer ? `${this.order.customer.first_name || this.order.customer.last_name ? this.getFirstName() : this.getUserName() }` : 'N/A';
        },
        couponName() {
            return __( 'Apply Coupon' );
        }
    },
    mounted() {
        this.cartButtonsSubscriber  =   POS.cartButtons.subscribe( cartButtons => {
            this.cartButtons    =   cartButtons;
        });

        this.optionsSubscriber  =   POS.options.subscribe( options => {
            this.options    =   options;
        });

        this.typeSubscribe  =   POS.types.subscribe( types => this.types = types );

        this.orderSubscribe  =   POS.order.subscribe( order => {
            this.order   =   ref(order);
        });

        this.productSubscribe  =   POS.products.subscribe( products => {
            this.products = ref(products);
        });

        this.settingsSubscribe  =   POS.settings.subscribe( settings => {
            this.settings   =   ref(settings);
        });

        this.visibleSectionSubscriber   =   POS.visibleSection.subscribe( section => {
            this.visibleSection     =   ref(section);
        });

        /**
         * everytime the cart reset
         * we restore original buttons.
         */
        nsHooks.addAction( 'ns-before-cart-reset', 'ns-pos-cart-buttons', () => {
            POS.cartButtons.next( this.defaultCartButtons );
        });

        /**
         * let's register hotkeys
         */
        for( let shortcut in nsShortcuts ) {
            if ([ 
                    'ns_pos_keyboard_shipping', 
                ].includes( shortcut ) ) {
                nsHotPress
                    .create( 'ns_pos_keyboard_shipping' )
                    .whenNotVisible([ '.is-popup' ])
                    .whenPressed( nsShortcuts[ shortcut ] !== null ? nsShortcuts[ shortcut ].join( '+' ) : null, ( event ) => {
                        event.preventDefault();
                        this.openShippingPopup();
                });
            }

            if ([ 
                    'ns_pos_keyboard_note', 
                ].includes( shortcut ) ) {
                nsHotPress
                    .create( 'ns_pos_keyboard_note' )
                    .whenNotVisible([ '.is-popup' ])
                    .whenPressed( nsShortcuts[ shortcut ] !== null ? nsShortcuts[ shortcut ].join( '+' ) : null, ( event ) => {
                        event.preventDefault();
                        this.openNotePopup();
                });
            }
        }
    },
    unmounted() {
        this.visibleSectionSubscriber.unsubscribe();
        this.typeSubscribe.unsubscribe();
        this.orderSubscribe.unsubscribe();
        this.productSubscribe.unsubscribe();
        this.settingsSubscribe.unsubscribe();
        this.optionsSubscriber.unsubscribe();
        this.cartButtonsSubscriber.unsubscribe();

        nsHotPress.destroy( 'ns_pos_keyboard_shipping' );
        nsHotPress.destroy( 'ns_pos_keyboard_note' );
    },
    methods: {
        __,
        nsCurrency,

        switchTo,

        getFirstName() {
            return `${this.order.customer.first_name || ''} ${this.order.customer.last_name || '' }`;
        },

        getUserName() {
            return this.order.customer.username;
        },

        takeRandomClass() {
            return 'border-gray-500 bg-gray-400 text-white hover:bg-gray-500';
        },

        displayProductQuantity( product ) {
            const unitQuantity = product.$quantities();
            
            if ( unitQuantity && unitQuantity.is_weighable && unitQuantity.unit ) {
                const unitIdentifier = unitQuantity.unit.identifier;
                const supportedWeightUnits = ['kilogram', 'gram', 'miligram', 'tones'];
                
                // Check if the unit is a supported weight unit
                if ( supportedWeightUnits.includes( unitIdentifier ) ) {
                    try {
                        // Convert simple locale (e.g., 'en', 'fr') to full locale string (e.g., 'en-US', 'fr-FR')
                        const locale = ns.language || 'en';
                        const fullLocale = this.getFullLocale( locale );
                        
                        // Map unit identifiers to Intl unit names
                        const unitMap = {
                            'kilogram': 'kilogram',
                            'gram': 'gram',
                            'miligram': 'milligram',
                            'tones': 'metric-ton'
                        };
                        
                        const intlUnit = unitMap[ unitIdentifier ] || unitIdentifier;
                        
                        // Format the weight using Intl.NumberFormat
                        const formatter = new Intl.NumberFormat( fullLocale, {
                            style: 'unit',
                            unit: intlUnit,
                            unitDisplay: 'short',
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 3
                        });
                        
                        return formatter.format( product.quantity );
                    } catch ( error ) {
                        // Fallback to default behavior if formatting fails
                        console.warn( 'Failed to format weight quantity:', error );
                        return product.quantity;
                    }
                }
            }

            return product.quantity;
        },

        /**
         * Convert simple locale code to full locale string for Intl API
         * Based on languages defined in config/nexopos.php
         * e.g., 'en' -> 'en-US', 'fr' -> 'fr-FR'
         */
        getFullLocale( locale ) {
            // Map language codes from config/nexopos.php to full locale strings
            // Supported: en, de, fr, es, it, id, ar, pt, tr, km, vi, sq
            const localeMap = {
                'en': 'en-US',    // English
                'de': 'de-DE',    // Deutsch
                'fr': 'fr-FR',    // Français
                'es': 'es-ES',    // Espanol
                'it': 'it-IT',    // Italian
                'id': 'id-ID',    // Indonesian
                'ar': 'ar-SA',    // Arabic
                'pt': 'pt-PT',    // Portuguese
                'tr': 'tr-TR',    // Türkçe
                'km': 'km-KH',    // ភាសាខ្មែរ (Khmer)
                'vi': 'vi-VN',    // Vietnamese
                'sq': 'sq-AL'     // Shqiptare (Albanian)
            };
            
            return localeMap[ locale ] || `${locale}-${locale.toUpperCase()}`;
        },

        async openAddQuickProduct() {
            /**
             * We'll check if the user has the right to add a quick product.
             */
            await ActionPermissions.canProceed( 'nexopos.cart.products' );
            
            try {
                const promise   =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosQuickProductPopupVue, { resolve, reject })
                });
            } catch( exception ) {
                // ...
            }
        },

        summarizeCoupons() {
            const coupons   =   this.order.coupons.map( coupon => coupon.value );

            if ( coupons.length > 0 ) {
                return coupons.reduce( ( before, after ) => before + after );
            }

            return 0;
        },

        async changeProductPrice( product ) {
            // if ( ! this.settings.edit_purchase_price ) {
            //     return nsSnackBar.error( __( `You don't have the right to edit the purchase price.` ) );
            // }

            if ( product.product_type === 'dynamic' ) {
                return nsSnackBar.error( __( 'Dynamic product can\'t have their price updated.' ) );
            }

            if ( this.settings.unit_price_editable ) {
                try {
                    /**
                     * We'll check if the user has the right to edit a 
                     * purchase price of a product.
                     */
                    await ActionPermissions.canProceed( 'nexopos.cart.product-price' );

                    const newPrice  =   await new Promise( ( resolve, reject ) => {
                        return Popup.show( nsPosProductPricePopupVue, { product: Object.assign({}, product ), resolve, reject })
                    });

                    const quantities  =   {
                        ...product.$quantities(), 
                        ...{
                            custom_price_edit : newPrice,
                            custom_price_gross: newPrice,
                            custom_price_net: newPrice
                        }
                    }

                    product.$quantities     =   () => quantities;

                    /**
                     * We need to change the price mode
                     * to avoid restoring the original prices.
                     */
                    product.mode    =   'custom';
                                        
                    POS.recomputeProducts( POS.products.getValue() );
                    POS.refreshCart();

                    return nsSnackBar.success( __( 'The product price has been updated.' ) );
                } catch( exception ) {
                    if ( exception !== false ) {
                        nsSnackBar.error( exception );
                        throw exception;
                    }
                }
            } else {
                return nsSnackBar.error( __( 'The editable price feature is disabled.' ) );
            }
        },

        async selectCoupon() {
            /**
             * We'll check if the user has the right to manage coupons.
             */
            await ActionPermissions.canProceed( 'nexopos.cart.coupons' );

            try {
                const response  =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosCouponsLoadPopupVue, { resolve, reject })
                })
            } catch( exception ) {
                // something happened
            }
        },

        async defineOrderSettings() {
            if ( ! this.settings.edit_settings ) {
                return nsSnackBar.error( __( 'You\'re not allowed to edit the order settings.' ) );
            }

            /**
             * We'll check if the user has the right to define order settings.
             */
            await ActionPermissions.canProceed( 'defineOrderSettings' );

            try {
                const response  =   await new Promise<{}>( ( resolve, reject) => {
                    Popup.show( nsPosOrderSettingsVue, { resolve, reject, order : this.order });
                });

                /**
                 * We'll update the order
                 */
                POS.order.next({ ...this.order, ...response });

            } catch( exception ) {
                // we shouldn't catch any exception here.
            }
        },

        async openNotePopup() {
            /**
             * We'll ensure the user has the right to add comments to an order.
             */
            await ActionPermissions.canProceed( 'nexopos.cart.comments' );
            
            try {
                const response  =   await new Promise<{}>( ( resolve, reject ) => {
                    const note              =   this.order.note;
                    const note_visibility   =   this.order.note_visibility;
                    Popup.show( nsPosNotePopupVue, { resolve, reject, note, note_visibility });
                });

                const order     =   { ...this.order, ...response };
                POS.order.next( order );
            } catch( exception ) {
                if ( exception !== false ) {
                    nsSnackBar.error( exception.message );
                }
            }
        },

        async selectTaxGroup( activeTab = 'settings' ) {
            /**
             * We'll check if the user has the right to manage taxes.
             */
            await ActionPermissions.canProceed( 'nexopos.cart.taxes' );

            try {
                const response              =   await new Promise<{}>( ( resolve, reject ) => {
                    const taxes             =   this.order.taxes;
                    const tax_group_id      =   this.order.tax_group_id;
                    const tax_type          =   this.order.tax_type;
                    Popup.show( nsPosTaxPopupVue, { resolve, reject, taxes, tax_group_id, tax_type, activeTab })
                });

                const order             =   { ...this.order, ...response };
                
                POS.order.next( order );
                POS.refreshCart();

            } catch( exception ) {
                // popup is closed... not needed to log or do anything else
            }
        },

        openTaxSummary() {
            this.selectTaxGroup( 'summary' );
        },

        selectCustomer() {
            Popup.show( nsPosCustomerPopupVue );
        },

        async openDiscountPopup( reference, type, index = null ) {
            if ( ! this.settings.products_discount && type === 'product' ) {
                return nsSnackBar.error( __( `You're not allowed to add a discount on the product.` ) );
            }

            if ( ! this.settings.cart_discount && type === 'cart' ) {
                return nsSnackBar.error( __( `You're not allowed to add a discount on the cart.` ) );
            }

            if ( type === 'product' ) {
                reference.disable_flat = true;
            }

            await ActionPermissions.canProceed( type === 'product' ? 'nexopos.cart.product-discount' : 'nexopos.cart.discount' );

            try {
                const promise   =   await new Promise( ( resolve, reject ) => {
                    Popup.show( nsPosDiscountPopupVue, { 
                        reference,
                        resolve,
                        reject,
                        type,
                        onSubmit( response ) {
                            /**
                             * we should check here, if the discount is flat, we'll make sure
                             * the amount doesn't exceed the total_price of the product.
                             */
                            if ( response.discount_type === 'flat' && response.discount > reference.total_price ) {
                                return nsSnackBar.error( __( 'The discount amount can\'t exceed the total price of the product.' ) );
                            }
                            
                            
                            if ( type === 'product' ) {
                                POS.updateProduct( reference, response, index );
                            } else if ( type === 'cart' ) {
                                POS.updateCart( reference, response );
                            }
                        }
                    }, {
                        popupClass: 'bg-white h:2/3 shadow-lg xl:w-1/4 lg:w-2/5 md:w-2/3 w-full'
                    })
                })
            } catch ( exception ) {
                // the popup might just be closed...
            }
        },

        async toggleMode( product, index ) {
            if ( ! this.options.ns_pos_allow_wholesale_price ) {
                return nsSnackBar.error( __( 'Unable to change the price mode. This feature has been disabled.' ) );
            }

            if ( product.mode === 'normal' ) {
                /**
                 * We restrict the usage of the wholesale-price
                 * behind a permission defined on ActionPermissions.
                 */
                await ActionPermissions.canProceed( 'nexopos.cart.product-wholesale-price' );

                Popup.show( PosConfirmPopup, {
                    title: __( 'Enable WholeSale Price' ),
                    message: __( 'Would you like to switch to wholesale price ?' ),
                    onAction( action ) {
                        if ( action ) {
                            POS.updateProduct( product, { mode: 'wholesale' }, index );
                        }
                    }
                });
            } else {
                Popup.show( PosConfirmPopup, {
                    title: __( 'Enable Normal Price' ),
                    message: __( 'Would you like to switch to normal price ?' ),
                    onAction( action ) {
                        if ( action ) {
                            POS.updateProduct( product, { mode: 'normal' }, index );
                        }
                    }
                });
            }
        },
        async removeUsingIndex( index ) {
            /**
             * We need to check if the user has the right to delete a product.
             */
            await ActionPermissions.canProceed( 'nexopos.cart.product-delete' );

            Popup.show( PosConfirmPopup, {
                title: __( 'Confirm Your Action' ),
                message: __( 'Would you like to delete this product ?' ),
                onAction( action ) {
                    if ( action ) {
                        POS.removeProductUsingIndex( index );
                    }
                }
            });
        },

        allowQuantityModification( product ) {
            return product.product_type === 'product';
        },

        /**
         * This will use the previously used 
         * popup to run the promise.
         */
        changeQuantity( product, index ) {
            if ( this.allowQuantityModification( product ) ) {
                const quantityPromise   =   new ProductQuantityPromise( product );
                quantityPromise.run({ 
                    unit_quantity_id    : product.unit_quantity_id, 
                    unit_name           : product.unit_name, 
                    $quantities         : product.$quantities 
                }).then( result => {
                    POS.updateProduct( product, result, index );
                });
            }
        },

        openOrderType() {
            Popup.show( nsPosOrderTypePopupVue );
        },

        openShippingPopup() {
            Popup.show( nsPosShippingPopupVue );
        }
    }
}
</script>