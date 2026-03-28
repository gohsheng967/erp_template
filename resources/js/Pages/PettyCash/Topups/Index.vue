<script setup>
import { ref, computed, watch, onMounted, inject } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'

import TopupTable from './Partials/TopupsTable.vue'
import CreateTopupModal from './Partials/CreateTopupModal.vue'
import PayTopupModal from './Partials/PayTopupModal.vue'
import TopupApprovalModal from './Partials/TopupApprovalModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const page = usePage()
const toast = inject('toast', null)

const topups = computed(() => page.props.topups)
const tabCounts = computed(() => page.props.tabCounts ?? {})
const filters = page.props.filters ?? {}
const projects = computed(() => page.props.projects ?? [])
const requesters = computed(() => page.props.requesters ?? [])
const canBrowseAllTopups = computed(() => Boolean(page.props.canBrowseAllTopups))

const showCreate = ref(false)
const showPay = ref(false)
const selectedTopup = ref(null)

const showApproveConfirm = ref(false)
const showDeleteConfirm = ref(false)
const approvingTopup = ref(null)
const deletingTopup = ref(null)

const search = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo = ref(filters.to ?? null)
const contextType = ref(filters.context_type ?? '')
const projectId = ref(filters.project_id ?? '')
const requesterId = ref(filters.requester_id ?? '')
const amountMin = ref(filters.amount_min ?? '')
const amountMax = ref(filters.amount_max ?? '')
const paymentState = ref(filters.payment_state ?? 'all')

const statusMeta = {
    my_in_progress: {
        key: 'my_in_progress',
        queue: 'my',
        label: 'In Progress',
        hint: 'Your top-up requests that are still in process.',
    },
    my_rejected: {
        key: 'my_rejected',
        queue: 'my',
        label: 'Returned / Rejected',
        hint: 'Your top-up requests returned or rejected.',
    },
    my_completed: {
        key: 'my_completed',
        queue: 'my',
        label: 'Completed',
        hint: 'Your top-up requests that are paid.',
    },
    checked: {
        key: 'checked',
        queue: 'review',
        label: 'Needs Check',
        hint: 'Top-up requests waiting for first review.',
    },
    verified: {
        key: 'verified',
        queue: 'review',
        label: 'Needs Verify',
        hint: 'Top-up requests verified by department stage.',
    },
    approval: {
        key: 'approval',
        queue: 'review',
        label: 'Needs CEO / GM Approval',
        hint: 'Top-up requests waiting payment processing after approval.',
    },
    payment: {
        key: 'payment',
        queue: 'payment',
        label: 'Top-Up Payment Status',
        hint: 'Approved and paid top-up payment records.',
    },
}

const queueMeta = [
    { key: 'my', label: 'My Requests', statuses: ['my_in_progress', 'my_rejected', 'my_completed'] },
    { key: 'review', label: 'Review Queue', statuses: ['checked', 'verified', 'approval'] },
    { key: 'payment', label: 'Payment', statuses: ['payment'] },
]

const browseStatusMeta = {
    all_non_draft: {
        key: 'all_non_draft',
        label: 'All Top-Up Requests',
        hint: 'Browse all top-up requests with filters.',
    },
    checked: {
        key: 'checked',
        label: 'Needs Check',
        hint: 'Requests waiting for first review.',
    },
    verified: {
        key: 'verified',
        label: 'Needs Verify',
        hint: 'Requests waiting verification stage.',
    },
    approval: {
        key: 'approval',
        label: 'Needs CEO / GM Approval',
        hint: 'Requests approved and waiting payment stage.',
    },
    payment: {
        key: 'payment',
        label: 'Payment Status',
        hint: 'Payment processing and paid records.',
    },
    rejected: {
        key: 'rejected',
        label: 'Rejected',
        hint: 'Rejected top-up requests.',
    },
}

const browseStatuses = ['all_non_draft', 'checked', 'verified', 'approval', 'payment', 'rejected']
const defaultTabPriority = ['my_in_progress', 'checked', 'verified', 'approval', 'payment', 'my_rejected', 'my_completed']

const allowedTabs = new Set(Object.keys(statusMeta))
const tabStorageKey = 'topups-index-active-tab'
const browseTabStorageKey = 'topups-index-browse-tab'
const serverTab = page.props.activeTab

const hasTabQuery = (() => {
    if (typeof window !== 'undefined') {
        return new URLSearchParams(window.location.search).has('tab')
    }

    const queryString = String(page.url ?? '').split('?')[1] ?? ''
    return new URLSearchParams(queryString).has('tab')
})()

function sectionCount(sectionKey) {
    const map = {
        checked: tabCounts.value.checked ?? 0,
        verified: tabCounts.value.verified ?? 0,
        approval: tabCounts.value.approval ?? 0,
        payment: tabCounts.value.payment ?? 0,
        my_in_progress: tabCounts.value.my_in_progress ?? 0,
        my_rejected: tabCounts.value.my_rejected ?? 0,
        my_completed: tabCounts.value.my_completed ?? 0,
        rejected: tabCounts.value.rejected ?? 0,
    }

    if (Object.prototype.hasOwnProperty.call(map, sectionKey)) {
        return map[sectionKey]
    }

    return topups.value?.[sectionKey]?.total ?? 0
}

function queueCount(queueKey) {
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return 0
    return queue.statuses.reduce((sum, status) => sum + sectionCount(status), 0)
}

function tabCount(tab) {
    return Number(tabCounts.value?.[tab] ?? 0)
}

function resolveSmartDefaultTab() {
    return defaultTabPriority.find((tab) => tabCount(tab) > 0) ?? 'my_in_progress'
}

function resolveInitialTab() {
    if (typeof window !== 'undefined') {
        const remembered = localStorage.getItem(tabStorageKey)
        if (!hasTabQuery && remembered && allowedTabs.has(remembered)) {
            return remembered
        }
    }

    if (serverTab && allowedTabs.has(serverTab) && (hasTabQuery || tabCount(serverTab) > 0)) {
        return serverTab
    }

    return resolveSmartDefaultTab()
}

function resolveInitialBrowseTab() {
    if (typeof window !== 'undefined') {
        const remembered = localStorage.getItem(browseTabStorageKey)
        if (remembered && browseStatusMeta[remembered]) {
            return remembered
        }
    }

    return 'all_non_draft'
}

const activeTab = ref(resolveInitialTab())
const activeQueue = ref(statusMeta[activeTab.value]?.queue ?? 'review')

watch(activeTab, (tab) => {
    if (!allowedTabs.has(tab) || typeof window === 'undefined') return
    activeQueue.value = statusMeta[tab]?.queue ?? 'review'
    localStorage.setItem(tabStorageKey, tab)
})

onMounted(() => {
    if (hasTabQuery) return
    if (activeTab.value === serverTab) return
    applyFilters()
})

const currentTopups = computed(() => {
    return topups.value?.[activeTab.value] ?? {
        data: [],
        links: [],
    }
})

const activeTabHint = computed(() => statusMeta[activeTab.value]?.hint ?? '')
const activeTabLabel = computed(() => statusMeta[activeTab.value]?.label ?? activeTab.value)
const activeQueueStatuses = computed(() => {
    const queue = queueMeta.find((item) => item.key === activeQueue.value)
    return (queue?.statuses ?? []).map((status) => statusMeta[status])
})

const browseTab = ref(resolveInitialBrowseTab())
const browseTopups = computed(() => {
    return topups.value?.[browseTab.value] ?? {
        data: [],
        links: [],
    }
})
const browseTabHint = computed(() => browseStatusMeta[browseTab.value]?.hint ?? '')
const browseTabLabel = computed(() => browseStatusMeta[browseTab.value]?.label ?? browseTab.value)
const isBrowsePaymentTab = computed(() => browseTab.value === 'payment')

watch(browseTab, (tab) => {
    if (!browseStatusMeta[tab] || typeof window === 'undefined') return
    localStorage.setItem(browseTabStorageKey, tab)
})

const activeFilterCount = computed(() => {
    let count = 0
    if (String(search.value).trim() !== '') count++
    if (dateFrom.value) count++
    if (dateTo.value) count++
    if (contextType.value) count++
    if (projectId.value !== '' && projectId.value !== null) count++
    if (requesterId.value !== '' && requesterId.value !== null) count++
    if (amountMin.value !== '' && amountMin.value !== null) count++
    if (amountMax.value !== '' && amountMax.value !== null) count++
    if (isBrowsePaymentTab.value && paymentState.value !== 'all') count++
    return count
})

function applyFilters() {
    router.get(
        route('petty-cash.topups.index'),
        {
            tab: activeTab.value,
            search: search.value,
            from: dateFrom.value,
            to: dateTo.value,
            context_type: contextType.value,
            project_id: projectId.value,
            requester_id: requesterId.value,
            amount_min: amountMin.value,
            amount_max: amountMax.value,
            payment_state: paymentState.value,
        },
        {
            preserveScroll: true,
            replace: true,
        }
    )
}

function resetFilters() {
    search.value = ''
    dateFrom.value = null
    dateTo.value = null
    contextType.value = ''
    projectId.value = ''
    requesterId.value = ''
    amountMin.value = ''
    amountMax.value = ''
    paymentState.value = 'all'

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

function switchQueue(queueKey) {
    activeQueue.value = queueKey
    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return

    const preferred = queue.statuses.find((status) => sectionCount(status) > 0) ?? queue.statuses[0]
    if (preferred) {
        switchTab(preferred)
    }
}

function switchBrowseTab(tab) {
    if (!browseStatusMeta[tab]) return
    browseTab.value = tab
}

function confirmApprove(topup) {
    approvingTopup.value = topup
    showApproveConfirm.value = true
}

function closeApprove() {
    showApproveConfirm.value = false
    approvingTopup.value = null
}

function onApproved() {
    showApproveConfirm.value = false
    approvingTopup.value = null
    router.reload({ only: ['topups', 'tabCounts'] })
}

function openPay(topup) {
    selectedTopup.value = topup
    showPay.value = true
}

function askDelete(topup) {
    deletingTopup.value = topup
    showDeleteConfirm.value = true
}

function doDelete() {
    if (!deletingTopup.value) return

    router.delete(
        route('petty-cash.topups.destroy', deletingTopup.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Top-up request deleted')

                showDeleteConfirm.value = false
                deletingTopup.value = null

                router.reload({ only: ['topups', 'tabCounts'] })
            },
        }
    )
}

function closeDelete() {
    showDeleteConfirm.value = false
    deletingTopup.value = null
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('petty-cash.index')"
                    class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900"
                >
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </Link>

                <h2 class="text-xl font-semibold text-gray-800">
                    Petty Cash Top-Ups
                </h2>
            </div>
        </template>

        <div class="p-6 space-y-6">
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                    Top-Up Flow
                </div>
                <div class="mt-1 text-sm text-emerald-900">
                    Request Top-Up -> Needs Check -> Needs Verify -> CEO / GM Approval -> Payment
                </div>
            </div>

            <div class="bg-white rounded-xl border p-3">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-slate-800">Queue</div>
                    <button
                        type="button"
                        @click="showCreate = true"
                        class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-emerald-700"
                    >
                        + Request Top-Up
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <button
                        v-for="queue in queueMeta"
                        :key="queue.key"
                        @click="switchQueue(queue.key)"
                        class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm transition"
                        :class="activeQueue === queue.key
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300'"
                    >
                        <span class="font-semibold">{{ queue.label }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-semibold"
                            :class="activeQueue === queue.key ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-700'"
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
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                        : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300'"
                >
                    <span>{{ status.label }}</span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                        {{ sectionCount(status.key) }}
                    </span>
                </button>

            </div>

            <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-sm text-emerald-900">
                <span class="font-semibold mr-2">{{ activeTabLabel }}:</span>
                <span>{{ activeTabHint }}</span>
            </div>

            <TopupTable
                :topups="currentTopups"
                :status="activeTab"
                @approve="confirmApprove"
                @pay="openPay"
                @delete="askDelete"
            />

            <div v-if="currentTopups.links?.length" class="flex gap-1">
                <Link
                    v-for="link in currentTopups.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>

            <template v-if="canBrowseAllTopups">
                <div class="relative py-2">
                    <div class="border-t-2 border-dashed border-slate-300"></div>
                    <div class="absolute inset-x-0 -top-1.5 flex justify-center">
                        <span class="rounded-full border border-slate-300 bg-white px-3 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                            All Top-Up Browse Section
                        </span>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border-2 border-slate-300 bg-slate-50/60 p-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">All Top-Up Browse</h3>
                        <p class="text-sm text-slate-600">Filter and browse all top-up requests.</p>
                    </div>

                    <StandardFilterBar
                        title="Browse Filters"
                        description="Use filters below for full top-up listing."
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
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="Top-Up No / Reason / Requester"
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">From</label>
                                <input
                                    type="date"
                                    v-model="dateFrom"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">To</label>
                                <input
                                    type="date"
                                    v-model="dateTo"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                />
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Context</label>
                                <select
                                    v-model="contextType"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="">All Context</option>
                                    <option value="office">Office</option>
                                    <option value="project">Project</option>
                                </select>
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</label>
                                <select
                                    v-model="projectId"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="">All Projects</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requester</label>
                                <select
                                    v-model="requesterId"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="">All Requesters</option>
                                    <option v-for="requester in requesters" :key="requester.id" :value="requester.id">
                                        {{ requester.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Amount Min</label>
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    v-model="amountMin"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
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
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                    placeholder="0.00"
                                />
                            </div>

                            <div v-if="isBrowsePaymentTab" class="flex flex-col xl:col-span-2">
                                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment State</label>
                                <select
                                    v-model="paymentState"
                                    class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-emerald-500 focus:ring-emerald-500"
                                >
                                    <option value="all">All</option>
                                    <option value="pending">Pending Paid</option>
                                    <option value="paid">Paid</option>
                                </select>
                            </div>

                            <div class="xl:col-span-12 flex flex-wrap items-center gap-2 pt-1">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quick Date</span>
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                                    @click="setDatePreset('7d')"
                                >
                                    Last 7 Days
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                                    @click="setDatePreset('30d')"
                                >
                                    Last 30 Days
                                </button>
                                <button
                                    type="button"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs font-medium text-slate-700 hover:border-emerald-300 hover:text-emerald-700"
                                    @click="setDatePreset('month')"
                                >
                                    This Month
                                </button>
                            </div>
                        </div>
                    </StandardFilterBar>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            v-for="statusKey in browseStatuses"
                            :key="statusKey"
                            type="button"
                            @click="switchBrowseTab(statusKey)"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                            :class="browseTab === statusKey
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300'"
                        >
                            <span>{{ browseStatusMeta[statusKey].label }}</span>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                                {{ sectionCount(statusKey) }}
                            </span>
                        </button>
                    </div>

                    <div class="bg-emerald-50 border border-emerald-100 rounded-lg px-4 py-3 text-sm text-emerald-900">
                        <span class="font-semibold mr-2">{{ browseTabLabel }}:</span>
                        <span>{{ browseTabHint }}</span>
                    </div>

                    <TopupTable
                        :topups="browseTopups"
                        :status="browseTab"
                        @approve="confirmApprove"
                        @pay="openPay"
                        @delete="askDelete"
                    />

                    <div v-if="browseTopups.links?.length" class="flex gap-1">
                        <Link
                            v-for="link in browseTopups.links"
                            :key="`${browseTab}-${link.label}`"
                            :href="link.url ?? ''"
                            v-html="link.label"
                            class="px-3 py-1 border rounded text-sm"
                        />
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>

    <CreateTopupModal
        :show="showCreate"
        :projects="projects"
        @close="showCreate = false"
    />

    <PayTopupModal
        :show="showPay"
        :topup="selectedTopup"
        @close="showPay = false"
    />

    <TopupApprovalModal
        :show="showApproveConfirm"
        :topup="approvingTopup"
        @approved="onApproved"
        @close="closeApprove"
    />

    <DeleteConfirmation
        v-if="showDeleteConfirm"
        title="Delete Top-Up"
        message="This action cannot be undone. Are you sure?"
        @confirm="doDelete"
        @close="closeDelete"
    />
</template>
