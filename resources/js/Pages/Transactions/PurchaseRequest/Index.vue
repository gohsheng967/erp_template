<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'

import PurchaseRequestsTable from '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestsTable.vue'
import CreatePurchaseRequestModal from '@/Pages/Transactions/PurchaseRequest/Partials/CreatePurchaseRequestModal.vue'
import PurchaseRequestActions from '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestActions.vue'
import PurchaseRequestShowModal from '@/Pages/Transactions/PurchaseRequest/Partials/PurchaseRequestShowModal.vue'

const activeRequest = ref(null)

function openView(pr) {
    activeRequest.value = pr
}

function closeView() {
  activeRequest.value = null
}

const page = usePage()

/* ========================
   PROPS FROM CONTROLLER
======================== */
const purchaseRequests = computed(() => page.props.purchaseRequests)
const counts = computed(() => page.props.counts)
const filters = page.props.filters ?? {}

const showCreate = ref(false)

/* ========================
   FILTER STATE
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)

/* ========================
   TABS
======================== */
const tabs = [
    { key: 'draft',     label: 'Draft' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'approved',  label: 'Approved' },
    { key: 'rejected',  label: 'Rejected' },
    { key: 'issued',  label: 'PO Issued' },

]

const badgeTabs = ['submitted', 'approved']

const activeTab = ref(page.props.activeTab ?? 'draft')

/* ========================
   CURRENT TAB DATA
======================== */
const currentPRs = computed(() => {
    return purchaseRequests.value?.[activeTab.value] ?? {
        data: [],
        links: [],
    }
})

/* ========================
   ACTIONS
======================== */
function applyFilters() {
    router.get(
        route('purchase-request.index'),
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

function refreshList() {
    router.reload({
        only: ['purchaseRequests', 'counts'],
        preserveScroll: true,
    })
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
                    Purchase Requests
                </h2>

                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 shadow"
                >
                    + Create Purchase Request
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- FILTERS -->
            <StandardFilterBar
                title="Filters"
                description="Filter purchase requests by keyword and date."
                @apply="applyFilters"
                @reset="resetFilters"
            >

                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="text-sm font-medium">Search</label>
                        <input
                            v-model="search"
                            class="border rounded px-3 py-2"
                            placeholder="PR No / Purpose / Requester"
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
            <PurchaseRequestsTable
                :prs="currentPRs"
                :status="activeTab"
                @view="openView"
            />

            <!-- PAGINATION -->
            <div
                v-if="currentPRs?.links?.length"
                class="flex gap-1"
            >
                <Link
                    v-for="link in currentPRs.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>

        </div>
    </AuthenticatedLayout>

    <!-- CREATE MODAL -->
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
</template>
