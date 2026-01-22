<script setup>
import { ref, computed, inject } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import TopupTable from './Partials/TopupsTable.vue'
import CreateTopupModal from './Partials/CreateTopupModal.vue'
import PayTopupModal from './Partials/PayTopupModal.vue'
import TopupApprovalModal from './Partials/TopupApprovalModal.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

/* =========================
   PAGE / TOAST
========================= */
const page = usePage()
const toast = inject('toast', null)

/* =========================
   PROPS
========================= */
const topups    = computed(() => page.props.topups)
const tabCounts = computed(() => page.props.tabCounts ?? {})

const filters  = page.props.filters ?? {}
const wallets  = page.props.wallets ?? []
const projects = page.props.projects ?? []

/* =========================
   UI STATE
========================= */
const showCreate = ref(false)
const showPay = ref(false)

const selectedTopup = ref(null)

/* ===== CONFIRMATION MODALS ===== */
const showApproveConfirm = ref(false)
const showDeleteConfirm = ref(false)

const approvingTopup = ref(null)
const deletingTopup = ref(null)

/* =========================
   TABS
========================= */
const tabs = [
    { key: 'requested', label: 'Requested', badge: true },
    { key: 'approved',  label: 'Approved',  badge: true },
    { key: 'rejected',  label: 'Rejected',  badge: true },
    { key: 'paid',      label: 'Paid',       badge: false },
]

const activeTab = ref(page.props.activeTab ?? 'requested')

/* =========================
   FILTERS
========================= */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)

/* =========================
   CURRENT TAB DATA
========================= */
const currentTopups = computed(() => {
    return topups.value?.[activeTab.value] ?? {
        data: [],
        links: [],
    }
})

/* =========================
   FILTER ACTIONS
========================= */
function applyFilters() {
    router.get(
        route('petty-cash.topups.index'),
        {
            tab: activeTab.value,
            search: search.value,
            from: dateFrom.value,
            to: dateTo.value,
        },
        {
            preserveScroll: true,
            replace: true,
        }
    )
}

function switchTab(tab) {
    if (activeTab.value === tab) return
    activeTab.value = tab
    applyFilters()
}

/* =========================
   APPROVAL FLOW
========================= */
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

/* =========================
   PAY FLOW
========================= */
function openPay(topup) {
    selectedTopup.value = topup
    showPay.value = true
}

/* =========================
   DELETE FLOW
========================= */
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

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center">
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

                <button
                    @click="showCreate = true"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 shadow"
                >
                    + Request Top-Up
                </button>
            </div>
        </template>

        <div class="p-6 space-y-6">

            <!-- ================= FILTERS ================= -->
            <div class="bg-white p-4 rounded-lg shadow border">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex flex-col w-full md:w-1/3">
                        <label class="text-sm font-medium">Search</label>
                        <input
                            v-model="search"
                            class="border rounded px-3 py-2"
                            placeholder="Top-Up No / Wallet / User"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">From</label>
                        <input type="date" v-model="dateFrom" class="border rounded px-3 py-2" />
                    </div>

                    <div class="flex flex-col w-40">
                        <label class="text-sm font-medium">To</label>
                        <input type="date" v-model="dateTo" class="border rounded px-3 py-2" />
                    </div>

                    <button
                        class="px-4 py-2 h-10 bg-gray-200 rounded"
                        @click="applyFilters"
                    >
                        Apply
                    </button>
                </div>
            </div>

            <!-- ================= TABS ================= -->
            <div class="bg-white rounded shadow border px-4">
                <nav class="flex gap-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="switchTab(tab.key)"
                        class="py-3 font-medium flex items-center gap-2"
                        :class="{
                            'border-b-2 border-green-600 text-green-600': activeTab === tab.key,
                            'text-gray-500': activeTab !== tab.key
                        }"
                    >
                        {{ tab.label }}

                        <!-- BADGE -->
                        <span
                            v-if="tab.badge && tabCounts[tab.key] > 0"
                            class="px-2 py-0.5 text-xs rounded-full"
                            :class="activeTab === tab.key
                                ? 'bg-green-600 text-white'
                                : 'bg-gray-200 text-gray-700'"
                        >
                            {{ tabCounts[tab.key] }}
                        </span>
                    </button>
                </nav>
            </div>

            <!-- ================= TABLE ================= -->
            <TopupTable
                :topups="currentTopups"
                :status="activeTab"
                @approve="confirmApprove"
                @pay="openPay"
                @delete="askDelete"
            />

            <!-- ================= PAGINATION ================= -->
            <div v-if="currentTopups.links?.length" class="flex gap-1">
                <Link
                    v-for="link in currentTopups.links"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- ================= MODALS ================= -->

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
