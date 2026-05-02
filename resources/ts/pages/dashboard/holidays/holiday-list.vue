<template>
    <div id="holiday-list" class="px-4">
        <div class="flex -mx-2 mb-4">
            <div class="px-2">
                <select
                    v-model="year"
                    class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm pr-8"
                    @change="loadHolidays"
                >
                    <option value="2026">2026</option>
                    <option value="2027">2027</option>
                    <option value="2028">2028</option>
                </select>
            </div>
            <div class="px-2">
                <button
                    @click="showAddForm = !showAddForm"
                    class="rounded bg-green-500 text-white shadow py-2 px-4 text-sm font-semibold hover:bg-green-600 transition-colors duration-200"
                >{{ showAddForm ? __( 'Cancel' ) : __( 'Add Holiday' ) }}</button>
            </div>
        </div>

        <!-- Add / Edit Form -->
        <div v-if="showAddForm" class="ns-box rounded-lg shadow mb-4">
            <div class="ns-box-header p-2 border-b">
                <h3 class="font-semibold text-primary">{{ editing ? __( 'Edit Holiday' ) : __( 'New Holiday' ) }}</h3>
            </div>
            <div class="ns-box-body p-4">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Name' ) }}</label>
                        <input v-model="form.name" type="text" class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Date' ) }}</label>
                        <input v-model="form.date" type="date" class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full" />
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Type' ) }}</label>
                        <select v-model="form.type" class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full">
                            <option value="regular_holiday">{{ __( 'Regular Holiday (x2.0)' ) }}</option>
                            <option value="special_non_working">{{ __( 'Special Non-Working (x1.3)' ) }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Multiplier' ) }}</label>
                        <input v-model.number="form.multiplier" type="number" step="0.05" min="1.0" max="3.0" class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full" />
                    </div>
                    <div class="flex items-center">
                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.is_recurring" type="checkbox" class="rounded" />
                            {{ __( 'Recurring yearly' ) }}
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs mb-1 font-semibold text-secondary">{{ __( 'Description' ) }}</label>
                    <input v-model="form.description" type="text" class="outline-none rounded border-2 border-gray-200 bg-surface h-10 px-2 text-sm w-full" />
                </div>
                <button
                    @click="saveHoliday"
                    class="rounded bg-blue-500 text-white shadow py-2 px-6 text-sm font-semibold hover:bg-blue-600 transition-colors duration-200"
                >{{ __( 'Save' ) }}</button>
            </div>
        </div>

        <!-- Table -->
        <div class="ns-table-container">
            <table class="ns-table">
                <thead>
                    <tr>
                        <th>{{ __( 'Name' ) }}</th>
                        <th>{{ __( 'Date' ) }}</th>
                        <th>{{ __( 'Type' ) }}</th>
                        <th>{{ __( 'Multiplier' ) }}</th>
                        <th>{{ __( 'Recurring' ) }}</th>
                        <th>{{ __( 'Actions' ) }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="holidays.length === 0">
                        <td colspan="6" class="text-center py-8 text-secondary">{{ __( 'No holidays configured.' ) }}</td>
                    </tr>
                    <tr v-for="h in holidays" :key="h.id">
                        <td class="font-semibold">{{ h.name }}</td>
                        <td>{{ h.date }}</td>
                        <td>
                            <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="h.type === 'regular_holiday' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'">
                                {{ h.type === 'regular_holiday' ? __( 'Regular' ) : __( 'Special' ) }}
                            </span>
                        </td>
                        <td class="font-bold">×{{ h.multiplier }}</td>
                        <td>{{ h.is_recurring ? __( 'Yes' ) : __( 'No' ) }}</td>
                        <td>
                            <button @click="editHoliday( h )" class="rounded bg-blue-500 text-white shadow py-1 px-2 text-xs font-semibold hover:bg-blue-600 transition-colors duration-200">{{ __( 'Edit' ) }}</button>
                            <button @click="deleteHoliday( h )" class="rounded bg-red-500 text-white shadow py-1 px-2 text-xs font-semibold hover:bg-red-600 transition-colors duration-200 ml-1">{{ __( 'Delete' ) }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script>
import { nsHttpClient, nsSnackBar } from '~/bootstrap';
import { __ } from '~/libraries/lang';

export default {
    name: 'nsHolidayList',
    data() {
        return {
            holidays: [],
            year: '2026',
            showAddForm: false,
            editing: false,
            form: { name: '', date: '', type: 'regular_holiday', multiplier: 2.00, is_recurring: true, description: '' },
        };
    },
    mounted() {
        this.loadHolidays();
    },
    methods: {
        __,
        loadHolidays() {
            nsHttpClient.get( `/api/holidays?year=${this.year}` ).subscribe({
                next: (result) => { this.holidays = result; },
                error: () => {},
            });
        },
        editHoliday( h ) {
            this.editing = true;
            this.form = { ...h };
            this.showAddForm = true;
        },
        saveHoliday() {
            const method = this.editing ? 'put' : 'post';
            const url = this.editing ? `/api/holidays/${this.form.id}` : '/api/holidays';

            nsHttpClient[ method ]( url, this.form ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Holiday saved.' ) );
                        this.showAddForm = false;
                        this.editing = false;
                        this.form = { name: '', date: '', type: 'regular_holiday', multiplier: 2.00, is_recurring: true, description: '' };
                        this.loadHolidays();
                    } else {
                        nsSnackBar.error( result.message || __( 'Failed.' ) );
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'Error.' ) ); },
            });
        },
        deleteHoliday( h ) {
            if ( ! confirm( __( 'Delete this holiday?' ) ) ) return;
            nsHttpClient.delete( `/api/holidays/${h.id}` ).subscribe({
                next: (result) => {
                    if ( result.status === 'success' ) {
                        nsSnackBar.success( __( 'Holiday deleted.' ) );
                        this.loadHolidays();
                    }
                },
                error: (e) => { nsSnackBar.error( e.message || __( 'Error.' ) ); },
            });
        },
    },
};
</script>
