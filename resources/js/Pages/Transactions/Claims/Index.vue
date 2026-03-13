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

/* ========================
   Tabs
======================== */
const tabs = [
    { key: 'draft',     label: 'Draft' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'checked',   label: 'Checked' },
    { key: 'verified',  label: 'Verified' },
    { key: 'approved',  label: 'Approved' },
    { key: 'payment',   label: 'Payment' },
    { key: 'rejected',  label: 'Rejected' },
]

const tabStorageKey = 'claims-index-active-tab'
const allowedTabs = new Set(tabs.map((tab) => tab.key))
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

    return 'submitted'
}

const activeTab = ref(resolveInitialTab())

watch(activeTab, (tab) => {
    if (!allowedTabs.has(tab) || typeof window === 'undefined') return
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


/* ========================
   Badge tabs
======================== */
const badgeTabs = ['submitted', 'checked', 'verified', 'approved', 'payment']

const tabHints = {
    draft: 'Draft claims are editable by requester and not yet submitted for review.',
    submitted: 'Submitted claims are waiting for checker review.',
    checked: 'Checked claims passed initial review and are waiting for verification.',
    verified: 'Verified claims are ready for final approval decision.',
    approved: 'Approved claims are waiting for CEO approval before entering payment stage.',
    payment: 'Payment tab shows only CEO-approved and paid claim payment status.',
    rejected: 'Rejected claims were not accepted in review flow and remain for audit trail.',
}

const activeTabHint = computed(() =>
    tabHints[activeTab.value] ?? ''
)

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
                    Claims Management
                </h2>

                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create Claim
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <StandardFilterBar
                title="Filters"
                description="Narrow claims by requester, project, value, and date."
                @apply="applyFilters"
                @reset="resetFilters"
            >

                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="text-sm font-medium">Search</label>
                        <input
                            v-model="search"
                            class="border rounded px-3 py-2"
                            placeholder="Claim No / Title / User Name"
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

                    <div class="flex flex-col w-full md:w-56">
                        <label class="text-sm font-medium">Project</label>
                        <select
                            v-model="projectId"
                            class="border rounded px-3 py-2"
                        >
                            <option value="">All Projects</option>
                            <option
                                v-for="project in page.props.projects"
                                :key="project.id"
                                :value="project.id"
                            >
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col w-full md:w-56">
                        <label class="text-sm font-medium">Requester</label>
                        <select
                            v-model="issuerId"
                            class="border rounded px-3 py-2"
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

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">Amount Min</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            v-model="amountMin"
                            class="border rounded px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">Amount Max</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            v-model="amountMax"
                            class="border rounded px-3 py-2"
                            placeholder="0.00"
                        />
                    </div>

                    <div
                        v-if="activeTab === 'payment'"
                        class="flex flex-col w-44"
                    >
                        <label class="text-sm font-medium">Payment State</label>
                        <select
                            v-model="paymentState"
                            class="border rounded px-3 py-2"
                        >
                            <option value="all">All</option>
                            <option value="pending">Pending Paid</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
            </StandardFilterBar>

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

            <!-- TABLE -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-3 text-sm text-blue-900">
                <span class="font-semibold mr-2">{{ tabs.find((t) => t.key === activeTab)?.label }}:</span>
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

        </div>
    </AuthenticatedLayout>

    <!-- CREATE MODAL -->
    <CreateClaimModal
        :show="showCreate"
        :projects="page.props.projects"
        @close="showCreate = false"
    />
</template>
