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

const activeFilterCount = computed(() => {
    let count = 0
    if (String(search.value || '').trim() !== '') count += 1
    if (projectId.value) count += 1
    if (supplierId.value) count += 1
    if (from.value) count += 1
    if (to.value) count += 1
    return count
})

const pageRows = computed(() => deliveries.value?.data ?? [])
const dueCount = computed(() => pageRows.value.filter((row) => row.is_due_alert).length)
const avgProgress = computed(() => {
    if (!pageRows.value.length) return 0

    const total = pageRows.value.reduce((sum, row) => sum + Number(row.delivery_percent ?? 0), 0)
    return Math.round(total / pageRows.value.length)
})

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

function setDatePreset(key) {
    const now = new Date()
    const toIso = (value) => value.toISOString().slice(0, 10)

    if (key === '7d') {
        const start = new Date(now)
        start.setDate(start.getDate() - 6)
        from.value = toIso(start)
        to.value = toIso(now)
        return
    }

    if (key === '30d') {
        const start = new Date(now)
        start.setDate(start.getDate() - 29)
        from.value = toIso(start)
        to.value = toIso(now)
        return
    }

    if (key === 'month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1)
        from.value = toIso(start)
        to.value = toIso(now)
    }
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

function statusLabel(value) {
    if (!value) return 'Not Started'
    return String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (m) => m.toUpperCase())
}

function statusClass(value) {
    if (value === 'preparation') return 'bg-amber-100 text-amber-700'
    if (value === 'transit') return 'bg-blue-100 text-blue-700'
    if (value === 'warehouse') return 'bg-green-100 text-green-700'
    if (value === 'returned') return 'bg-red-100 text-red-700'
    return 'bg-gray-100 text-gray-600'
}

function progressBarClass(percent) {
    if (Number(percent) >= 100) return 'bg-emerald-600'
    if (Number(percent) >= 70) return 'bg-indigo-600'
    return 'bg-amber-500'
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="font-semibold text-xl text-gray-800">Delivery</h2>
                <p class="text-sm text-gray-500">Track purchase order delivery progress and jump into timeline updates.</p>
            </div>
        </template>

        <div class="space-y-6 p-6">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">In Progress</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-700">{{ counts.in_progress }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Delivered</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-700">{{ counts.delivered }}</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Due Alerts (page)</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-700">{{ dueCount }}</p>
                </div>
            </div>

            <StandardFilterBar
                title="Filters"
                description="Track all purchase orders by delivery progress."
                @apply="applyFilters"
                @reset="resetFilters"
            >
                <template #header-actions>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">
                            {{ activeFilterCount }} active
                        </span>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                            @click="resetFilters"
                        >
                            Clear all
                        </button>
                    </div>
                </template>

                <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-12">
                    <div class="flex flex-col xl:col-span-4">
                        <label class="text-sm font-medium">Search</label>
                        <input
                            v-model="search"
                            class="border rounded px-3 py-2"
                            placeholder="PO No / Supplier / Project"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="flex flex-col xl:col-span-2">
                        <label class="text-sm font-medium">Project</label>
                        <select v-model="projectId" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col xl:col-span-2">
                        <label class="text-sm font-medium">Supplier</label>
                        <select v-model="supplierId" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.company_name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col xl:col-span-2">
                        <label class="text-sm font-medium">From</label>
                        <input v-model="from" type="date" class="border rounded px-3 py-2" />
                    </div>

                    <div class="flex flex-col xl:col-span-2">
                        <label class="text-sm font-medium">To</label>
                        <input v-model="to" type="date" class="border rounded px-3 py-2" />
                    </div>

                    <div class="xl:col-span-12 flex flex-wrap items-center gap-2 pt-1">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick Date</span>
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                            @click="setDatePreset('7d')"
                        >
                            Last 7 Days
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                            @click="setDatePreset('30d')"
                        >
                            Last 30 Days
                        </button>
                        <button
                            type="button"
                            class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                            @click="setDatePreset('month')"
                        >
                            This Month
                        </button>
                    </div>
                </div>
            </StandardFilterBar>

            <div class="rounded-xl border border-gray-200 bg-white p-2">
                <div class="flex flex-wrap gap-2">
                    <button
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="tab === 'in_progress' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-100'"
                        @click="switchTab('in_progress')"
                    >
                        In Progress
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700 border border-gray-200">
                            {{ counts.in_progress }}
                        </span>
                    </button>

                    <button
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="tab === 'delivered' ? 'bg-emerald-50 text-emerald-700' : 'text-gray-600 hover:bg-gray-100'"
                        @click="switchTab('delivered')"
                    >
                        Delivered
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-gray-700 border border-gray-200">
                            {{ counts.delivered }}
                        </span>
                    </button>

                    <span class="ml-auto inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                        Avg Progress (page): {{ avgProgress }}%
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-white">
                <table class="min-w-[1280px] divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 whitespace-nowrap">
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
                        <tr
                            v-for="row in deliveries.data"
                            :key="row.id"
                            class="hover:bg-indigo-50/40"
                        >
                            <td class="px-4 py-3 font-medium text-gray-900 whitespace-nowrap">{{ row.code }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.supplier }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ row.project }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.order_date) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.ordered_qty }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.delivered_qty }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ row.remaining_qty }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusClass(row.current_status)"
                                >
                                    {{ statusLabel(row.current_status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span>{{ formatDate(row.current_eod_date) }}</span>
                                    <span
                                        v-if="row.is_due_alert"
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                                    >
                                        Due
                                    </span>
                                </div>
                            </td>
                            <td class="w-52 px-4 py-3 whitespace-nowrap">
                                <div class="h-2 w-full rounded bg-gray-200">
                                    <div
                                        class="h-2 rounded"
                                        :class="progressBarClass(row.delivery_percent)"
                                        :style="{ width: `${row.delivery_percent}%` }"
                                    ></div>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">{{ row.delivery_percent }}%</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    class="rounded bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700"
                                    @click="goToPoDelivery(row)"
                                >
                                    View Delivery
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!deliveries.data?.length">
                            <td colspan="11" class="px-4 py-8 text-center text-gray-400">No records found</td>
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
                    class="rounded border px-3 py-1 text-sm"
                    :class="{ 'cursor-not-allowed text-gray-400': !link.url, 'bg-gray-100': link.active }"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
