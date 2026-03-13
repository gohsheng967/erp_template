<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'

const page = usePage()

const deliveries = computed(() => page.props.deliveries)
const filters = page.props.filters ?? {}
const counts = computed(() => page.props.counts ?? { in_progress: 0, delivered: 0 })
const projects = computed(() => page.props.projects ?? [])
const suppliers = computed(() => page.props.suppliers ?? [])

const tab = ref(filters.tab ?? 'in_progress')
const search = ref(filters.search ?? '')
const projectId = ref(filters.project_id ?? '')
const supplierId = ref(filters.supplier_id ?? '')
const from = ref(filters.from ?? '')
const to = ref(filters.to ?? '')

function applyFilters() {
    router.get(route('deliveries.index'), {
        tab: tab.value,
        search: search.value,
        project_id: projectId.value,
        supplier_id: supplierId.value,
        from: from.value,
        to: to.value,
    }, {
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters() {
    search.value = ''
    projectId.value = ''
    supplierId.value = ''
    from.value = ''
    to.value = ''
    applyFilters()
}

function switchTab(nextTab) {
    tab.value = nextTab
    applyFilters()
}

function goToPoDelivery(row) {
    router.visit(route('purchase-orders.deliveries.index', row.uuid))
}

function formatDate(value) {
    if (!value) return '-'
    return new Date(value).toLocaleDateString('en-GB')
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800">Delivery</h2>
        </template>

        <div class="p-6 space-y-6">
            <StandardFilterBar
                title="Filters"
                description="Track all purchase orders by delivery progress."
                @apply="applyFilters"
                @reset="resetFilters"
            >
                <div class="flex flex-col w-full md:w-1/3">
                    <label class="text-sm font-medium">Search</label>
                    <input
                        v-model="search"
                        class="border rounded px-3 py-2"
                        placeholder="PO No / Supplier / Project"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <div class="flex flex-col w-52">
                    <label class="text-sm font-medium">Project</label>
                    <select v-model="projectId" class="border rounded px-3 py-2">
                        <option value="">All</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col w-52">
                    <label class="text-sm font-medium">Supplier</label>
                    <select v-model="supplierId" class="border rounded px-3 py-2">
                        <option value="">All</option>
                        <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                            {{ supplier.company_name }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col w-40">
                    <label class="text-sm font-medium">From</label>
                    <input v-model="from" type="date" class="border rounded px-3 py-2" />
                </div>

                <div class="flex flex-col w-40">
                    <label class="text-sm font-medium">To</label>
                    <input v-model="to" type="date" class="border rounded px-3 py-2" />
                </div>
            </StandardFilterBar>

            <div class="bg-white rounded shadow border px-4">
                <nav class="flex gap-6">
                    <button
                        class="py-3 font-medium flex items-center gap-2"
                        :class="tab === 'in_progress' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500'"
                        @click="switchTab('in_progress')"
                    >
                        In Progress
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200">
                            {{ counts.in_progress }}
                        </span>
                    </button>

                    <button
                        class="py-3 font-medium flex items-center gap-2"
                        :class="tab === 'delivered' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500'"
                        @click="switchTab('delivered')"
                    >
                        Delivered
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200">
                            {{ counts.delivered }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="overflow-x-auto bg-white rounded-lg border">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">PO No</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Order Date</th>
                            <th class="px-4 py-3 text-right">Ordered Qty</th>
                            <th class="px-4 py-3 text-right">Delivered Qty</th>
                            <th class="px-4 py-3 text-right">Remaining Qty</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">EOD</th>
                            <th class="px-4 py-3">Progress</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in deliveries.data" :key="row.id">
                            <td class="px-4 py-3 font-medium text-gray-900">{{ row.code }}</td>
                            <td class="px-4 py-3">{{ row.supplier }}</td>
                            <td class="px-4 py-3">{{ row.project }}</td>
                            <td class="px-4 py-3">{{ formatDate(row.order_date) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.ordered_qty }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.delivered_qty }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.remaining_qty }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="{
                                        'bg-amber-100 text-amber-700': row.current_status === 'preparation',
                                        'bg-blue-100 text-blue-700': row.current_status === 'transit',
                                        'bg-green-100 text-green-700': row.current_status === 'warehouse',
                                        'bg-red-100 text-red-700': row.current_status === 'returned',
                                    }"
                                >
                                    {{ row.current_status ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span>{{ formatDate(row.current_eod_date) }}</span>
                                    <span
                                        v-if="row.is_due_alert"
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium bg-amber-100 text-amber-700"
                                    >
                                        Due
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 w-52">
                                <div class="w-full h-2 bg-gray-200 rounded">
                                    <div class="h-2 bg-indigo-600 rounded" :style="{ width: `${row.delivery_percent}%` }"></div>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">{{ row.delivery_percent }}%</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="px-3 py-1.5 rounded bg-indigo-600 text-white text-xs hover:bg-indigo-700"
                                    @click="goToPoDelivery(row)"
                                >
                                    View Delivery
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!deliveries.data?.length">
                            <td colspan="11" class="px-4 py-6 text-center text-gray-400">No records found</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="deliveries?.links?.length" class="flex gap-1">
                <button
                    v-for="link in deliveries.links"
                    :key="link.label"
                    :disabled="!link.url"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                    :class="{ 'text-gray-400 cursor-not-allowed': !link.url, 'bg-gray-100': link.active }"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
