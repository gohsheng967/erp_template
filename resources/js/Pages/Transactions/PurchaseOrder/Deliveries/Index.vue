<script setup>
import { ref, computed, inject } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import AddDeliveryModal from './Partials/AddDeliveryModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import { router } from '@inertiajs/vue3'

const showAddDelivery = ref(false)

const showDeleteConfirm = ref(false)
const deletingDelivery = ref(null)

function confirmDelete(delivery) {
    deletingDelivery.value = delivery
    showDeleteConfirm.value = true
}

function closeDelete() {
    showDeleteConfirm.value = false
    deletingDelivery.value = null
}

function deleteDelivery() {
    if (!deletingDelivery.value) return

    router.delete(
        route('purchase-orders.deliveries.destroy', deletingDelivery.value.uuid),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value.show('Delivery deleted', 'success')
                closeDelete()
            },
            onError: () => {
                toast?.value.show('Failed to delete delivery', 'error')
            },
        }
    )
}


function openAddDelivery() {
    showAddDelivery.value = true
}

function closeAddDelivery() {
    showAddDelivery.value = false
    router.reload({
        preserveScroll: true,
        preserveState: false, 
    })
}

const toast = inject('toast', null)
const { formatDate } = useFormat()

const props = defineProps({
    purchaseOrder: Object,
    deliveries: Array,
    warehouses: Array,
})

/* =========================
   BACK
========================= */
function goBack() {
    window.history.back()
}

/* =========================
   EXPAND STATE
========================= */
const expanded = ref({})

function toggleExpand(id) {
    expanded.value[id] = !expanded.value[id]
}

/* =========================
   PO DELIVERY PROGRESS
========================= */
const totalOrderedQty = computed(() => {
    return props.purchaseOrder.items?.reduce(
        (sum, item) => sum + Number(item.quantity ?? 0),
        0
    ) ?? 0
})

const totalDeliveredQty = computed(() => {
    return props.deliveries.reduce((sum, d) => {
        const rowQty = d.items?.reduce(
            (s, i) => s + Number(i.quantity ?? 0),
            0
        ) ?? 0
        return sum + rowQty
    }, 0)
})

const deliveryPercent = computed(() => {
    if (!totalOrderedQty.value) return 0
    return Math.min(
        100,
        Math.round((totalDeliveredQty.value / totalOrderedQty.value) * 100)
    )
})

const isFullyDelivered = computed(() => deliveryPercent.value >= 100)

function fileIcon(file) {
    const name = file.original_name?.toLowerCase() || ''

    if (name.endsWith('.pdf')) return 'mdi-file-pdf-box'
    if (name.match(/\.(jpg|jpeg|png|gif)$/)) return 'mdi-file-image'
    if (name.match(/\.(doc|docx)$/)) return 'mdi-file-word-box'
    if (name.match(/\.(xls|xlsx)$/)) return 'mdi-file-excel-box'

    return 'mdi-file-outline'
}

</script>

<template>
    <AuthenticatedLayout>
        <div class="max-w-5xl mx-auto p-6 space-y-6">

            <!-- HEADER -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button
                        @click="goBack"
                        class="inline-flex items-center gap-2
                               text-sm text-gray-600 hover:text-indigo-600"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back
                    </button>

                    <div>
                        <h1 class="text-xl font-semibold text-gray-800">
                            Delivery Timeline
                        </h1>
                        <p class="text-sm text-gray-500">
                            Purchase Order · {{ purchaseOrder.code }}
                        </p>
                    </div>
                </div>

                <button
                    class="inline-flex items-center gap-2
                        px-3 py-1.5 rounded-md text-sm
                        bg-indigo-600 text-white hover:bg-indigo-700"
                    @click="openAddDelivery"
                >
                    <i class="mdi mdi-plus"></i>
                    Add Delivery Status
                </button>

            </div>

            <!-- ORDER INFO -->
            <div class="bg-white border rounded-xl p-4 text-sm">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-gray-500">Supplier</p>
                        <p class="font-medium">
                            {{ purchaseOrder.supplier?.company_name ?? '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-gray-500">Order Date</p>
                        <p class="font-medium">
                            {{ formatDate(purchaseOrder.order_date) }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- PO DELIVERY PROGRESS -->
            <div class="bg-white border rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700">
                        Delivery Progress
                    </p>

                    <span
                        class="text-xs font-semibold px-2 py-0.5 rounded-full"
                        :class="isFullyDelivered
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-amber-100 text-amber-700'"
                    >
                        {{ isFullyDelivered ? 'FULLY DELIVERED' : 'PARTIAL DELIVERY' }}
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                    <div
                        class="h-3 rounded-full transition-all"
                        :class="isFullyDelivered ? 'bg-emerald-600' : 'bg-indigo-600'"
                        :style="{ width: deliveryPercent + '%' }"
                    ></div>
                </div>

                <div class="mt-2 text-xs text-gray-500 flex justify-between">
                    <span>
                        {{ totalDeliveredQty }} / {{ totalOrderedQty }} delivered
                    </span>
                    <span>
                        {{ deliveryPercent }}%
                    </span>
                </div>
            </div>

            <!-- TIMELINE -->
            <div class="bg-white border rounded-xl p-6">
                <div class="relative border-l border-gray-200 ml-6 space-y-10">

                    <!-- PO CREATED -->
                    <div class="relative flex items-start">
                        <div class="ml-8">
                            <p class="font-semibold text-gray-800">
                                Purchase Order Created
                            </p>
                            <p class="text-sm text-gray-500">
                                {{ formatDate(purchaseOrder.order_date) }}
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                PO {{ purchaseOrder.code }} issued
                            </p>
                        </div>
                    </div>

                    <!-- DELIVERY ROWS -->
                    <div
                        v-for="delivery in deliveries"
                        :key="delivery.uuid"
                        class="relative flex items-start"
                    >
                        <!-- DOT -->
                        <span
                            class="absolute -left-[13px] top-6
                                   w-7 h-7 rounded-full
                                   flex items-center justify-center shadow"
                            :class="{
                                'bg-blue-500': delivery.status === 'transit',
                                'bg-green-500': delivery.status === 'warehouse',
                                'bg-red-500': delivery.status === 'returned',
                            }"
                        >
                            <i class="mdi mdi-truck-outline text-white text-sm"></i>
                        </span>

                        <!-- CARD -->
                        <div
                            class="ml-8 w-full bg-gray-50 border border-gray-200
                                   rounded-xl p-4 hover:bg-white transition"
                        >
                            <!-- HEADER -->
                            <div
                                class="flex items-start justify-between gap-4 cursor-pointer"
                                @click="toggleExpand(delivery.id)"
                            >
                                <div>
                                    <p class="font-semibold text-gray-800">
                                        {{ delivery.title }}
                                    </p>

                                    <div class="flex items-center gap-2 mt-0.5">
                                        <p class="text-sm text-gray-500">
                                            {{ formatDate(delivery.delivery_date) }}
                                        </p>

                                        <!-- STATUS BADGE -->
                                        <span
                                            class="text-xs px-2 py-0.5 rounded-full font-medium"
                                            :class="{
                                                'bg-blue-100 text-blue-700': delivery.status === 'transit',
                                                'bg-green-100 text-green-700': delivery.status === 'warehouse',
                                                'bg-red-100 text-red-700': delivery.status === 'returned',
                                            }"
                                        >
                                            {{ delivery.status.toUpperCase() }}
                                        </span>
                                    </div>
                                </div>


                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-medium px-2.5 py-1 rounded-full"
                                        :class="{
                                            'bg-amber-100 text-amber-700': delivery.delivery_type === 'partial',
                                            'bg-emerald-100 text-emerald-700': delivery.delivery_type === 'full',
                                        }"
                                    >
                                        {{ delivery.delivery_type.toUpperCase() }}
                                    </span>

                                    <i
                                        class="mdi"
                                        :class="expanded[delivery.id]
                                            ? 'mdi-chevron-up'
                                            : 'mdi-chevron-down'"
                                    ></i>
                                </div>
                            </div>

                            <!-- DESCRIPTION -->
                            <p
                                v-if="delivery.description"
                                class="text-sm text-gray-600 mt-2"
                            >
                                {{ delivery.description }}
                            </p>

                            <!-- EXPANDED -->
                            <div v-if="expanded[delivery.id]" class="mt-4 space-y-4">

                                <!-- ITEMS (WAREHOUSE ONLY) -->
                                <div v-if="delivery.status === 'warehouse'">
                                    <p class="text-xs font-semibold text-gray-500 mb-2">
                                        Delivered Items
                                    </p>

                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-gray-400 text-xs">
                                                <th class="text-left">Item</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-left">Destination</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in delivery.items"
                                                :key="item.id"
                                                class="border-t"
                                            >
                                                <td class="py-1">
                                                    {{ item.order_item?.item_name }}
                                                </td>
                                                <td class="text-center">
                                                    {{ item.quantity }}
                                                </td>
                                                <td>
                                                    {{ item.warehouse?.title ?? '-' }}
                                                    <br>
                                                    {{ item.warehouse?.address ?? '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>


                                <!-- ATTACHMENTS -->
                                <div v-if="delivery.attachments?.length">
                                    <p class="text-xs font-semibold text-gray-500 mb-2">
                                        Attachments
                                    </p>

                                    <div class="flex flex-wrap gap-3">
                                        <a
                                            v-for="file in delivery.attachments"
                                            :key="file.id"
                                            :href="file.url"
                                            target="_blank"
                                            class="flex items-center gap-2
                                                px-3 py-1.5 rounded-md
                                                bg-gray-100 hover:bg-gray-200
                                                text-sm text-gray-700"
                                        >
                                            <i
                                                class="mdi text-lg"
                                                :class="fileIcon(file)"
                                            ></i>

                                            <span class="truncate max-w-[160px]">
                                                {{ file.original_name }}
                                            </span>
                                        </a>

                                    </div>
                                </div>

                                <!-- ACTION -->
                                <div class="flex justify-end pt-2">
                                    <button
                                        class="text-sm text-red-600 hover:underline"
                                        @click.stop="confirmDelete(delivery)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EMPTY -->
                    <div
                        v-if="!deliveries.length"
                        class="ml-8 text-sm text-gray-400 italic"
                    >
                        No delivery records yet
                    </div>

                </div>
            </div>
        </div>

        <AddDeliveryModal
            v-if="showAddDelivery"
            :purchase-order="purchaseOrder"
            :warehouses="warehouses"
            @close="closeAddDelivery"
            @saved="closeAddDelivery"
        />
        <DeleteConfirmation
            v-if="showDeleteConfirm"
            title="Delete Delivery"
            message="This will permanently remove the delivery record and reverse delivered quantities (if applicable). Continue?"
            @close="closeDelete"
            @confirm="deleteDelivery"
        />

    </AuthenticatedLayout>
</template>
