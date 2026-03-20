<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import IssueStockModal from './Partials/IssueStockModal.vue'
import TransferStockModal from './Partials/TransferStockModal.vue'
import AdjustStockModal from './Partials/AdjustStockModal.vue'

const page = usePage()

const warehouses = computed(() => page.props.warehouses ?? [])
const projects = computed(() => page.props.projects ?? [])
const stockCategories = computed(() => page.props.stock_categories ?? [])
const stocks = computed(() => page.props.stocks ?? [])

const selectedWarehouse = ref('all')
const search = ref('')

const selectedStock = ref(null)
const showIssue = ref(false)
const showTransfer = ref(false)
const showAdjust = ref(false)

const filteredStocks = computed(() => {
    let result = stocks.value

    if (selectedWarehouse.value !== 'all') {
        result = result.filter(s => Number(s.warehouse_id) === Number(selectedWarehouse.value))
    }

    if (search.value) {
        const keyword = search.value.toLowerCase().trim()
        result = result.filter((s) => {
            const item = s.purchase_order_item?.item_name?.toLowerCase() ?? ''
            const desc = s.purchase_order_item?.description?.toLowerCase() ?? ''
            const warehouse = s.warehouse?.title?.toLowerCase() ?? ''

            return item.includes(keyword) || desc.includes(keyword) || warehouse.includes(keyword)
        })
    }

    return result
})

const totalQuantity = computed(() =>
    filteredStocks.value.reduce((sum, s) => sum + Number(s.quantity || 0), 0)
)

const lowStockCount = computed(() =>
    filteredStocks.value.filter(s => Number(s.quantity || 0) > 0 && Number(s.quantity || 0) <= 5).length
)

const outOfStockCount = computed(() =>
    filteredStocks.value.filter(s => Number(s.quantity || 0) <= 0).length
)

function formatQty(value) {
    return Number(value || 0).toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })
}

function openMovements(stock) {
    router.get(route('inventory.stocks.movements'), {
        warehouse_id: stock.warehouse_id,
        po_item_id: stock.purchase_order_item_id,
    })
}

function openMovementLog() {
    router.get(route('inventory.stocks.movements'))
}

function openBrowse() {
    router.get(route('inventory.stocks.browse'))
}

function openIssue(stock) {
    selectedStock.value = stock
    showIssue.value = true
}

function openTransfer(stock) {
    selectedStock.value = stock
    showTransfer.value = true
}

function openAdjust(stock) {
    selectedStock.value = stock
    showAdjust.value = true
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">Inventory Stock</h2>
                    <p class="text-sm text-gray-500">Monitor current quantities and act quickly on low stock.</p>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search item, warehouse, description"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100 sm:w-72"
                    />

                    <select
                        v-model="selectedWarehouse"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                    >
                        <option value="all">All Warehouses</option>
                        <option
                            v-for="w in warehouses"
                            :key="w.id"
                            :value="w.id"
                        >
                            {{ w.title }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        @click="openMovementLog"
                    >
                        <i class="mdi mdi-history"></i>
                        Movement Log
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-1.5 rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
                        @click="openBrowse"
                    >
                        <i class="mdi mdi-view-list"></i>
                        Browse
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Stock Records</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ filteredStocks.length }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Quantity</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatQty(totalQuantity) }}</p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Low Stock (<= 5)</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-800">{{ lowStockCount }}</p>
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-700">Out of Stock</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-800">{{ outOfStockCount }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Item</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="stock in filteredStocks"
                            :key="stock.id"
                            class="cursor-pointer hover:bg-indigo-50/40"
                            title="View movement history"
                            @click="openMovements(stock)"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ stock.warehouse?.title }}</p>
                            </td>

                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ stock.purchase_order_item?.item_name }}</p>
                            </td>

                            <td class="px-4 py-3 text-sm text-gray-600">
                                {{ stock.purchase_order_item?.description || '-' }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <span
                                    class="inline-flex min-w-20 items-center justify-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="Number(stock.quantity) <= 0
                                        ? 'bg-rose-100 text-rose-700'
                                        : Number(stock.quantity) <= 5
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-emerald-100 text-emerald-700'"
                                >
                                    {{ formatQty(stock.quantity) }}
                                </span>
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        v-if="Number(stock.quantity) > 0"
                                        type="button"
                                        @click.stop="openIssue(stock)"
                                        class="inline-flex items-center gap-1 rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                        title="Issue Stock"
                                    >
                                        <i class="mdi mdi-minus-circle-outline"></i>
                                        Issue
                                    </button>

                                    <button
                                        v-if="Number(stock.quantity) > 0"
                                        type="button"
                                        @click.stop="openTransfer(stock)"
                                        class="inline-flex items-center gap-1 rounded-md border border-blue-200 bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 hover:bg-blue-100"
                                        title="Transfer Stock"
                                    >
                                        <i class="mdi mdi-swap-horizontal"></i>
                                        Transfer
                                    </button>

                                    <button
                                        type="button"
                                        @click.stop="openAdjust(stock)"
                                        class="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 hover:bg-amber-100"
                                        title="Adjust Stock"
                                    >
                                        <i class="mdi mdi-tune-vertical"></i>
                                        Adjust
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="filteredStocks.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-10 text-center text-sm text-gray-500"
                            >
                                No inventory stock found for the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <IssueStockModal
            :show="showIssue"
            :stock="selectedStock"
            :projects="projects"
            :stock-categories="stockCategories"
            @close="showIssue = false"
        />

        <TransferStockModal
            :show="showTransfer"
            :stock="selectedStock"
            :warehouses="warehouses"
            @close="showTransfer = false"
        />

        <AdjustStockModal
            :show="showAdjust"
            :stock="selectedStock"
            @close="showAdjust = false"
        />
    </AuthenticatedLayout>
</template>
