<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import ArInvoicesTable from '@/Pages/Transactions/ArInvoices/Partials/ArInvoicesTable.vue'
import ArInvoicesPie from '@/Components/Charts/ClaimsPie.vue'
import CreateArInvoiceModal from '@/Pages/Transactions/ArInvoices/Partials/CreateArInvoiceModal.vue'
import { useFormat } from '@/Composables/useFormat'

const page = usePage()
const { formatCurrency } = useFormat()

/* ========================
   Props from controller
======================== */
const invoices = computed(() => page.props.invoices)
const counts   = computed(() => page.props.counts)
const filters  = page.props.filters
const donut    = page.props.donut ?? {}

const prefill = computed(() => page.props.prefill ?? {})
const showCreate = ref(prefill.value.open_create ?? false)

/* ========================
   Filter state
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)
const customerId = ref(filters.customer_id ?? null)
const approvedTotals = computed(() => page.props.approvedTotals ?? null)

/* ========================
   Tabs
======================== */
const tabs = [
    { key: 'draft',     label: 'Draft' },
    { key: 'issued',    label: 'Issued' },
    { key: 'approved',  label: 'Approved' },
    { key: 'received',  label: 'Received' },
    { key: 'cancelled', label: 'Cancelled' },
]

const colors = [
  "#6366f1", "#22c55e", "#f59e0b",
  "#ef4444", "#0ea5e9", "#a855f7",
  "#64748b"
]

const activeTab = ref(page.props.activeTab ?? 'issued')

/* ========================
   Current tab data
======================== */
const currentInvoices = computed(() => {
    return invoices.value?.[activeTab.value] ?? {
        data: [],
        links: [],
    }
})

/* ========================
   Donut visibility
======================== */
const hasProjectDonut = computed(() =>
    donut?.by_project?.some(i => Number(i.amount) > 0)
)

const hasCustomerDonut = computed(() =>
    donut?.by_customer?.some(i => Number(i.amount) > 0)
)

/* ========================
   Badge tabs
======================== */
const badgeTabs = ['issued', 'approved']

/* ========================
   Actions
======================== */
function applyFilters() {
    router.get(
        route('ar-invoices.index'),
        {
            search: search.value,
            from: dateFrom.value,
            to: dateTo.value,
            tab: activeTab.value,
            customer_id: customerId.value ?? null,
        },
        {
            preserveScroll: true,
            replace: true,
        }
    )
}

function resetFilters() {
    const today = new Date().toISOString().slice(0, 10)

    search.value = ''
    dateFrom.value = today
    dateTo.value = today

    applyFilters()
}

function switchTab(tab) {
    activeTab.value = tab
    applyFilters()
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    AR Invoice Management
                </h2>

                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create Invoice
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <div class="bg-white p-4 rounded-lg shadow border">
                <div class="flex flex-wrap gap-4 items-end">

                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="text-sm font-medium">Search</label>
                        <input
                            v-model="search"
                            class="border rounded px-3 py-2"
                            placeholder="Invoice No / Title / Customer"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">From</label>
                        <input
                            type="date"
                            v-model="dateFrom"
                            class="border rounded px-3 py-2"
                        />
                    </div>

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">To</label>
                        <input
                            type="date"
                            v-model="dateTo"
                            class="border rounded px-3 py-2"
                        />
                    </div>

                    <button
                        class="px-4 py-2 h-10 bg-gray-200 rounded"
                        @click="applyFilters"
                    >
                        Apply
                    </button>

                    <button
                        class="px-4 py-2 h-10 bg-red-200 rounded"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- TABS -->
            <div class="bg-white rounded shadow border px-4">
                <nav class="flex gap-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="switchTab(tab.key)"
                        class="py-3 font-medium flex items-center gap-2"
                        :class="{
                            'border-b-2 border-indigo-600 text-indigo-600': activeTab === tab.key,
                            'text-gray-500': activeTab !== tab.key
                        }"
                    >
                        {{ tab.label }}

                        <span
                            v-if="badgeTabs.includes(tab.key)"
                            class="px-2 py-0.5 text-xs rounded-full bg-gray-200"
                        >
                            {{ counts?.[tab.key] ?? 0 }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- DONUTS -->
            <div
                v-if="hasProjectDonut || hasCustomerDonut"
                class="grid grid-cols-1 md:grid-cols-2 gap-3"
            >
                <!-- BY PROJECT -->
                <div
                    v-if="hasProjectDonut"
                    class="bg-white rounded-md border shadow-sm p-2 h-[280px] flex flex-col"
                >
                    <h3 class="text-[11px] font-medium text-gray-500 mb-6">
                        By Project
                    </h3>

                    <div class="flex justify-center">
                        <div class="flex items-center gap-4">
                            <div class="w-[180px] h-[180px] relative flex-shrink-0">
                                <ArInvoicesPie :donut="donut.by_project" />
                            </div>

                            <div class="flex flex-col gap-1 text-xs text-gray-600">
                                <div
                                    v-for="(item, i) in donut.by_project"
                                    :key="i"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="inline-block w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: colors[i % colors.length] }"
                                    ></span>

                                    <span class="truncate max-w-[140px]">
                                        {{ item.label }}
                                    </span>

                                    <span class="ml-auto font-medium">
                                        {{ item.amount.toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BY CUSTOMER -->
                <div
                    v-if="hasCustomerDonut"
                    class="bg-white rounded-md border shadow-sm p-2 h-[280px] flex flex-col"
                >
                    <h3 class="text-[11px] font-medium text-gray-500 mb-6">
                        By Customer
                    </h3>

                    <div class="flex justify-center">
                        <div class="flex items-center gap-4">
                            <div class="w-[180px] h-[180px] relative flex-shrink-0">
                                <ArInvoicesPie :donut="donut.by_customer" />
                            </div>

                            <div class="flex flex-col gap-1 text-xs text-gray-600">
                                <div
                                    v-for="(item, i) in donut.by_customer"
                                    :key="i"
                                    class="flex items-center gap-2"
                                >
                                    <span
                                        class="inline-block w-3 h-3 rounded-full"
                                        :style="{ backgroundColor: colors[i % colors.length] }"
                                    ></span>

                                    <span class="truncate max-w-[140px]">
                                        {{ item.label }}
                                    </span>

                                    <span class="ml-auto font-medium">
                                        {{ item.amount.toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- APPROVED SUMMARY -->
            <div
                v-if="activeTab === 'approved' && approvedTotals"
                class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-white border rounded-lg shadow p-4"
            >
                <!-- TOTAL -->
                <div class="bg-indigo-50 rounded p-4">
                    <div class="text-xs text-gray-500">
                        Total Approved
                    </div>
                    <div class="text-2xl font-semibold text-indigo-700">
                        {{ formatCurrency(approvedTotals.total_amount) }}
                    </div>
                </div>

                <!-- RECEIVED -->
                <div class="bg-green-50 rounded p-4">
                    <div class="text-xs text-gray-500">
                        Received
                    </div>
                    <div class="text-2xl font-semibold text-green-700">
                        {{ formatCurrency(approvedTotals.received_amount) }}
                    </div>
                </div>

                <!-- OUTSTANDING -->
                <div class="bg-red-50 rounded p-4">
                    <div class="text-xs text-gray-500">
                        Outstanding
                    </div>
                    <div class="text-2xl font-semibold text-red-700">
                        {{ formatCurrency(approvedTotals.outstanding) }}
                    </div>
                </div>
            </div>


            <!-- TABLE -->
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-red-400"></span>
                    Overdue
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                    Due soon (5 days)
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-300"></span>
                    On track
                </span>
            </div>

            <ArInvoicesTable
                :invoices="currentInvoices"
                :status="activeTab"
            />

            <!-- PAGINATION -->
            <div
                v-if="currentInvoices?.links?.length"
                class="flex gap-1"
            >
                <Link
                    v-for="link in currentInvoices.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- CREATE MODAL -->
    <CreateArInvoiceModal
        :show="showCreate"
        :projects="page.props.projects"
        :customers="page.props.customers"
        :default-customer-id="prefill.customer_id"
        @close="showCreate = false"
    />
</template>
