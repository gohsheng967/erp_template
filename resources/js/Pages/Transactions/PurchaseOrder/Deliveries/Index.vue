<script setup>
import { ref, computed, inject } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import AddDeliveryModal from './Partials/AddDeliveryModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import { router } from '@inertiajs/vue3'

const showAddDelivery = ref(false)
const activeStatus = ref('all')
const deliverySearch = ref('')

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
    stockCategories: Array,
})

function goBack() {
    window.history.back()
}

const expanded = ref({})

function toggleExpand(id) {
    expanded.value[id] = !expanded.value[id]
}

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
const sortedDeliveries = computed(() =>
    [...props.deliveries].sort((a, b) => {
        const ad = new Date(a.delivery_date ?? a.created_at ?? 0).getTime()
        const bd = new Date(b.delivery_date ?? b.created_at ?? 0).getTime()
        return bd - ad
    })
)

const statusMeta = {
    all: { label: 'All', hint: 'Show all delivery updates in timeline order.' },
    preparation: { label: 'Preparation', hint: 'Pending delivery planning and preparation updates.' },
    transit: { label: 'Transit', hint: 'Items currently moving to destination.' },
    warehouse: { label: 'Warehouse / Office', hint: 'Items delivered to warehouse or office.' },
    returned: { label: 'Returned', hint: 'Returned delivery records.' },
}

const deliveryCounts = computed(() => {
    const base = {
        all: sortedDeliveries.value.length,
        preparation: 0,
        transit: 0,
        warehouse: 0,
        returned: 0,
    }

    sortedDeliveries.value.forEach((row) => {
        const key = String(row.status ?? '')
        if (Object.prototype.hasOwnProperty.call(base, key)) {
            base[key] += 1
        }
    })

    return base
})

const filteredDeliveries = computed(() => {
    const keyword = String(deliverySearch.value ?? '').trim().toLowerCase()

    return sortedDeliveries.value.filter((row) => {
        if (activeStatus.value !== 'all' && row.status !== activeStatus.value) {
            return false
        }

        if (!keyword) return true

        const blob = [
            row.title,
            row.description,
            row.status,
            row.delivery_type,
            ...(row.items ?? []).map((item) => item.order_item?.item_name),
            ...(row.items ?? []).map((item) => item.serial_number),
            ...(row.items ?? []).map((item) => item.stock_category),
            ...(row.items ?? []).map((item) => item.warehouse?.title),
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return blob.includes(keyword)
    })
})

const activeStatusHint = computed(() => statusMeta[activeStatus.value]?.hint ?? '')
const latestDelivery = computed(() => sortedDeliveries.value[0] ?? null)
const totalAttachmentCount = computed(() =>
    (props.deliveries ?? []).reduce((sum, row) => sum + Number(row.attachments?.length ?? 0), 0)
)
const isDueAlert = computed(() => {
    const row = latestDelivery.value
    if (!row || row.status !== 'preparation' || !row.eod_date) return false
    const today = new Date().toISOString().slice(0, 10)
    return today >= row.eod_date
})

function resetTimelineFilters() {
    activeStatus.value = 'all'
    deliverySearch.value = ''
}

function expandAllFiltered() {
    const nextState = {}
    filteredDeliveries.value.forEach((row) => {
        nextState[row.id] = true
    })
    expanded.value = nextState
}

function collapseAllFiltered() {
    const nextState = { ...expanded.value }
    filteredDeliveries.value.forEach((row) => {
        nextState[row.id] = false
    })
    expanded.value = nextState
}

function statusDotClass(status) {
    if (status === 'preparation') return 'bg-amber-500'
    if (status === 'transit') return 'bg-blue-500'
    if (status === 'warehouse') return 'bg-green-500'
    if (status === 'returned') return 'bg-red-500'
    return 'bg-gray-400'
}

function statusBadgeClass(status) {
    if (status === 'preparation') return 'bg-amber-100 text-amber-700'
    if (status === 'transit') return 'bg-blue-100 text-blue-700'
    if (status === 'warehouse') return 'bg-green-100 text-green-700'
    if (status === 'returned') return 'bg-red-100 text-red-700'
    return 'bg-gray-100 text-gray-700'
}

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
        <div class="mx-auto max-w-6xl space-y-6 p-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-center gap-4">
                    <button
                        @click="goBack"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back
                    </button>

                    <div>
                        <h1 class="text-xl font-semibold text-gray-800">
                            Delivery Timeline
                        </h1>
                        <p class="text-sm text-gray-500">
                            Purchase Order - {{ purchaseOrder.code }}
                        </p>
                    </div>
                </div>

                <button
                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                    @click="openAddDelivery"
                >
                    <i class="mdi mdi-plus"></i>
                    Add Delivery Status
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Supplier</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ purchaseOrder.supplier?.company_name ?? '-' }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Order Date</p>
                    <p class="mt-1 font-semibold text-gray-900">{{ formatDate(purchaseOrder.order_date) }}</p>
                </div>

                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-indigo-700">Records</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-800">{{ deliveries.length }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <button
                    v-for="status in ['all', 'preparation', 'transit', 'warehouse', 'returned']"
                    :key="status"
                    type="button"
                    class="rounded-xl border p-3 text-left transition"
                    :class="activeStatus === status
                        ? 'border-indigo-300 bg-indigo-50'
                        : 'border-gray-200 bg-white hover:border-indigo-200'"
                    @click="activeStatus = status"
                >
                    <p class="text-[11px] uppercase tracking-wide text-gray-500">
                        {{ statusMeta[status].label }}
                    </p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ deliveryCounts[status] ?? 0 }}
                    </p>
                </button>
            </div>

            <div class="bg-white border rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700">Delivery Progress</p>

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

            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(meta, key) in statusMeta"
                                :key="key"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                                :class="activeStatus === key
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                                @click="activeStatus = key"
                            >
                                <span>{{ meta.label }}</span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                                    {{ deliveryCounts[key] ?? 0 }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div class="lg:col-span-4">
                        <input
                            v-model="deliverySearch"
                            type="text"
                            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            placeholder="Search title, item, SN, category"
                        />
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="expandAllFiltered"
                    >
                        Expand All
                    </button>
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="collapseAllFiltered"
                    >
                        Collapse All
                    </button>
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="resetTimelineFilters"
                    >
                        Reset Filters
                    </button>
                    <span class="ml-auto text-xs text-gray-500">
                        Showing {{ filteredDeliveries.length }} of {{ deliveries.length }} updates · {{ totalAttachmentCount }} attachments
                    </span>
                </div>
            </div>

            <div class="rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                <span class="mr-2 font-semibold">{{ statusMeta[activeStatus]?.label }}:</span>
                <span>{{ activeStatusHint }}</span>
            </div>

            <div
                v-if="isDueAlert"
                class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                EOD alert: latest status is in preparation and has reached/passed EOD date
                ({{ latestDelivery?.eod_date }}). Update next progress status.
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6">
                <div class="relative ml-6 space-y-10 border-l border-gray-200">
                    <div class="relative flex items-start">
                        <div class="ml-8">
                            <p class="font-semibold text-gray-800">Purchase Order Created</p>
                            <p class="text-sm text-gray-500">{{ formatDate(purchaseOrder.order_date) }}</p>
                            <p class="mt-1 text-sm text-gray-600">PO {{ purchaseOrder.code }} issued</p>
                        </div>
                    </div>

                    <div
                        v-for="delivery in filteredDeliveries"
                        :key="delivery.uuid"
                        class="relative flex items-start"
                    >
                        <span
                            class="absolute -left-[13px] top-6 flex h-7 w-7 items-center justify-center rounded-full shadow"
                            :class="statusDotClass(delivery.status)"
                        >
                            <i class="mdi mdi-truck-outline text-sm text-white"></i>
                        </span>

                        <div class="ml-8 w-full rounded-xl border border-gray-200 bg-gray-50 p-4 transition hover:bg-white">
                            <div
                                class="flex cursor-pointer items-start justify-between gap-4"
                                @click="toggleExpand(delivery.id)"
                            >
                                <div>
                                    <p class="font-semibold text-gray-900">{{ delivery.title }}</p>

                                    <div class="mt-0.5 flex items-center gap-2">
                                        <p class="text-sm text-gray-500">{{ formatDate(delivery.delivery_date) }}</p>

                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="statusBadgeClass(delivery.status)"
                                        >
                                            {{ (statusMeta[delivery.status]?.label ?? delivery.status).toUpperCase() }}
                                        </span>
                                        <span v-if="delivery.eod_date" class="text-xs text-gray-500">
                                            EOD: {{ formatDate(delivery.eod_date) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="{
                                            'bg-amber-100 text-amber-700': delivery.delivery_type === 'partial',
                                            'bg-emerald-100 text-emerald-700': delivery.delivery_type === 'full',
                                        }"
                                    >
                                        {{ delivery.delivery_type.toUpperCase() }}
                                    </span>

                                    <i
                                        class="mdi"
                                        :class="expanded[delivery.id] ? 'mdi-chevron-up' : 'mdi-chevron-down'"
                                    ></i>
                                </div>
                            </div>

                            <p
                                v-if="delivery.description"
                                class="mt-2 text-sm text-gray-600"
                            >
                                {{ delivery.description }}
                            </p>

                            <div v-if="expanded[delivery.id]" class="mt-4 space-y-4">
                                <div v-if="delivery.status === 'warehouse'">
                                    <p class="mb-2 text-xs font-semibold text-gray-500">Delivered Items</p>

                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-xs text-gray-400">
                                                <th class="text-left">Item</th>
                                                <th class="text-center">Qty</th>
                                                <th class="text-left">Serial Number</th>
                                                <th class="text-left">Stock Category</th>
                                                <th class="text-left">Destination</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="item in delivery.items"
                                                :key="item.id"
                                                class="border-t"
                                            >
                                                <td class="py-1">{{ item.order_item?.item_name }}</td>
                                                <td class="text-center">{{ item.quantity }}</td>
                                                <td>{{ item.serial_number || '-' }}</td>
                                                <td>{{ item.stock_category || '-' }}</td>
                                                <td>
                                                    {{ item.warehouse?.title ?? '-' }}
                                                    <br>
                                                    {{ item.warehouse?.address ?? '-' }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div v-if="delivery.attachments?.length">
                                    <p class="mb-2 text-xs font-semibold text-gray-500">Attachments</p>

                                    <div class="flex flex-wrap gap-3">
                                        <a
                                            v-for="file in delivery.attachments"
                                            :key="file.id"
                                            :href="file.url"
                                            target="_blank"
                                            class="flex items-center gap-2 rounded-md bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200"
                                        >
                                            <i class="mdi text-lg" :class="fileIcon(file)"></i>

                                            <span class="max-w-[160px] truncate">{{ file.original_name }}</span>
                                        </a>
                                    </div>
                                </div>

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

                    <div
                        v-if="!filteredDeliveries.length"
                        class="ml-8 text-sm italic text-gray-400"
                    >
                        No delivery records match the current filters.
                    </div>
                </div>
            </div>
        </div>

        <AddDeliveryModal
            v-if="showAddDelivery"
            :purchase-order="purchaseOrder"
            :warehouses="warehouses"
            :stock-categories="stockCategories"
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
