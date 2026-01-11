<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()

const warehouses = page.props.warehouses ?? []
const movements = page.props.movements ?? []

const filters = page.props.filters ?? {}

const isDrillDown = computed(() =>
    !!filters.warehouse_id && !!filters.po_item_id
)

/* =========================
   FILTERS
========================= */
const selectedWarehouse = ref('all')
const selectedType = ref('all')

const filteredMovements = computed(() => {
    let result = movements

    if (selectedWarehouse.value !== 'all') {
        result = result.filter(
            m => m.warehouse_id === selectedWarehouse.value
        )
    }

    if (selectedType.value !== 'all') {
        result = result.filter(
            m => m.type === selectedType.value
        )
    }

    return result
})

function typeLabel(type) {
    return {
        IN: 'Stock In',
        OUT: 'Stock Out',
        TRANSFER: 'Transfer',
        ADJUST: 'Adjustment',
    }[type] ?? type
}

function typeClass(type) {
    return {
        IN: 'bg-green-100 text-green-700',
        OUT: 'bg-red-100 text-red-700',
        TRANSFER: 'bg-blue-100 text-blue-700',
        ADJUST: 'bg-yellow-100 text-yellow-700',
    }[type]
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3">
                    <!-- BACK BUTTON -->
                    <button
                        v-if="isDrillDown"
                        @click="$inertia.visit(route('inventory.stocks.index'))"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back to Inventory
                    </button>

                    <h2 class="text-xl font-semibold text-gray-800">
                        Inventory Movements
                    </h2>
                </div>

                <div v-if="!isDrillDown" class="flex gap-2">
                    <!-- WAREHOUSE FILTER -->
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

                    <!-- TYPE FILTER -->
                    <select
                        v-model="selectedType"
                        class="border rounded px-3 py-2 text-sm"
                    >
                        <option value="all">All Types</option>
                        <option value="IN">Stock In</option>
                        <option value="OUT">Stock Out</option>
                        <option value="TRANSFER">Transfer</option>
                        <option value="ADJUST">Adjustment</option>
                    </select>
                </div>
            </div>
        </template>


        <!-- ================= TABLE ================= -->
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm text-gray-500">
                            Date
                        </th>
                        <th class="px-4 py-3 text-left text-sm text-gray-500">
                            Warehouse
                        </th>
                        <th class="px-4 py-3 text-left text-sm text-gray-500">
                            Item
                        </th>
                        <th class="px-4 py-3 text-center text-sm text-gray-500">
                            Type
                        </th>
                        <th class="px-4 py-3 text-right text-sm text-gray-500">
                            Quantity
                        </th>
                        <th class="px-4 py-3 text-right text-sm text-gray-500">
                            Balance After
                        </th>
                        <th class="px-4 py-3 text-left text-sm text-gray-500">
                            Remark
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    <tr
                        v-for="m in filteredMovements"
                        :key="m.id"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 text-sm">
                            {{ new Date(m.created_at).toLocaleString() }}
                        </td>

                        <td class="px-4 py-3">
                            {{ m.warehouse.title }}
                        </td>

                        <td class="px-4 py-3">
                            {{ m.purchase_order_item.item_name }}
                        </td>

                        <td class="px-4 py-3 text-center">
                            <span
                                class="px-2 py-1 text-xs rounded"
                                :class="typeClass(m.type)"
                            >
                                {{ typeLabel(m.type) }}
                            </span>
                        </td>

                        <td class="px-4 py-3 text-right font-semibold">
                            {{ m.quantity }}
                        </td>

                        <td class="px-4 py-3 text-right">
                            {{ m.balance_after }}
                        </td>

                        <td class="px-4 py-3 text-gray-600">
                            {{ m.remark || '-' }}
                        </td>
                    </tr>

                    <tr v-if="filteredMovements.length === 0">
                        <td
                            colspan="7"
                            class="px-4 py-8 text-center text-gray-500"
                        >
                            No movements found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </AuthenticatedLayout>
</template>
