<script setup>
import { ref, computed } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

import PurchaseOrdersTable from './Partials/PurchaseOrdersTable.vue'
import POShowModal from './Partials/POShowModal.vue'

const page = usePage()

/* ========================
   Props from controller
======================== */
const purchaseOrders = computed(() => page.props.purchaseOrders)
const filters = page.props.filters ?? {}

/* ========================
   Filter state
======================== */
const search   = ref(filters.search ?? '')
const dateFrom = ref(filters.from ?? null)
const dateTo   = ref(filters.to ?? null)

/* ========================
   Modal state
======================== */
const showPOModal = ref(false)
const activePO = ref(null)

/* ========================
   Actions
======================== */
function applyFilters() {
    router.get(
        route('purchase-orders.index'),
        {
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

function resetFilters() {
    search.value = ''
    dateFrom.value = null
    dateTo.value = null
    applyFilters()
}

/* ========================
   Table Events
======================== */
function viewPO(po) {
    activePO.value = po
    showPOModal.value = true
}

function closePO() {
    showPOModal.value = false
    activePO.value = null
}

function updateDelivery(po) {
    // placeholder – next step
    console.log('Update delivery for', po.code)
}

function createInvoice(po) {
    router.visit(
        route('invoices.create', {
            purchase_order: po.uuid,
        })
    )
}

function goToDelivery(po) {
    router.visit(
        route('purchase-orders.deliveries.index', po.uuid)
    )
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- HEADER -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800">
                    Purchase Orders
                </h2>
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
                            placeholder="PO No / Supplier"
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

            <!-- TABLE -->
            <PurchaseOrdersTable
                :pos="purchaseOrders"
                @view="viewPO"
                @delivery="goToDelivery"
                @invoice="createInvoice"
            />

            <!-- PAGINATION -->
            <div
                v-if="purchaseOrders?.links?.length"
                class="flex gap-1"
            >
                <button
                    v-for="link in purchaseOrders.links"
                    :key="link.label"
                    :disabled="!link.url"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                    :class="{
                        'text-gray-400 cursor-not-allowed': !link.url,
                        'bg-gray-100': link.active
                    }"
                    @click="link.url && router.visit(link.url, { preserveScroll: true })"
                />
            </div>

        </div>
    </AuthenticatedLayout>

    <!-- PO DOCUMENT MODAL -->
    <POShowModal
        v-if="showPOModal"
        :po="activePO"
        @close="closePO"
    />
</template>
