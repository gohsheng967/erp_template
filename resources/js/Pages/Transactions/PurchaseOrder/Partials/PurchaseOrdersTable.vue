<script setup>
import { useFormat } from '@/Composables/useFormat'

const { formatDate } = useFormat()

const emit = defineEmits(['view', 'delivery', 'invoice'])

const props = defineProps({
    pos: {
        type: Object, // Inertia paginator
        required: true,
    },
})

function isOverdue(po) {
    if (po.confirmed_at) return false
    if (!po.created_at) return false

    const created = new Date(po.created_at)
    const now = new Date()

    const diffDays =
        (now.getTime() - created.getTime()) / (1000 * 60 * 60 * 24)

    return diffDays >= 3
}

</script>

<template>
    <div class="overflow-x-auto border rounded bg-white">

        <table class="min-w-full text-sm">

            <!-- HEADER -->
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">
                        PO No
                    </th>

                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase">
                        Supplier
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase">
                        Order Status
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase">
                        Delivered
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase">
                        Payment
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase">
                        Action
                    </th>
                </tr>
            </thead>

            <!-- BODY -->
            <tbody>
                <tr
                    v-for="po in pos.data"
                    :key="po.uuid"
                    class="border-t hover:bg-gray-50"
                >
                    <!-- PO NO -->
                    <td class="px-4 py-2 font-medium text-indigo-600">
                        {{ po.code }}
                    </td>

                    <!-- SUPPLIER -->
                    <td class="px-4 py-2">
                        {{ po.supplier?.company_name ?? '-' }}
                    </td>
                    <!-- STATUS -->
                    <td class="px-4 py-2 text-center">
                        <span
                            v-if="!po.confirmed_at"
                            :title="isOverdue(po)
                                ? `Pending confirmation for more than 3 days`
                                : `Issued on ${formatDate(po.created_at)}`"
                            :class="[
                                'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs cursor-help',
                                isOverdue(po)
                                    ? 'bg-amber-200 text-amber-800 animate-pulse-soft'
                                    : 'bg-amber-100 text-amber-700'
                            ]"
                        >
                            <i class="mdi mdi-clock-outline text-xs"></i>
                            Issued
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center gap-1
                                px-2 py-0.5 rounded-full text-xs
                                bg-green-100 text-green-700"
                        >
                            <i class="mdi mdi-check-decagram-outline text-xs"></i>
                            Confirmed
                        </span>
                    </td>

                    <!-- DELIVERED (PLACEHOLDER) -->
                    <td class="px-4 py-2 text-center">
                        <div class="flex flex-col items-center gap-1">
                            <div class="w-20 bg-gray-200 rounded h-2">
                                <div
                                    class="bg-indigo-600 h-2 rounded"
                                    :style="{ width: po.delivery_percent + '%' }"
                                ></div>
                            </div>
                            <span class="text-xs text-gray-500">
                                {{ po.delivery_percent }}%
                            </span>
                        </div>
                    </td>

                    <!-- PAYMENT STATUS (PLACEHOLDER) -->
                    <td class="px-4 py-2 text-center">
                        <span
                            class="px-2 py-0.5 text-xs rounded-full bg-gray-200 text-gray-600"
                        >
                            {{ po.payment_status }}
                        </span>
                    </td>

                    <!-- ACTIONS -->
                    <td class="px-4 py-2">
                        <div class="flex justify-center gap-2">

                            <!-- VIEW DOCUMENT -->
                            <button
                                @click.stop="emit('view', po)"
                                title="View PO Document"
                                class="text-gray-600 hover:text-indigo-600"
                            >
                                <i class="mdi mdi-file-document-outline text-lg"></i>
                            </button>

                            <!-- UPDATE DELIVERY -->
                            <button
                                @click.stop="emit('delivery', po)"
                                title="Go To Delivery"
                                class="text-gray-600 hover:text-orange-600"
                            >
                                <i class="mdi mdi-truck-outline text-lg"></i>
                            </button>

                            <!-- CREATE INVOICE -->
                            <button
                                @click.stop="emit('invoice', po)"
                                title="Create Invoice"
                                class="text-gray-600 hover:text-purple-600"
                            >
                                <i class="mdi mdi-receipt-text-outline text-lg"></i>
                            </button>


                        </div>
                    </td>
                </tr>

                <!-- EMPTY -->
                <tr v-if="!pos.data.length">
                    <td
                        colspan="5"
                        class="py-6 text-center text-gray-400"
                    >
                        No purchase orders found
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
