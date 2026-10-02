<template>
    <div
        class="h-full w-full flex items-center justify-center"
        id="ns-units-selector"
    >
        <div
            class="ns-box w-[80vw] lg:w-[33.33vw] overflow-hidden flex flex-col"
            v-if="unitsQuantities.length > 0"
        >
            <div
                id="header"
                class="h-16 flex justify-center items-center flex-shrink-0"
            >
                <h3 class="font-bold text-font">
                    {{
                        __("{product} : Units").replace(
                            "{product}",
                            productName,
                        )
                    }}
                </h3>
            </div>
            <div
                v-if="unitsQuantities.length > 0"
                class="grid grid-flow-row grid-cols-2 overflow-y-auto"
            >
                <div
                    @click="selectUnit(unitQuantity)"
                    :key="unitQuantity.id"
                    v-for="unitQuantity of unitsQuantities"
                    class="ns-numpad-key cursor-pointer border flex-shrink-0 flex flex-col items-center justify-center"
                >
                    <div
                        class="h-40 w-full flex items-center justify-center overflow-hidden"
                    >
                        <img
                            v-if="unitQuantity.preview_url"
                            :src="unitQuantity.preview_url"
                            class="object-cover h-full w-full"
                            :alt="unitQuantity.unit.name"
                        />
                        <div
                            class="h-40 flex items-center justify-center"
                            v-if="!unitQuantity.preview_url"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 32 32"
                                class="w-16 h-16 fill-fontcolor"
                            ><path d="M 2 5 L 2 27 L 30 27 L 30 5 Z M 4 7 L 28 7 L 28 20.90625 L 22.71875 15.59375 L 22 14.875 L 17.46875 19.40625 L 11.71875 13.59375 L 11 12.875 L 4 19.875 Z M 24 9 C 22.894531 9 22 9.894531 22 11 C 22 12.105469 22.894531 13 24 13 C 25.105469 13 26 12.105469 26 11 C 26 9.894531 25.105469 9 24 9 Z M 11 15.71875 L 20.1875 25 L 4 25 L 4 22.71875 Z M 22 17.71875 L 28 23.71875 L 28 25 L 23.03125 25 L 18.875 20.8125 Z"/></svg>
                        </div>
                    </div>
                    <div class="h-0 w-full">
                        <div
                            class="relative w-full flex items-center justify-center -top-20 h-20 py-2 flex-col overlay"
                        >
                            <h3
                                class="font-bold text-fontcolor py-2 text-center"
                            >
                                {{ unitQuantity.unit.name }} ({{
                                    unitQuantity.quantity
                                }})
                            </h3>
                            <p class="text-sm font-medium text-font">
                                {{
                                    nsCurrency(displayRightPrice(unitQuantity))
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div
            class="h-56 flex items-center justify-center"
            v-if="unitsQuantities.length === 0"
        >
            <ns-spinner></ns-spinner>
        </div>
    </div>
</template>
<script>
import { nsCurrency } from "~/filters/currency";
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";
import popupCloser from "~/libraries/popup-closer";
import popupResolver from "~/libraries/popup-resolver";

export default {
    props: ["popup"],
    data() {
        return {
            unitsQuantities: [],
            loadsUnits: false,
            options: null,
            optionsSubscriber: null,
        };
    },

    beforeDestroy() {
        this.optionsSubscriber.unsubscribe();
    },

    mounted() {
        this.optionsSubscriber = POS.options.subscribe((options) => {
            this.options = options;
        });

        /**
         * If there is a default selected unit quantity
         * provided, we assume the product was added using the unit
         * quantity barcode.
         */
        if (
            this.popup.params.product.$original().selectedUnitQuantity !==
            undefined
        ) {
            this.selectUnit(
                this.popup.params.product.$original().selectedUnitQuantity,
            );
        } else if (
            this.popup.params.product.$original().unit_quantities !==
                undefined &&
            this.popup.params.product.$original().unit_quantities.length === 1
        ) {
            this.selectUnit(
                this.popup.params.product.$original().unit_quantities[0],
            );
        } else {
            this.loadsUnits = true;
            this.loadUnits();
        }

        this.popupCloser();
    },
    computed: {
        productName() {
            return this.popup.params.product.$original().name;
        },
    },
    methods: {
        __,
        nsCurrency,
        popupCloser,
        popupResolver,

        displayRightPrice(item) {
            return POS.getSalePrice(
                item,
                this.popup.params.product.$original(),
            );
        },

        loadUnits() {
            nsHttpClient
                .get(
                    `/api/products/${this.popup.params.product.$original().id}/units/quantities`,
                )
                .subscribe((result) => {
                    if (result.length === 0) {
                        this.popup.close();
                        return nsSnackBar.error(
                            __(
                                "This product doesn't have any unit defined for selling. Make sure to mark at least one unit as visible.",
                            ),
                        );
                    }

                    this.unitsQuantities = result;

                    /**
                     * This will automatically
                     * select a unit if there is only one unit available.
                     */
                    if (this.unitsQuantities.length === 1) {
                        this.selectUnit(this.unitsQuantities[0]);
                    }
                });
        },
        /**
         * we'll resolve a value that
         * will be added to the object
         * built at the end
         * @param Unit
         */
        selectUnit(unitQuantity) {
            if (unitQuantity.unit === null) {
                nsSnackBar.error(
                    __(
                        'The unit attached to this product is missing or not assigned. Please review the "Unit" tab for this product.',
                    ),
                );

                return this.popup.close();
            }

            this.popup.params.resolve({
                unit_quantity_id: unitQuantity.id,
                unit_name: unitQuantity.unit.name,
                $quantities: () => unitQuantity,
            });

            this.popup.close();
        },
    },
};
</script>
