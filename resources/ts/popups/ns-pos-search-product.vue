<template>
    <div
        id="product-search"
        class="ns-box shadow-lg w-full h-full flex flex-col overflow-hidden"
    >
        <div
            class="p-2 border-b ns-box-header flex justify-between items-center shrink-0"
        >
            <h3 class="text-fontcolor">{{ __("Search Product") }}</h3>
            <div>
                <ns-close-button @click="popup.close()"></ns-close-button>
            </div>
        </div>
        <div class="flex-auto overflow-hidden flex flex-col">
            <div class="p-2 border-b ns-box-body shrink-0">
                <div
                    class="flex input-group info border-2 rounded overflow-hidden"
                >
                    <input
                        @keyup.enter="search()"
                        v-model="searchValue"
                        ref="searchField"
                        type="text"
                        class="p-2 outline-hidden flex-auto text-font text-2xl"
                    />
                    <button @click="search()" class="px-2">
                        {{ __("Search") }}
                    </button>
                </div>
            </div>
            <div class="overflow-y-auto ns-scrollbar flex-auto relative">
                <div
                    v-if="products.length > 0"
                    class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 p-2"
                >
                    <div
                        v-for="product of products"
                        :key="product.id"
                        @click="!isOutOfStock(product) && addToCart(product)"
                        :class="[
                            'ns-box rounded-lg p-0 flex flex-col border transition-all overflow-hidden',
                            isOutOfStock(product)
                                ? 'ns-out-of-stock opacity-40 cursor-not-allowed'
                                : 'cursor-pointer hover:shadow-md hover:border-info',
                        ]"
                    >
                        <div
                            class="aspect-4/3 bg-box-elevation-background flex items-center justify-center overflow-hidden"
                        >
                            <img
                                v-if="
                                    product.galleries &&
                                    product.galleries.length > 0
                                "
                                :src="
                                    product.galleries.filter(
                                        (i) => i.featured,
                                    )[0]?.url || product.galleries[0].url
                                "
                                class="object-cover h-full w-full"
                                :alt="product.name"
                            />
                            <i
                                v-else
                                class="las la-image text-4xl opacity-20"
                            ></i>
                        </div>
                        <div class="p-3 flex flex-col gap-1 flex-1">
                            <div class="flex items-start justify-between gap-1">
                                <h4
                                    class="text-fontcolor font-semibold text-lg leading-tight line-clamp-2 flex-1"
                                >
                                    {{ product.name }}
                                </h4>
                                <span
                                    v-if="isOutOfStock(product)"
                                    class="text-xs px-1.5 py-0.5 rounded-sm font-bold bg-red-600 text-white shrink-0 leading-none"
                                >
                                    {{ __("OOS") }}
                                </span>
                            </div>
                            <small class="text-soft-secondary text-base">
                                {{ product.category?.name }}
                            </small>
                            <div
                                class="mt-auto flex items-center justify-between pt-1"
                            >
                                <span class="text-sm text-soft-secondary">
                                    {{ __("Stock") }}:
                                </span>
                                <span
                                    class="text-base font-bold text-fontcolor"
                                >
                                    {{ totalQuantity(product) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-fontcolor text-center p-2">
                    {{
                        __(
                            "There is nothing to display. Have you started the search ?",
                        )
                    }}
                </div>
                <div
                    v-if="isLoading"
                    class="absolute h-full w-full flex items-center justify-center z-10 top-0"
                    style="background: rgb(187 203 214 / 29%)"
                >
                    <ns-spinner></ns-spinner>
                </div>
            </div>
        </div>
    </div>
</template>
<script>
import { nsHttpClient, nsSnackBar } from "~/bootstrap";
import { __ } from "~/libraries/lang";
import popupCloser from "~/libraries/popup-closer";
import popupResolver from "~/libraries/popup-resolver";
import nsPosConfirmPopupVue from "./ns-pos-confirm-popup.vue";
export default {
    name: "ns-pos-search-product",
    props: ["popup"],
    data() {
        return {
            searchValue: "",
            products: [],
            isLoading: false,
            debounce: null,
        };
    },
    watch: {
        searchValue() {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => {
                this.search();
            }, 500);
        },
    },
    mounted() {
        this.$refs.searchField.focus();

        /**
         * hotkey can't catch esc if
         * the focus is on the field.
         */
        this.$refs.searchField.addEventListener("keydown", (e) => {
            if (e.keyCode === 27) {
                this.popupResolver(false);
            }
        });

        this.popupCloser();
    },
    methods: {
        __,

        isOutOfStock(product) {
            if (
                !product.unit_quantities ||
                product.unit_quantities.length === 0
            ) {
                return true;
            }
            return product.unit_quantities.every(
                (q) => parseFloat(q.quantity) <= 0,
            );
        },

        totalQuantity(product) {
            if (
                !product.unit_quantities ||
                product.unit_quantities.length === 0
            ) {
                return 0;
            }
            return product.unit_quantities
                .map((q) => parseFloat(q.quantity))
                .reduce((a, b) => a + b, 0);
        },

        popupCloser,
        popupResolver,

        addToCart(product) {
            this.popup.close();

            if (parseInt(product.accurate_tracking) === 1) {
                return Popup.show(nsPosConfirmPopupVue, {
                    title: __("Unable to add the product"),
                    message: __(
                        'The product "{product}" can\'t be added from a search field, as "Accurate Tracking" is enabled. Would you like to learn more ?',
                    ).replace("{product}", product.name),
                    onAction: (action) => {
                        if (action) {
                            window.open(
                                "https://my.nexopos.com/en/documentation/troubleshooting/accurate-tracking",
                                "_blank",
                            );
                        }
                    },
                });
            }

            POS.addToCart(product);
        },

        search() {
            this.isLoading = true;
            nsHttpClient
                .post("/api/products/search", {
                    search: this.searchValue,
                    limit: 20,
                })
                .subscribe({
                    next: (result) => {
                        this.isLoading = false;
                        this.products = result;

                        if (this.products.length === 0) {
                            return nsSnackBar.info(
                                __(
                                    "No result to result match the search value provided.",
                                ),
                            );
                        }
                    },
                    error: (error) => {
                        this.isLoading = false;
                        nsSnackBar.error(error.message);
                    },
                });
        },
    },
};
</script>
