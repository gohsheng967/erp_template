<script setup>
import { Link } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

defineProps({
    orders: Array,
})

const { formatDate, capitalize } = useFormat()

function statusClass(status) {
    return {
        draft: 'bg-gray-100 text-gray-700',
        confirmed: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700',
    }[status] || 'bg-gray-100 text-gray-600'
}
</script>

<template>
    <div class="space-y-4">

        <!-- Empty -->
        <div
            v-if="orders.length === 0"
            class="border border-dashed rounded-xl p-8 text-center text-sm text-gray-500"
        >
            No purchase orders from this supplier yet.
        </div>

        <!-- Table -->
        <div
            v-else
            class="overflow-x-auto border rounded-xl"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="px-4 py-3 text-left">PO Code</th>
                        <th class="px-4 py-3 text-left">Order Date</th>
                        <th class="px-4 py-3 text-center">Items</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Delivery</th>
                        <th class="px-4 py-3 text-right"></th>
                    </tr>
                </thead>

                <tbody class="divide-y">
                    <tr
                        v-for="po in orders"
                        :key="po.uuid"
                        class="hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ po.code }}
                        </td>

                        <td class="px-4 py-3 text-gray-600">
                            {{ formatDate(po.order_date) || '-' }}
                        </td>

                        <td class="px-4 py-3 text-center">
                            {{ po.total_items }}
                        </td>

                        <td class="px-4 py-3">
                            <span
                                class="px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="statusClass(po.status)"
                            >
                                {{ capitalize(po.status) }}
                            </span>
                        </td>

                        <td class="px-4 py-3">
                            <div class="text-xs text-gray-500 mb-1">
                                {{ po.delivered_qty }} / {{ po.ordered_qty }}
                            </div>

                            <div class="w-32 bg-gray-200 rounded-full h-1.5">
                                <div
                                    class="bg-indigo-600 h-1.5 rounded-full"
                                    :style="{ width: po.delivery_percent + '%' }"
                                ></div>
                            </div>
                        </td>

                        <td class="px-4 py-3 text-right">
                            <Link
                                :href="route('purchase-orders.deliveries.index', po.uuid)"
                                class="text-indigo-600 hover:underline"
                            >
                                Deliveries
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</template>
