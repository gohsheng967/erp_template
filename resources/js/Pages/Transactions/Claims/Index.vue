<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'

import ClaimsTable from '@/Pages/Transactions/Claims/Partials/ClaimsTable.vue'
import CreateClaimModal from '@/Pages/Transactions/Claims/Partials/CreateClaimModal.vue'

const page = usePage()

/* ========================
   Props from controller
======================== */
const claims = computed(() => page.props.claims)
const counts = computed(() => page.props.counts)
const issuers = computed(() => page.props.issuers ?? [])
const filters = page.props.filters
const canBrowseAllClaims = computed(() => Boolean(page.props.canBrowseAllClaims))

const showCreate = ref(false)

/* ========================
   Filter state
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)
const projectId = ref(filters.project_id ?? '')
const issuerId = ref(filters.issuer_id ?? '')
const amountMin = ref(filters.amount_min ?? '')
const amountMax = ref(filters.amount_max ?? '')
const paymentState = ref(filters.payment_state ?? 'all')
const projects = computed(() => page.props.projects ?? [])

/* ========================
   Queue + Status Chip UX
======================== */
const statusMeta = {
    my_in_progress: {
        key: 'my_in_progress',
        queue: 'my',
        label: 'In Progress',
        hint: 'Your claims that are still being processed.',
    },
    my_rejected: {
        key: 'my_rejected',
        queue: 'my',
        label: 'Returned / Rejected',
        hint: 'Your claims that were returned or rejected.',
    },
    my_completed: {
        key: 'my_completed',
        queue: 'my',
        label: 'Completed',
        hint: 'Your claims that are fully paid.',
    },
    draft: {
        key: 'draft',
        queue: 'my',
        label: 'Own Claim (Draft)',
        hint: 'Draft claims are editable by requester and not yet submitted for review.',
    },
    rejected: {
        key: 'rejected',
        queue: 'my',
        label: 'Rejected (All)',
        hint: 'Rejected claims were not accepted in review flow and remain for audit trail.',
    },
    submitted: {
        key: 'submitted',
        queue: 'review',
        label: 'Needs Check',
        hint: 'Check by admin.',
    },
    checked: {
        key: 'checked',
        queue: 'review',
        label: 'Needs Verify',
        hint: 'Verify by own department head or project department head.',
    },
    verified: {
        key: 'verified',
        queue: 'review',
        label: 'Needs CEO / GM Approval',
        hint: 'Verified claims are waiting for CEO / GM approval.',
    },
    approved: {
        key: 'approved',
        queue: 'review',
        label: 'Needs CEO / GM Approval (Legacy)',
        hint: 'Legacy stage kept for older records.',
    },
    payment: {
        key: 'payment',
        queue: 'payment',
        label: "Claim's Payment Status",
        hint: 'Payment section shows only CEO-approved and paid claim payment status.',
    },
}

const queueMeta = [
    { key: 'my', label: 'My Claims', statuses: ['my_in_progress', 'my_rejected', 'my_completed'] },
    { key: 'review', label: 'Review Queue', statuses: ['submitted', 'checked', 'verified'] },
    { key: 'payment', label: 'Payment', statuses: ['payment'] },
]

const allowedTabs = new Set(Object.keys(statusMeta))
const tabStorageKey = 'claims-index-active-tab'
const serverTab = page.props.activeTab
const hasTabQuery = (() => {
    if (typeof window !== 'undefined') {
        return new URLSearchParams(window.location.search).has('tab')
    }

    const queryString = String(page.url ?? '').split('?')[1] ?? ''
    return new URLSearchParams(queryString).has('tab')
})()

function resolveInitialTab() {
    const propTab = serverTab

    if (typeof window !== 'undefined') {
        const remembered = localStorage.getItem(tabStorageKey)
        if (!hasTabQuery && remembered && allowedTabs.has(remembered)) {
            return remembered
        }
    }

    if (propTab && allowedTabs.has(propTab)) {
        return propTab
    }

    const reviewPriority = ['submitted', 'checked', 'verified']
    const nextReview = reviewPriority.find((status) => sectionCount(status) > 0)
    if (nextReview) return nextReview

    if (sectionCount('payment') > 0) return 'payment'
    if (sectionCount('draft') > 0) return 'draft'

    return 'submitted'
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

/* ========================
   Current tab data
======================== */
const currentClaims = computed(() => {
    return claims.value?.[activeTab.value] ?? {
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

const browseStatusMeta = {
    all_non_draft: {
        key: 'all_non_draft',
        label: 'All Claims',
        hint: 'Full listing for all non-draft claims.',
    },
    submitted: {
        key: 'submitted',
        label: 'Needs Check',
        hint: 'Submitted claims waiting for admin check.',
    },
    checked: {
        key: 'checked',
        label: 'Needs Verify',
        hint: 'Checked claims waiting for department verification.',
    },
    verified: {
        key: 'verified',
        label: 'Needs CEO / GM Approval',
        hint: 'Verified claims waiting for CEO / GM approval.',
    },
    payment: {
        key: 'payment',
        label: 'Payment Status',
        hint: 'CEO-approved and paid claims.',
    },
    rejected: {
        key: 'rejected',
        label: 'Returned / Rejected',
        hint: 'Claims returned or rejected.',
    },
}

const browseStatuses = ['all_non_draft', 'submitted', 'checked', 'verified', 'payment', 'rejected']
const browseTabStorageKey = 'claims-index-browse-tab'
const browseTab = ref(resolveInitialBrowseTab())
const browseClaims = computed(() => {
    return claims.value?.[browseTab.value] ?? {
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
    if (projectId.value !== '' && projectId.value !== null) count++
    if (issuerId.value !== '' && issuerId.value !== null) count++
    if (amountMin.value !== '' && amountMin.value !== null) count++
    if (amountMax.value !== '' && amountMax.value !== null) count++
    if (isBrowsePaymentTab.value && paymentState.value !== 'all') count++
    return count
})

function sectionCount(sectionKey) {
    if (sectionKey === 'submitted') return counts.value?.submitted ?? 0
    if (sectionKey === 'checked') return counts.value?.checked ?? 0
    if (sectionKey === 'verified') return counts.value?.verified ?? 0
    if (sectionKey === 'approved') return counts.value?.approved ?? 0
    if (sectionKey === 'payment') return counts.value?.payment ?? 0
    if (sectionKey === 'my_in_progress') return claims.value?.my_in_progress?.total ?? 0
    if (sectionKey === 'my_rejected') return claims.value?.my_rejected?.total ?? 0
    if (sectionKey === 'my_completed') return claims.value?.my_completed?.total ?? 0
    return claims.value?.[sectionKey]?.total ?? 0
}

function queueCount(queueKey) {
    if (queueKey === 'my') {
        return sectionCount('my_in_progress')
            + sectionCount('my_rejected')
            + sectionCount('my_completed')
    }

    const queue = queueMeta.find((item) => item.key === queueKey)
    if (!queue) return 0
    return queue.statuses.reduce((sum, status) => sum + sectionCount(status), 0)
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

/* ========================
   Actions
======================== */
function applyFilters() {
    router.get(
        route('claims.index'),
        {
            search: search.value,
            from: dateFrom.value,
            to: dateTo.value,
            project_id: projectId.value,
            issuer_id: issuerId.value,
            amount_min: amountMin.value,
            amount_max: amountMax.value,
            payment_state: paymentState.value,
            tab: activeTab.value,
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
    projectId.value = ''
    issuerId.value = ''
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
</script>

<template>
    <AuthenticatedLayout>

        <!-- HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    Claims Management
                </h2>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- QUEUE SELECTOR -->
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

            <!-- STATUS CHIPS -->
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

                <button
                    v-if="activeQueue === 'my'"
                    type="button"
                    @click="showCreate = true"
                    class="ml-auto rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow hover:bg-indigo-700"
                >
                    + Create Claim
                </button>
            </div>

            <!-- TABLE -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
                <span class="font-semibold mr-2">
                    {{ activeTabLabel }}:
                </span>
                <span>{{ activeTabHint }}</span>
            </div>

            <ClaimsTable
                :claims="currentClaims"
                :status="activeTab"
            />

            <!-- PAGINATION -->
            <div
                v-if="currentClaims?.links?.length"
                class="flex gap-1"
            >
                <Link
                    v-for="link in currentClaims.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>

            <template v-if="canBrowseAllClaims">
                <div class="relative py-2">
                    <div class="border-t-2 border-dashed border-slate-300"></div>
                    <div class="absolute inset-x-0 -top-1.5 flex justify-center">
                        <span class="rounded-full border border-slate-300 bg-white px-3 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                            All Claims Browse Section
                        </span>
                    </div>
                </div>

                <div class="space-y-4 rounded-xl border-2 border-slate-300 bg-slate-50/60 p-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">All Claims Browse</h3>
                    <p class="text-sm text-slate-600">Filter and browse all non-draft claims.</p>
                </div>

                <StandardFilterBar
                    title="Browse Filters"
                    description="Use filters below for full claims listing."
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
                                placeholder="Claim No / Title / User Name"
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
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</label>
                            <select
                                v-model="projectId"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Projects</option>
                                <option
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                >
                                    {{ project.name }}
                                </option>
                            </select>
                        </div>

                        <div class="flex flex-col xl:col-span-2">
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requester</label>
                            <select
                                v-model="issuerId"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">All Requesters</option>
                                <option
                                    v-for="issuer in issuers"
                                    :key="issuer.id"
                                    :value="issuer.id"
                                >
                                    {{ issuer.name }}
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

                        <div
                            v-if="isBrowsePaymentTab"
                            class="flex flex-col xl:col-span-2"
                        >
                            <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Payment State</label>
                            <select
                                v-model="paymentState"
                                class="h-9 rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
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

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="statusKey in browseStatuses"
                        :key="statusKey"
                        type="button"
                        @click="switchBrowseTab(statusKey)"
                        class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition"
                        :class="browseTab === statusKey
                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                            : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'"
                    >
                        <span>{{ browseStatusMeta[statusKey].label }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">
                            {{ sectionCount(statusKey) }}
                        </span>
                    </button>
                </div>

                <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-3 text-sm text-indigo-900">
                    <span class="font-semibold mr-2">{{ browseTabLabel }}:</span>
                    <span>{{ browseTabHint }}</span>
                </div>

                <ClaimsTable
                    :claims="browseClaims"
                    :status="browseTab"
                />

                <div
                    v-if="browseClaims?.links?.length"
                    class="flex gap-1"
                >
                    <Link
                        v-for="link in browseClaims.links"
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

    <!-- CREATE MODAL -->
    <CreateClaimModal
        :show="showCreate"
        :projects="page.props.projects"
        @close="showCreate = false"
    />
</template>
