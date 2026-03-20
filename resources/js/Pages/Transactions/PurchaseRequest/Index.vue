<script setup>
import { ref, computed, inject, watch, onMounted } from 'vue'
import axios from 'axios'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'

import PurchaseRequestsTable from '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestsTable.vue'
import CreatePurchaseRequestModal from '@/Pages/Transactions/PurchaseRequest/Partials/CreatePurchaseRequestModal.vue'
import PurchaseRequestShowModal from '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestShowModal.vue'
import POShowModal from '@/Pages/Transactions/PurchaseOrder/Partials/POShowModal.vue'
import CreateApInvoiceModal from '@/Pages/Transactions/PurchaseOrder/Partials/CreateApInvoiceModal.vue'

const activeRequest = ref(null)
const activePO = ref(null)
const activePayablePO = ref(null)
const showPayableModal = ref(false)
const showCreate = ref(false)
const toast = inject('toast', null)

const page = usePage()

const purchaseRequests = computed(() => page.props.purchaseRequests ?? {})
const myPurchaseRequests = computed(() => page.props.myPurchaseRequests ?? {})
const counts = computed(() => page.props.counts ?? {})
const myCounts = computed(() => page.props.myCounts ?? {})
const filters = page.props.filters ?? {}
const filterOptions = computed(() => page.props.filterOptions ?? {})

const search = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo = ref(filters.to ?? null)
const requesterId = ref(filters.requester_id ?? '')
const projectId = ref(filters.project_id ?? '')
const departmentId = ref(filters.department_id ?? '')
const projectLinked = ref(filters.project_linked ?? '')
const hasQuotation = ref(filters.has_quotation ?? '')
const amountMin = ref(filters.amount_min ?? '')
const amountMax = ref(filters.amount_max ?? '')

const statusMeta = {
    draft: {
        key: 'draft',
        queue: 'preparation',
        label: 'Draft',
        hint: 'Draft PR is still being prepared and can be freely edited.',
    },
    submitted: {
        key: 'submitted',
        queue: 'review',
        label: 'Pending Check',
        hint: 'Submitted PR is waiting for Pending Check.',
    },
    verified_own_department: {
        key: 'verified_own_department',
        queue: 'review',
        label: 'Pending Project/Purchasing Verify',
        hint: 'After Pending Check: project-linked PR goes to Pending Project Dept Verify; non-project PR goes directly to Pending Purchasing Dept Verify.',
    },
    verified_project_department: {
        key: 'verified_project_department',
        queue: 'review',
        label: 'Pending Purchasing Dept Verify',
        hint: 'Project-linked PR passed project verification and is now pending Purchasing Dept Verify.',
    },
    verified_purchasing_department: {
        key: 'verified_purchasing_department',
        queue: 'review',
        label: 'Pending CEO / GM Approve',
        hint: 'Purchasing verification completed. PR is pending CEO / GM approval.',
    },
    po: {
        key: 'po',
        queue: 'fulfillment',
        label: 'PO',
        hint: 'PO has been created.',
    },
    payment: {
        key: 'payment',
        queue: 'fulfillment',
        label: 'Payment',
        hint: 'Payment stage tracks payable/AP payment progress and links to Payment Slips.',
    },
    rejected: {
        key: 'rejected',
        queue: 'fulfillment',
        label: 'Rejected',
        hint: 'Rejected PR remains for audit trail and reference.',
    },
}

const queueMeta = [
    { key: 'preparation', label: 'Preparation', statuses: ['draft'] },
    { key: 'review', label: 'Review Queue', statuses: ['submitted', 'verified_own_department', 'verified_project_department', 'verified_purchasing_department'] },
    { key: 'fulfillment', label: 'PO & Payment', statuses: ['po', 'payment', 'rejected'] },
]

const allowedTabs = new Set(Object.keys(statusMeta))
const allTabStorageKey = 'purchase-request-index-active-tab'
const myTabStorageKey = 'purchase-request-index-my-tab'
const serverTab = page.props.activeTab
const hasTabQuery = (() => {
    if (typeof window !== 'undefined') {
        return new URLSearchParams(window.location.search).has('tab')
    }

    const queryString = String(page.url ?? '').split('?')[1] ?? ''
    return new URLSearchParams(queryString).has('tab')
})()

const activeTab = ref(resolveInitialAllTab())
const activeQueue = ref(statusMeta[activeTab.value]?.queue ?? 'preparation')

const myActiveTab = ref(resolveInitialMyTab())
const myActiveQueue = ref(statusMeta[myActiveTab.value]?.queue ?? 'preparation')

const currentPRs = computed(() => {
    return purchaseRequests.value?.[activeTab.value] ?? { data: [], links: [] }
})

const myCurrentPRs = computed(() => {
    return myPurchaseRequests.value?.[myActiveTab.value] ?? { data: [], links: [] }
})

const activeTabHint = computed(() => statusMeta[activeTab.value]?.hint ?? '')
const activeTabLabel = computed(() => statusMeta[activeTab.value]?.label ?? activeTab.value)
const activeQueueStatuses = computed(() => {
    const queue = queueMeta.find((item) => item.key === activeQueue.value)
    return (queue?.statuses ?? []).map((status) => statusMeta[status])
})

const myActiveTabHint = computed(() => statusMeta[myActiveTab.value]?.hint ?? '')
const myActiveTabLabel = computed(() => statusMeta[myActiveTab.value]?.label ?? myActiveTab.value)
const myActiveQueueStatuses = computed(() => {
    const queue = queueMeta.find((item) => item.key === myActiveQueue.value)
    return (queue?.statuses ?? []).map((status) => statusMeta[status])
})

const activeFilterCount = computed(() => {
    let count = 0
    if (String(search.value).trim() !== '') count++
    if (dateFrom.value) count++
    if (dateTo.value) count++
    if (requesterId.value !== '' && requesterId.value !== null) count++
    if (projectId.value !== '' && projectId.value !== null) count++
    if (departmentId.value !== '' && departmentId.value !== null) count++
    if (projectLinked.value !== '' && projectLinked.value !== null) count++
    if (hasQuotation.value !== '' && hasQuotation.value !== null) count++
    if (amountMin.value !== '' && amountMin.value !== null) count++
    if (amountMax.value !== '' && amountMax.value !== null) count++
    return count
})

watch(activeTab, (tab) => {
    if (!allowedTabs.has(tab) || typeof window === 'undefined') return
    activeQueue.value = statusMeta[tab]?.queue ?? 'preparation'
    localStorage.setItem(allTabStorageKey, tab)
})

watch(myActiveTab, (tab) => {
    if (!allowedTabs.has(tab) || typeof window === 'undefined') return
    myActiveQueue.value = statusMeta[tab]?.queue ?? 'preparation'
    localStorage.setItem(myTabStorageKey, tab)
})

onMounted(() => {
    if (hasTabQuery) return
    if (activeTab.value === serverTab) return
    applyFilters()
})

function resolveInitialAllTab() {
    if (typeof window !== 'undefined') {
        const remembered = localStorage.getItem(allTabStorageKey)
        if (!hasTabQuery && remembered && allowedTabs.has(remembered)) {
            return remembered
        }
    }

    if (serverTab && allowedTabs.has(serverTab)) {
        return serverTab
    }

    const reviewPriority = ['submitted', 'verified_own_department', 'verified_project_department', 'verified_purchasing_department']
    const nextReview = reviewPriority.find((status) => sectionCount(status) > 0)
    if (nextReview) return nextReview

    if (sectionCount('draft') > 0) return 'draft'

    return 'submitted'
}

function resolveInitialMyTab() {
    if (typeof window !== 'undefined') {
        const remembered = localStorage.getItem(myTabStorageKey)
        if (remembered && allowedTabs.has(remembered)) {
            return remembered
        }
    }

    const reviewPriority = ['submitted', 'verified_own_department', 'verified_project_department', 'verified_purchasing_department']
    const nextReview = reviewPriority.find((status) => mySectionCount(status) > 0)
    if (nextReview) return nextReview

    return 'draft'
}

function sectionCount(sectionKey) {
    return Number(counts.value?.[sectionKey] ?? purchaseRequests.value?.[sectionKey]?.total ?? 0)
}

function mySectionCount(sectionKey) {
    return Number(myCounts.value?.[sectionKey] ?? myPurchaseRequests.value?.[sectionKey]?.total ?? 0)
}

function queueCount(queueKey) {
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return 0
    return queue.statuses.reduce((sum, status) => sum + sectionCount(status), 0)
}

function myQueueCount(queueKey) {
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return 0
    return queue.statuses.reduce((sum, status) => sum + mySectionCount(status), 0)
}

function applyFilters() {
    const currentScrollY = window.scrollY

    router.get(
        route('purchase-request.index'),
        {
            search: search.value,
            from: dateFrom.value,
            to: dateTo.value,
            requester_id: requesterId.value,
            project_id: projectId.value,
            department_id: departmentId.value,
            project_linked: projectLinked.value,
            has_quotation: hasQuotation.value,
            amount_min: amountMin.value,
            amount_max: amountMax.value,
            tab: activeTab.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onSuccess: () => {
                requestAnimationFrame(() => {
                    window.scrollTo({ top: currentScrollY })
                })
            },
        }
    )
}

function refreshList() {
    router.reload({
        only: ['purchaseRequests', 'myPurchaseRequests', 'counts', 'myCounts'],
        preserveScroll: true,
    })
}

function resetFilters() {
    search.value = ''
    dateFrom.value = null
    dateTo.value = null
    requesterId.value = ''
    projectId.value = ''
    departmentId.value = ''
    projectLinked.value = ''
    hasQuotation.value = ''
    amountMin.value = ''
    amountMax.value = ''

    applyFilters()
}

function setDatePreset(preset) {
    const now = new Date()
    const toIso = (value) => value.toISOString().slice(0, 10)

    if (preset === '7d') {
        const from = new Date(now)
        from.setDate(from.getDate() - 6)
        dateFrom.value = toIso(from)
        dateTo.value = toIso(now)
        return
    }

    if (preset === '30d') {
        const from = new Date(now)
        from.setDate(from.getDate() - 29)
        dateFrom.value = toIso(from)
        dateTo.value = toIso(now)
        return
    }

    if (preset === 'month') {
        const from = new Date(now.getFullYear(), now.getMonth(), 1)
        dateFrom.value = toIso(from)
        dateTo.value = toIso(now)
    }
}

function switchTab(tab) {
    activeTab.value = tab
    applyFilters()
}

function switchMyTab(tab) {
    myActiveTab.value = tab
}

function switchQueue(queueKey) {
    activeQueue.value = queueKey
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return

    const preferred = queue.statuses.find((status) => sectionCount(status) > 0) ?? queue.statuses[0]
    if (preferred) {
        switchTab(preferred)
    }
}

function switchMyQueue(queueKey) {
    myActiveQueue.value = queueKey
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return

    const preferred = queue.statuses.find((status) => mySectionCount(status) > 0) ?? queue.statuses[0]
    if (preferred) {
        switchMyTab(preferred)
    }
}

function openViewForTab(pr, tab) {
    if ((tab === 'po' || tab === 'payment') && pr?.purchase_order?.uuid) {
        activePO.value = { uuid: pr.purchase_order.uuid }
        return
    }

    activeRequest.value = pr
}

function openPRView(pr) {
    activeRequest.value = pr
}

function closeView() {
    activeRequest.value = null
}

function closePOView() {
    activePO.value = null
}

function goToDelivery(pr) {
    if (!pr?.purchase_order?.uuid) return

    router.visit(route('purchase-orders.deliveries.index', pr.purchase_order.uuid))
}

async function openCreatePayable(pr) {
    if (!pr?.purchase_order?.uuid) return
    if (!pr?.purchase_order?.confirmed_at) {
        toast?.value?.show('Please confirm PO first before creating payable', 'error')
        return
    }
    if (pr?.purchase_order?.ap_invoice) {
        toast?.value?.show('This PO already has an AP invoice', 'error')
        return
    }

    try {
        const res = await axios.get(route('purchase-orders.show', pr.purchase_order.uuid))
        activePayablePO.value = res.data?.po ?? null
        showPayableModal.value = !!activePayablePO.value
        if (!showPayableModal.value) {
            toast?.value?.show('Unable to open payable form for this PO', 'error')
        }
    } catch (e) {
        toast?.value?.show('Unable to load PO for payable creation', 'error')
    }
}

function openPaymentSlipPage(pr) {
    const searchText =
        pr?.purchase_order?.ap_invoice?.invoice_no
        ?? pr?.purchase_order?.code
        ?? pr?.code
        ?? ''

    router.visit(route('payment-slips.index', {
        tab: 'processing',
        search: searchText,
    }))
}

function closePayableModal() {
    showPayableModal.value = false
    activePayablePO.value = null
    refreshList()
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    Purchase Requests
                </h2>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-indigo-900">My PR</h3>
                    <button
                        type="button"
                        @click="showCreate = true"
                        class="rounded-md bg-indigo-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow hover:bg-indigo-700"
                    >
                        + Create Purchase Request
                    </button>
                </div>

                <div class="bg-white rounded-xl border p-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button
                            v-for="queue in queueMeta"
                            :key="`my-${queue.key}`"
                            @click="switchMyQueue(queue.key)"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition"
                            :class="myActiveQueue === queue.key
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                        >
                            <span class="font-semibold">{{ queue.label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="myActiveQueue === queue.key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                            >
                                {{ myQueueCount(queue.key) }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="status in myActiveQueueStatuses"
                        :key="`my-status-${status.key}`"
                        @click="switchMyTab(status.key)"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                        :class="myActiveTab === status.key
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                    >
                        <span>{{ status.label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                            {{ mySectionCount(status.key) }}
                        </span>
                    </button>
                </div>

                <div class="rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <span class="font-semibold mr-2">{{ myActiveTabLabel }}:</span>
                    <span>{{ myActiveTabHint }}</span>
                </div>

                <PurchaseRequestsTable
                    :prs="myCurrentPRs"
                    :status="myActiveTab"
                    @view="openViewForTab($event, myActiveTab)"
                    @view-pr="openPRView"
                    @delivery="goToDelivery"
                    @payable="openCreatePayable"
                    @payment-slip="openPaymentSlipPage"
                />

                <div
                    v-if="myCurrentPRs?.links?.length"
                    class="flex gap-1"
                >
                    <Link
                        v-for="link in myCurrentPRs.links"
                        :key="`${myActiveTab}-${link.label}`"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="px-3 py-1 border rounded text-sm"
                    />
                </div>
            </div>

            <div class="relative py-2">
                <div class="border-t-2 border-dashed border-slate-300"></div>
                <div class="absolute inset-x-0 -top-1.5 flex justify-center">
                    <span class="rounded-full border border-slate-300 bg-white px-3 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                        Whole List Section
                    </span>
                </div>
            </div>

            <div class="space-y-4 rounded-xl border-2 border-slate-300 bg-slate-50/60 p-4">
                <div class="bg-white rounded-xl border p-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <button
                            v-for="queue in queueMeta"
                            :key="queue.key"
                            @click="switchQueue(queue.key)"
                            class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition"
                            :class="activeQueue === queue.key
                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                        >
                            <span class="font-semibold">{{ queue.label }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="activeQueue === queue.key ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700'"
                            >
                                {{ queueCount(queue.key) }}
                            </span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="status in activeQueueStatuses"
                        :key="status.key"
                        @click="switchTab(status.key)"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                        :class="activeTab === status.key
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                    >
                        <span>{{ status.label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                            {{ sectionCount(status.key) }}
                        </span>
                    </button>
                </div>

                <StandardFilterBar
                    title="Filters"
                    description="Use filters below to find purchase requests quickly."
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

                    <div class="w-full grid grid-cols-1 md:grid-cols-2 xl:grid-cols-12 gap-3">
                        <div class="flex flex-col xl:col-span-4">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                            <input
                                v-model="search"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="PR No / Purpose / Requester"
                                @keyup.enter="applyFilters"
                            />
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
                            <input
                                type="date"
                                v-model="dateFrom"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
                            <input
                                type="date"
                                v-model="dateTo"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requester</label>
                            <select
                                v-model="requesterId"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Requesters</option>
                                <option v-for="u in (filterOptions.requesters || [])" :key="u.id" :value="u.id">
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Department</label>
                            <select
                                v-model="departmentId"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Departments</option>
                                <option v-for="d in (filterOptions.departments || [])" :key="d.id" :value="d.id">
                                    {{ d.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</label>
                            <select
                                v-model="projectId"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Projects</option>
                                <option v-for="p in (filterOptions.projects || [])" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project Linked</label>
                            <select
                                v-model="projectLinked"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All</option>
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quotation</label>
                            <select
                                v-model="hasQuotation"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All</option>
                                <option value="yes">Has Quotation</option>
                                <option value="no">No Quotation</option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Min</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                v-model="amountMin"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0.00"
                            />
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Max</label>
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                v-model="amountMax"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="0.00"
                            />
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

                <div class="rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <span class="font-semibold mr-2">{{ activeTabLabel }}:</span>
                    <span>{{ activeTabHint }}</span>
                </div>

                <PurchaseRequestsTable
                    :prs="currentPRs"
                    :status="activeTab"
                    @view="openViewForTab($event, activeTab)"
                    @view-pr="openPRView"
                    @delivery="goToDelivery"
                    @payable="openCreatePayable"
                    @payment-slip="openPaymentSlipPage"
                />

                <div
                    v-if="currentPRs?.links?.length"
                    class="flex gap-1"
                >
                    <Link
                        v-for="link in currentPRs.links"
                        :key="`${activeTab}-${link.label}`"
                        :href="link.url ?? ''"
                        v-html="link.label"
                        class="px-3 py-1 border rounded text-sm"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <CreatePurchaseRequestModal
        :show="showCreate"
        @close="showCreate = false"
        @created="refreshList"
    />

    <PurchaseRequestShowModal
        v-if="activeRequest"
        :request="activeRequest"
        @close="closeView"
        @refresh="refreshList"
    />

    <POShowModal
        v-if="activePO"
        :po="activePO"
        @close="closePOView"
    />

    <CreateApInvoiceModal
        v-if="showPayableModal && activePayablePO"
        :po="activePayablePO"
        @close="closePayableModal"
    />
</template>
