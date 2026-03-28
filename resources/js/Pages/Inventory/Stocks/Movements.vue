<script setup>
import { ref, computed } from 'vue'
import { usePage, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const page = usePage()

const warehouses = computed(() => page.props.warehouses ?? [])
const stockCategories = computed(() => page.props.stock_categories ?? [])
const movements = computed(() => page.props.movements ?? [])
const filters = computed(() => page.props.filters ?? {})
const currentUserId = computed(() => Number(page.props.auth?.user?.data?.id ?? page.props.auth?.user?.id ?? 0))
const approveForm = useForm({})

const isDrillDown = computed(() => !!filters.value.warehouse_id && !!filters.value.po_item_id)

const selectedWarehouse = ref('all')
const selectedType = ref('all')
const selectedDestination = ref('all')
const selectedCategory = ref('all')
const search = ref('')

const filteredMovements = computed(() => {
    const keyword = search.value.trim().toLowerCase()

    return movements.value.filter((m) => {
        if (selectedWarehouse.value !== 'all' && Number(m.warehouse_id) !== Number(selectedWarehouse.value)) {
            return false
        }

        if (selectedType.value !== 'all' && m.type !== selectedType.value) {
            return false
        }

        if (selectedDestination.value !== 'all' && (m.issue_destination_type || 'none') !== selectedDestination.value) {
            return false
        }

        if (selectedCategory.value !== 'all' && (m.stock_category || '') !== selectedCategory.value) {
            return false
        }

        if (!keyword) {
            return true
        }

        const blob = [
            m.purchase_order_item?.item_name,
            m.serial_number,
            m.stock_category,
            m.issue_user?.name,
            m.project?.name,
            m.site?.site_name,
            m.purpose,
            m.remark,
            m.warehouse?.title,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()

        return blob.includes(keyword)
    })
})

const totalIn = computed(() =>
    filteredMovements.value
        .filter((m) => m.type === 'IN')
        .reduce((sum, m) => sum + Number(m.quantity || 0), 0)
)

const totalOut = computed(() =>
    filteredMovements.value
        .filter((m) => m.type === 'OUT')
        .reduce((sum, m) => sum + Number(m.quantity || 0), 0)
)

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
        IN: 'bg-emerald-100 text-emerald-700',
        OUT: 'bg-rose-100 text-rose-700',
        TRANSFER: 'bg-blue-100 text-blue-700',
        ADJUST: 'bg-amber-100 text-amber-700',
    }[type]
}

function destinationLabel(m) {
    if (m.issue_destination_type === 'office') return 'Office'
    if (m.issue_destination_type === 'project') return `${m.project?.name || 'Project'} / ${m.site?.site_name || '-'}`
    if (m.issue_destination_type === 'user') return m.destination_user?.name || '-'
    return '-'
}

function resetFilters() {
    selectedWarehouse.value = 'all'
    selectedType.value = 'all'
    selectedDestination.value = 'all'
    selectedCategory.value = 'all'
    search.value = ''
}

function approvalLabel(m) {
    if (!m.issuer_id || m.type !== 'OUT') return '-'
    return m.issuer_approved_at ? 'Approved' : 'Pending'
}

function approvalClass(m) {
    if (!m.issuer_id || m.type !== 'OUT') return 'bg-gray-100 text-gray-600'
    return m.issuer_approved_at ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
}

function canApprove(m) {
    if (m.type !== 'OUT') return false
    if (!m.issuer_id || m.issuer_approved_at) return false
    return Number(m.issuer_id) === currentUserId.value
}

function approveIssue(m) {
    if (!canApprove(m) || approveForm.processing) return

    approveForm.post(route('inventory.stocks.approve-issue', m.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="flex items-center gap-3">
                    <button
                        @click="router.visit(route('inventory.stocks.index'))"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back
                    </button>

                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Inventory Movements</h2>
                        <p class="text-sm text-gray-500">Track every in/out/transfer/adjust with issue context.</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        @click="router.visit(route('inventory.stocks.browse'))"
                    >
                        Browse Stocks
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-6">
                    <input
                        v-model="search"
                        type="text"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm"
                        placeholder="Search item / SN / project / purpose"
                    />

                    <select v-model="selectedWarehouse" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="all">All Warehouses</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.title }}</option>
                    </select>

                    <select v-model="selectedType" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="all">All Types</option>
                        <option value="IN">Stock In</option>
                        <option value="OUT">Stock Out</option>
                        <option value="TRANSFER">Transfer</option>
                        <option value="ADJUST">Adjustment</option>
                    </select>

                    <select v-model="selectedDestination" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="all">All Destinations</option>
                        <option value="office">Office</option>
                        <option value="project">Project</option>
                        <option value="none">Not Set</option>
                    </select>

                    <select v-model="selectedCategory" class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="all">All Categories</option>
                        <option v-for="category in stockCategories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Rows</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ filteredMovements.length }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-emerald-700">Total In</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-800">{{ totalIn }}</p>
                </div>
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-rose-700">Total Out</p>
                    <p class="mt-1 text-2xl font-semibold text-rose-800">{{ totalOut }}</p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Warehouse</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-center">Type</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-left">SN</th>
                            <th class="px-3 py-2 text-left">Category</th>
                            <th class="px-3 py-2 text-left">Issued To</th>
                            <th class="px-3 py-2 text-left">Holder</th>
                            <th class="px-3 py-2 text-left">Issued By</th>
                            <th class="px-3 py-2 text-left">Issuer</th>
                            <th class="px-3 py-2 text-center">Issuer Approval</th>
                            <th class="px-3 py-2 text-center">Proof</th>
                            <th class="px-3 py-2 text-right">Balance</th>
                            <th class="px-3 py-2 text-left">Purpose</th>
                            <th class="px-3 py-2 text-left">Remark</th>
                            <th class="px-3 py-2 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        <tr v-for="m in filteredMovements" :key="m.id" class="hover:bg-gray-50/80">
                            <td class="px-3 py-2">{{ new Date(m.created_at).toLocaleString() }}</td>
                            <td class="px-3 py-2">{{ m.warehouse?.title || '-' }}</td>
                            <td class="px-3 py-2">{{ m.purchase_order_item?.item_name || '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="typeClass(m.type)">{{ typeLabel(m.type) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-medium">{{ m.quantity }}</td>
                            <td class="px-3 py-2">{{ m.serial_number || '-' }}</td>
                            <td class="px-3 py-2">{{ m.stock_category || '-' }}</td>
                            <td class="px-3 py-2">{{ destinationLabel(m) }}</td>
                            <td class="px-3 py-2">{{ m.holder_user?.name || '-' }}</td>
                            <td class="px-3 py-2">{{ m.issue_user?.name || '-' }}</td>
                            <td class="px-3 py-2">{{ m.issuer?.name || '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="approvalClass(m)"
                                >
                                    {{ approvalLabel(m) }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <a
                                    v-if="m.attachments?.length"
                                    :href="m.attachments[0].url"
                                    target="_blank"
                                    class="inline-flex rounded border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 hover:bg-sky-100"
                                >
                                    {{ m.attachments.length }} file(s)
                                </a>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ m.balance_after }}</td>
                            <td class="px-3 py-2">{{ m.purpose || '-' }}</td>
                            <td class="px-3 py-2">{{ m.remark || '-' }}</td>
                            <td class="px-3 py-2 text-center">
                                <button
                                    v-if="canApprove(m)"
                                    type="button"
                                    class="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                    :disabled="approveForm.processing"
                                    @click="approveIssue(m)"
                                >
                                    Approve
                                </button>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                        </tr>

                        <tr v-if="!filteredMovements.length">
                            <td colspan="17" class="px-3 py-10 text-center text-sm text-gray-500">No movements found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
