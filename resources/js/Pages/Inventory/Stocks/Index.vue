<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import IssueStockModal from './Partials/IssueStockModal.vue'
import TransferStockModal from './Partials/TransferStockModal.vue'
import AdjustStockModal from './Partials/AdjustStockModal.vue'

const page = usePage()

/* =========================
   REACTIVE PROPS (IMPORTANT)
========================= */
const warehouses = computed(() => page.props.warehouses ?? [])
const stocks = computed(() => page.props.stocks ?? [])

/* =========================
   UI STATE
========================= */
const selectedWarehouse = ref('all')
const search = ref('')

const selectedStock = ref(null)
const showIssue = ref(false)
const showTransfer = ref(false)
const showAdjust = ref(false)

/* =========================
   COMPUTED
========================= */
const filteredStocks = computed(() => {
    let result = stocks.value

    if (selectedWarehouse.value !== 'all') {
        result = result.filter(
            s => s.warehouse_id === selectedWarehouse.value
        )
    }

    if (search.value) {
        const keyword = search.value.toLowerCase()
        result = result.filter(s =>
            s.purchase_order_item.item_name
                ?.toLowerCase()
                .includes(keyword)
        )
    }

    return result
})

const totalQuantity = computed(() =>
    filteredStocks.value.reduce(
        (sum, s) => sum + Number(s.quantity),
        0
    )
)

/* =========================
   ACTIONS
========================= */
function openMovements(stock) {
    router.get(route('inventory.stocks.movements'), {
        warehouse_id: stock.warehouse_id,
        po_item_id: stock.purchase_order_item_id,
    })
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

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="text-xl font-semibold text-gray-800">
                    Inventory Stock
                </h2>

                <div class="flex gap-2">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search item..."
                        class="border rounded px-3 py-2 text-sm w-48"
                    />

                    <select
                        v-model="selectedWarehouse"
                        class="border rounded px-3 py-2 text-sm"
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
                </div>
            </div>
        </template>

        <!-- ================= SUMMARY ================= -->
        <div class="mb-4 bg-white rounded-lg shadow p-4 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                Total Records:
                <span class="font-semibold text-gray-800">
                    {{ filteredStocks.length }}
                </span>
            </div>

            <div class="text-sm text-gray-600">
                Total Quantity:
                <span class="font-semibold text-gray-800">
                    {{ totalQuantity }}
                </span>
            </div>
        </div>

        <!-- ================= TABLE ================= -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                            Warehouse
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                            Item
                        </th>
                        <th class="px-4 py-3 text-left text-sm font-medium text-gray-500">
                            Description
                        </th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500">
                            Quantity
                        </th>
                        <th class="px-4 py-3 text-right text-sm font-medium text-gray-500">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="stock in filteredStocks"
                        :key="stock.id"
                        class="hover:bg-gray-50 cursor-pointer"
                        title="View movement history"
                        @click="openMovements(stock)"
                    >
                        <td class="px-4 py-3 font-medium text-gray-800">
                            {{ stock.warehouse.title }}
                        </td>

                        <td class="px-4 py-3">
                            {{ stock.purchase_order_item.item_name }}
                        </td>

                        <td class="px-4 py-3 text-gray-600">
                            {{ stock.purchase_order_item.description || '-' }}
                        </td>

                        <td class="px-4 py-3 text-right font-semibold">
                            {{ stock.quantity }}
                        </td>

                        <td class="px-4 py-3 text-right space-x-2">
                            <button
                                v-if="Number(stock.quantity) > 0"
                                @click.stop="openIssue(stock)"
                                class="text-red-600 hover:text-red-800"
                                title="Issue Stock"
                            >
                                <i class="mdi mdi-minus-circle-outline text-lg"></i>
                            </button>

                            <button
                                v-if="Number(stock.quantity) > 0"
                                @click.stop="openTransfer(stock)"
                                class="text-indigo-600 hover:text-indigo-800"
                                title="Transfer Stock"
                            >
                                <i class="mdi mdi-swap-horizontal text-lg"></i>
                            </button>

                            <button
                                @click.stop="openAdjust(stock)"
                                class="text-yellow-600 hover:text-yellow-800"
                                title="Adjust Stock"
                            >
                                <i class="mdi mdi-tune-vertical text-lg"></i>
                            </button>
                        </td>
                    </tr>

                    <tr v-if="filteredStocks.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-gray-500"
                        >
                            No inventory stock found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ================= MODALS ================= -->
        <IssueStockModal
            :show="showIssue"
            :stock="selectedStock"
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
