<template>
    <div class="px-4 py-4">
        <div class="mb-4 flex items-center gap-2">
            <input
                v-model="search"
                type="text"
                placeholder="Search activities..."
                class="border rounded px-3 py-2 flex-auto outline-hidden"
                @input="debounceSearch"
            />
            <select
                v-model="typeFilter"
                class="border rounded px-3 py-2 outline-hidden"
                @change="loadActivities"
            >
                <option value="">All Types</option>
                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
            </select>
            <button
                @click="loadActivities"
                class="rounded px-4 py-2 bg-primary text-white"
            >
                Refresh
            </button>
        </div>

        <div class="border rounded overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-table-th border-b border-table-th-edge">
                        <th class="px-3 py-2 text-left">Time</th>
                        <th class="px-3 py-2 text-left">User</th>
                        <th class="px-3 py-2 text-left">Action</th>
                        <th class="px-3 py-2 text-left">Subject</th>
                        <th class="px-3 py-2 text-left">Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="activity in activities"
                        :key="activity.id"
                        class="border-b hover:bg-option-hover"
                    >
                        <td class="px-3 py-2 whitespace-nowrap text-fontcolor-soft">
                            {{ formatDate(activity.created_at) }}
                        </td>
                        <td class="px-3 py-2">
                            {{ activity.causer ? activity.causer.username : 'System' }}
                        </td>
                        <td class="px-3 py-2">
                            <span
                                class="inline-block rounded px-2 py-0.5 text-xs font-medium"
                                :class="badgeClass(activity.log_name)"
                            >
                                {{ activity.description }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-fontcolor-soft">
                            {{ activity.subject_type ? activity.subject_type.replace('App\\Models\\', '') : '-' }}
                            <span v-if="activity.subject_id">#{{ activity.subject_id }}</span>
                        </td>
                        <td class="px-3 py-2 text-fontcolor-soft text-xs max-w-xs truncate">
                            {{ formatProperties(activity.properties) }}
                        </td>
                    </tr>
                    <tr v-if="activities.length === 0">
                        <td colspan="5" class="px-3 py-8 text-center text-fontcolor-soft">
                            No activities found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-center justify-between" v-if="totalPages > 1">
            <span class="text-sm text-fontcolor-soft">
                Page {{ currentPage }} of {{ totalPages }}
            </span>
            <div class="flex gap-2">
                <button
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                    class="rounded px-3 py-1 border"
                    :class="currentPage <= 1 ? 'opacity-50' : 'cursor-pointer'"
                >
                    Previous
                </button>
                <button
                    :disabled="currentPage >= totalPages"
                    @click="goToPage(currentPage + 1)"
                    class="rounded px-3 py-1 border"
                    :class="currentPage >= totalPages ? 'opacity-50' : 'cursor-pointer'"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { nsHttpClient } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'ns-activity-log',
    data() {
        return {
            activities: [],
            types: [],
            search: '',
            typeFilter: '',
            currentPage: 1,
            totalPages: 1,
            debounce: null,
        };
    },
    mounted() {
        this.loadActivities();
        this.loadTypes();
    },
    methods: {
        __,
        async loadActivities() {
            try {
                const params = new URLSearchParams({
                    per_page: 50,
                    page: this.currentPage,
                });
                if (this.search) params.append('search', this.search);
                if (this.typeFilter) params.append('type', this.typeFilter);

                const response = await fetch(`/api/activity-log?${params}`);
                const data = await response.json();
                this.activities = data.data || [];
                this.totalPages = data.last_page || 1;
            } catch (e) {
                this.activities = [];
            }
        },
        async loadTypes() {
            try {
                const response = await fetch('/api/activity-log/types');
                this.types = await response.json();
            } catch (e) {
                this.types = [];
            }
        },
        debounceSearch() {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => {
                this.currentPage = 1;
                this.loadActivities();
            }, 300);
        },
        goToPage(page) {
            this.currentPage = page;
            this.loadActivities();
        },
        formatDate(date) {
            if (!date) return '-';
            const d = new Date(date);
            return d.toLocaleString();
        },
        formatProperties(props) {
            if (!props) return '-';
            const attrs = props.attributes || {};
            const changes = [];
            if (attrs.name) changes.push(`name: ${attrs.name}`);
            if (attrs.email) changes.push(`email: ${attrs.email}`);
            return changes.length ? changes.join(', ') : '-';
        },
        badgeClass(logName) {
            const map = {
                default: 'bg-gray-100 text-gray-700',
                product: 'bg-blue-100 text-blue-700',
                order: 'bg-green-100 text-green-700',
                customer: 'bg-purple-100 text-purple-700',
                user: 'bg-yellow-100 text-yellow-700',
            };
            return map[logName] || 'bg-gray-100 text-gray-700';
        },
    },
};
</script>
