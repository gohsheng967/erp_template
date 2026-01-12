<script setup>
import { computed, ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'

import CancelInvoiceModal from './Partials/CancelInvoiceModal.vue'

const page = usePage()
const { formatCurrency, formatDate } = useFormat()

/* =========================
   PROPS FROM CONTROLLER
========================= */
const invoices = computed(() => page.props.invoices)
const activeTab = computed(() => page.props.tab ?? 'outstanding')
const subtotal = computed(() => page.props.subtotal ?? {
    invoice: 0,
    balance: 0,
})

/* =========================
   TABS
========================= */
const tabs = [
    { key: 'outstanding', label: 'Outstanding' },
    { key: 'paid',        label: 'Paid' },
    { key: 'all',         label: 'All' },
]

function changeTab(tab) {
    router.get(
        route('ap-invoices.index'),
        { tab },
        { preserveScroll: true, replace: true }
    )
}

function goToInvoice(invoice) {
    router.visit(route('ap-invoices.show', invoice.uuid))
}

/* =========================
   CANCEL INVOICE MODAL
========================= */
const showCancelModal = ref(false)
const selectedInvoice = ref(null)

function openCancel(invoice) {
    selectedInvoice.value = invoice
    showCancelModal.value = true
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    AP Invoices
                </h2>
                <p class="text-sm text-gray-500">
                    Supplier invoices & outstanding payments
                </p>
            </div>
        </template>

        <div class="p-6 space-y-4">

            <!-- ================= TABS ================= -->
            <div class="flex gap-2 border-b">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="changeTab(tab.key)"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition"
                    :class="activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- ================= TABLE ================= -->
            <div class="bg-white border rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">

                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left">Invoice No</th>
                            <th class="px-4 py-3 text-left">Supplier</th>
                            <th class="px-4 py-3 text-left">PO No</th>
                            <th class="px-4 py-3 text-right">Invoice Amount</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3 text-center">Due Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="invoice in invoices.data"
                            :key="invoice.uuid"
                            class="border-t hover:bg-gray-50"
                        >
                            <td class="px-4 py-2 font-medium text-indigo-600">
                                {{ invoice.invoice_number }}
                            </td>

                            <td class="px-4 py-2">
                                {{ invoice.supplier?.company_name ?? '-' }}
                            </td>

                            <td class="px-4 py-2">
                                {{ invoice.purchase_order?.code ?? '-' }}
                            </td>

                            <td class="px-4 py-2 text-right">
                                {{ formatCurrency(invoice.invoice_amount) }}
                            </td>

                            <td class="px-4 py-2 text-right font-semibold">
                                {{ formatCurrency(invoice.balance_amount) }}
                            </td>

                            <td class="px-4 py-2 text-center">
                                {{ formatDate(invoice.due_date) }}
                            </td>

                            <td class="px-4 py-2 text-center">
                                <span
                                    class="px-2 py-0.5 text-xs rounded-full capitalize"
                                    :class="{
                                        'bg-amber-100 text-amber-700': invoice.status === 'confirmed',
                                        'bg-blue-100 text-blue-700': invoice.status === 'partially_paid',
                                        'bg-green-100 text-green-700': invoice.status === 'paid',
                                        'bg-red-100 text-red-700': invoice.status === 'cancelled',
                                    }"
                                >
                                    {{ invoice.status.replace('_', ' ') }}
                                </span>
                            </td>

                            <!-- ACTIONS -->
                            <td class="px-4 py-2 text-center">
                                <div class="flex justify-center items-center gap-3">

                                    <!-- VIEW -->
                                    <button
                                        class="text-indigo-600 hover:text-indigo-800"
                                        title="View invoice"
                                        @click="goToInvoice(invoice)"
                                    >
                                        <i class="mdi mdi-eye-outline text-lg"></i>
                                    </button>

                                    <!-- CANCEL -->
                                    <button
                                        v-if="invoice.status !== 'paid' && invoice.status !== 'cancelled'"
                                        class="text-red-600 hover:text-red-800"
                                        title="Cancel invoice"
                                        @click="openCancel(invoice)"
                                    >
                                        <i class="mdi mdi-close-circle-outline text-lg"></i>
                                    </button>

                                </div>
                            </td>
                        </tr>

                        <tr v-if="!invoices.data.length">
                            <td colspan="8" class="py-6 text-center text-gray-400">
                                No invoices found
                            </td>
                        </tr>
                    </tbody>

                    <!-- ================= SUBTOTAL ================= -->
                    <tfoot class="bg-gray-100 border-t font-semibold">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-gray-600">
                                Subtotal
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ formatCurrency(subtotal.invoice) }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                {{ formatCurrency(subtotal.balance) }}
                            </td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>

                </table>
            </div>

            <!-- ================= PAGINATION ================= -->
            <div
                v-if="invoices.links?.length"
                class="flex gap-1 flex-wrap"
            >
                <button
                    v-for="link in invoices.links"
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

    <!-- ================= CANCEL INVOICE MODAL ================= -->
    <CancelInvoiceModal
        :show="showCancelModal"
        :invoice="selectedInvoice"
        @close="showCancelModal = false"
        @success="showCancelModal = false"
    />
</template>
