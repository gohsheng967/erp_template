<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    rows: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
    stock_categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const destination = ref(props.filters.destination ?? 'all')
const warehouseId = ref(props.filters.warehouse_id ?? 'all')
const stockCategory = ref(props.filters.stock_category ?? 'all')

const filteredRows = computed(() => {
    const keyword = String(search.value ?? '').trim().toLowerCase()

    return (props.rows ?? []).filter((row) => {
        if (destination.value !== 'all' && row.issue_destination_type !== destination.value) {
            return false
        }

        if (warehouseId.value !== 'all' && Number(row.warehouse_id) !== Number(warehouseId.value)) {
            return false
        }

        if (stockCategory.value !== 'all' && (row.stock_category || '') !== stockCategory.value) {
            return false
        }

        if (!keyword) {
            return true
        }

        const haystack = [
            row.purchase_order_item?.item_name,
            row.serial_number,
            row.stock_category,
            row.purpose,
            row.remark,
            row.issue_user?.name,
            row.project?.name,
            row.project?.code,
            row.site?.site_name,
            row.warehouse?.title,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return haystack.includes(keyword)
    })
})

const officeRows = computed(() => filteredRows.value.filter((row) => row.issue_destination_type === 'office'))

const projectGroups = computed(() => {
    const bucket = {}

    filteredRows.value
        .filter((row) => row.issue_destination_type === 'project')
        .forEach((row) => {
            const key = `${row.project_id || 'na'}-${row.site_id || 'na'}`

            if (!bucket[key]) {
                bucket[key] = {
                    key,
                    projectName: row.project?.name || 'Unnamed Project',
                    projectCode: row.project?.code || '-',
                    siteName: row.site?.site_name || 'Unknown Site',
                    rows: [],
                    totalQty: 0,
                }
            }

            bucket[key].rows.push(row)
            bucket[key].totalQty += Number(row.quantity || 0)
        })

    return Object.values(bucket).sort((a, b) => a.projectName.localeCompare(b.projectName))
})

const totalQty = computed(() =>
    filteredRows.value.reduce((sum, row) => sum + Number(row.quantity || 0), 0)
)

function applyFilters() {
    router.get(route('inventory.stocks.browse'), {
        search: search.value || undefined,
        destination: destination.value,
        warehouse_id: warehouseId.value,
        stock_category: stockCategory.value,
    }, {
        preserveState: true,
        preserveScroll: true,
    })
}

function resetFilters() {
    search.value = ''
    destination.value = 'all'
    warehouseId.value = 'all'
    stockCategory.value = 'all'
    applyFilters()
}

function exportCsv() {
    const query = new URLSearchParams({
        search: search.value || '',
        destination: destination.value,
        warehouse_id: warehouseId.value,
        stock_category: stockCategory.value,
    })

    window.location.href = `${route('inventory.stocks.browse.export')}?${query.toString()}`
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="router.visit(route('inventory.stocks.index'))"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back
                    </button>

                    <div>
                    <h2 class="text-xl font-semibold text-gray-800">Stock Browse</h2>
                    <p class="text-sm text-gray-500">Grouped view by Office and Project / Site with export.</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        @click="router.visit(route('inventory.stocks.movements'))"
                    >
                        Movement Log
                    </button>
                    <button
                        type="button"
                        class="rounded-md bg-emerald-600 px-3 py-2 text-sm text-white hover:bg-emerald-700"
                        @click="exportCsv"
                    >
                        Export CSV
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                    <input
                        v-model="search"
                        type="text"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                        placeholder="Search item / SN / project / user"
                    />

                    <select
                        v-model="destination"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="all">All Destinations</option>
                        <option value="office">Office</option>
                        <option value="project">Project</option>
                    </select>

                    <select
                        v-model="warehouseId"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="all">All Warehouses</option>
                        <option v-for="w in warehouses" :key="w.id" :value="String(w.id)">
                            {{ w.title }}
                        </option>
                    </select>

                    <select
                        v-model="stockCategory"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="all">All Categories</option>
                        <option v-for="category in stock_categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
                            @click="applyFilters"
                        >
                            Apply
                        </button>
                        <button
                            type="button"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Total Rows</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ filteredRows.length }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Total Quantity</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ totalQty }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Project Groups</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ projectGroups.length }}</p>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-800">Office Stocks</h3>
                    <span class="text-xs text-gray-500">{{ officeRows.length }} rows</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Warehouse</th>
                                <th class="px-3 py-2 text-left">Item</th>
                                <th class="px-3 py-2 text-right">Qty</th>
                                <th class="px-3 py-2 text-left">SN</th>
                                <th class="px-3 py-2 text-left">Category</th>
                                <th class="px-3 py-2 text-left">Issued By</th>
                                <th class="px-3 py-2 text-left">Purpose</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in officeRows" :key="row.id">
                                <td class="px-3 py-2">{{ new Date(row.created_at).toLocaleString() }}</td>
                                <td class="px-3 py-2">{{ row.warehouse?.title || '-' }}</td>
                                <td class="px-3 py-2">{{ row.purchase_order_item?.item_name || '-' }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ row.quantity }}</td>
                                <td class="px-3 py-2">{{ row.serial_number || '-' }}</td>
                                <td class="px-3 py-2">{{ row.stock_category || '-' }}</td>
                                <td class="px-3 py-2">{{ row.issue_user?.name || '-' }}</td>
                                <td class="px-3 py-2">{{ row.purpose || '-' }}</td>
                            </tr>
                            <tr v-if="!officeRows.length">
                                <td colspan="8" class="px-3 py-6 text-center text-gray-500">No office stock rows.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    v-for="group in projectGroups"
                    :key="group.key"
                    class="rounded-xl border border-gray-200 bg-white p-4"
                >
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">
                                {{ group.projectName }} ({{ group.projectCode }})
                            </h3>
                            <p class="text-xs text-gray-500">Site: {{ group.siteName }}</p>
                        </div>
                        <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                            Qty {{ group.totalQty }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Warehouse</th>
                                    <th class="px-3 py-2 text-left">Item</th>
                                    <th class="px-3 py-2 text-right">Qty</th>
                                    <th class="px-3 py-2 text-left">SN</th>
                                    <th class="px-3 py-2 text-left">Category</th>
                                    <th class="px-3 py-2 text-left">Issued By</th>
                                    <th class="px-3 py-2 text-left">Purpose</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="row in group.rows" :key="row.id">
                                    <td class="px-3 py-2">{{ new Date(row.created_at).toLocaleString() }}</td>
                                    <td class="px-3 py-2">{{ row.warehouse?.title || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.purchase_order_item?.item_name || '-' }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ row.quantity }}</td>
                                    <td class="px-3 py-2">{{ row.serial_number || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.stock_category || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.issue_user?.name || '-' }}</td>
                                    <td class="px-3 py-2">{{ row.purpose || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="!projectGroups.length"
                    class="rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500"
                >
                    No project stock rows.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
