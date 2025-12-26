<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import ClaimsTable from '@/Pages/Transactions/Claims/Partials/ClaimsTable.vue'
import ClaimsPie from '@/Components/Charts/ClaimsPie.vue'
import CreateClaimModal from '@/Pages/Transactions/Claims/Partials/CreateClaimModal.vue'

const page = usePage()

/* ========================
   Props from controller
======================== */
const claims = computed(() => page.props.claims)
const counts = computed(() => page.props.counts)
const filters = page.props.filters
const donut   = page.props.donut

const showCreate = ref(false)

/* ========================
   Filter state
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)

/* ========================
   Tabs
======================== */
const tabs = [
    { key: 'draft',     label: 'Draft' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'approved',  label: 'Approved' },
    { key: 'rejected',  label: 'Rejected' },
    { key: 'paid',      label: 'Paid' },
]

const activeTab = ref(page.props.activeTab ?? 'submitted')

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
   Donut visibility
======================== */
const hasProjectDonut = computed(() =>
    donut?.by_project?.some(i => Number(i.amount) > 0)
)

const hasCategoryDonut = computed(() =>
    donut?.by_category?.some(i => Number(i.amount) > 0)
)

/* ========================
   Badge tabs
======================== */
const badgeTabs = ['submitted', 'approved']

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
            tab: activeTab.value,
        },
        {
            preserveScroll: true,
            replace: true,
        }
    )
}


function resetFilters() {
    const today = new Date().toISOString().slice(0, 10) // YYYY-MM-DD

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
            <div class="bg-white p-4 rounded-lg shadow border">
                <div class="flex flex-wrap gap-4 items-end">

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
                v-if="hasProjectDonut || hasCategoryDonut"
                class="grid grid-cols-1 md:grid-cols-2 gap-3"
            >
                <div
                    v-if="hasProjectDonut"
                    class="bg-white rounded-md border shadow-sm p-2 h-[120px] flex flex-col"
                >
                    <h3 class="text-[11px] font-medium text-gray-500 mb-1">
                        By Project
                    </h3>

                    <div class="flex-1 flex items-center justify-center">
                        <ClaimsPie :donut="donut.by_project" />
                    </div>
                </div>

                <div
                    v-if="hasCategoryDonut"
                    class="bg-white rounded-md border shadow-sm p-2 h-[120px] flex flex-col"
                >
                    <h3 class="text-[11px] font-medium text-gray-500 mb-1">
                        By Category
                    </h3>

                    <div class="flex-1 flex items-center justify-center">
                        <ClaimsPie :donut="donut.by_category" />
                    </div>
                </div>
            </div>

            <!-- TABLE -->
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
