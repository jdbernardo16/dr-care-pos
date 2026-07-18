<template>
    <div class="relative flex-shrink-0" id="more-button">
        <div @click="toggleMenu()" class="flex items-center font-bold cursor-pointer justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 text-xl h-14 w-14 mx-1 border border-gray-200 dark:border-gray-700">
            <span>⋮</span>
        </div>
        <div v-if="showMenu" class="absolute bottom-full left-0 right-0 mb-2 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50">
            <div @click="handleAction(action)" v-for="action of actions" :key="action.label" class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-300 border-b border-gray-100 dark:border-gray-700 last:border-0">
                <i :class="action.icon" class="text-lg"></i>
                <span>{{ action.label }}</span>
            </div>
        </div>
    </div>
</template>
<script lang="ts">
declare const __;

export default {
    props: {
        actions: {
            type: Array,
            default: () => [],
        },
    },
    data: () => ({
        showMenu: false,
    }),
    methods: {
        __,
        toggleMenu() {
            this.showMenu = !this.showMenu;
        },
        handleAction(action) {
            this.showMenu = false;
            if (action.action) {
                action.action();
            }
        },
        handleClickOutside(e) {
            if (!this.$el.contains(e.target)) {
                this.showMenu = false;
            }
        },
    },
    mounted() {
        document.addEventListener('click', this.handleClickOutside);
    },
    unmounted() {
        document.removeEventListener('click', this.handleClickOutside);
    },
}
</script>
