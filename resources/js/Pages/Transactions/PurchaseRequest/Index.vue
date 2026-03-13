<script setup>
import { ref, computed, inject } from 'vue'
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
const toast = inject('toast', null)

function openView(pr) {
    if ((activeTab.value === 'po' || activeTab.value === 'payment') && pr?.purchase_order?.uuid) {
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
    const search =
        pr?.purchase_order?.ap_invoice?.invoice_no
        ?? pr?.purchase_order?.code
        ?? pr?.code
        ?? ''

    router.visit(route('payment-slips.index', {
        tab: 'processing',
        search,
    }))
}

function closePayableModal() {
    showPayableModal.value = false
    activePayablePO.value = null
    refreshList()
}

const page = usePage()

/* ========================
   PROPS FROM CONTROLLER
======================== */
const purchaseRequests = computed(() => page.props.purchaseRequests)
const counts = computed(() => page.props.counts)
const filters = page.props.filters ?? {}
const filterOptions = computed(() => page.props.filterOptions ?? {})

const showCreate = ref(false)

/* ========================
   FILTER STATE
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)
const requesterId = ref(filters.requester_id ?? '')
const projectId = ref(filters.project_id ?? '')
const departmentId = ref(filters.department_id ?? '')
const projectLinked = ref(filters.project_linked ?? '')
const hasQuotation = ref(filters.has_quotation ?? '')
const amountMin = ref(filters.amount_min ?? '')
const amountMax = ref(filters.amount_max ?? '')

/* ========================
   TABS
======================== */
const tabs = [
    { key: 'draft',     label: 'Draft' },
    { key: 'submitted', label: 'Submitted' },
    { key: 'verified_own_department',  label: 'Dept Verified' },
    { key: 'verified_project_department',  label: 'Project Verified' },
    { key: 'verified_purchasing_department',  label: 'Purchasing Verified' },
    { key: 'po',  label: 'PO Issued' },
    { key: 'payment',  label: 'Payment' },
    { key: 'rejected',  label: 'Rejected' },
]

const badgeTabs = [
    'submitted',
    'verified_own_department',
    'verified_project_department',
    'verified_purchasing_department',
    'po',
    'payment',
]

const tabHints = {
    draft: 'Draft PR is still being prepared and can be freely edited.',
    submitted: 'Submitted PR is waiting for first verification by requester department.',
    verified_own_department: 'Own department verified. PR can still be edited before re-submission.',
    verified_project_department: 'Project department verified this project-linked PR.',
    verified_purchasing_department: 'Purchasing verification completed. Waiting for CEO approval.',
    po: 'PO has been issued after CEO approval.',
    payment: 'Payment stage tracks payable/AP payment progress and links to Payment Slips.',
    rejected: 'Rejected PR remains for audit trail and reference.',
}

const activeTabHint = computed(() =>
    tabHints[activeTab.value] ?? ''
)

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
    search.value = ''
    dateFrom.value = ''
    dateTo.value = ''
    requesterId.value = ''
    projectId.value = ''
    departmentId.value = ''
    projectLinked.value = ''
    hasQuotation.value = ''
    amountMin.value = ''
    amountMax.value = ''

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

                    <div class="flex flex-col w-56">
                        <label class="text-sm font-medium">Requester</label>
                        <select v-model="requesterId" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option v-for="u in (filterOptions.requesters || [])" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col w-56">
                        <label class="text-sm font-medium">Department</label>
                        <select v-model="departmentId" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option v-for="d in (filterOptions.departments || [])" :key="d.id" :value="d.id">
                                {{ d.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col w-56">
                        <label class="text-sm font-medium">Project</label>
                        <select v-model="projectId" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option v-for="p in (filterOptions.projects || [])" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex flex-col w-44">
                        <label class="text-sm font-medium">Project Linked</label>
                        <select v-model="projectLinked" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option value="yes">Yes</option>
                            <option value="no">No</option>
                        </select>
                    </div>

                    <div class="flex flex-col w-44">
                        <label class="text-sm font-medium">Quotation</label>
                        <select v-model="hasQuotation" class="border rounded px-3 py-2">
                            <option value="">All</option>
                            <option value="yes">Has Quotation</option>
                            <option value="no">No Quotation</option>
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

            <div class="rounded border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <span class="font-semibold mr-2">{{ tabs.find((t) => t.key === activeTab)?.label }}:</span>
                <span>{{ activeTabHint }}</span>
            </div>

            <!-- TABLE -->
            <PurchaseRequestsTable
                :prs="currentPRs"
                :status="activeTab"
                @view="openView"
                @view-pr="openPRView"
                @delivery="goToDelivery"
                @payable="openCreatePayable"
                @payment-slip="openPaymentSlipPage"
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
