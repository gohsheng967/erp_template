<script setup>
import { computed, ref } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'

import CreatePaymentModal from './Partials/CreatePaymentModal.vue'
import CancelPaymentModal from './Partials/CancelPaymentModal.vue'

/* =========================
   PAGE DATA
========================= */
const page = usePage()
const { formatCurrency, formatDate } = useFormat()

const invoice = computed(() => page.props.invoice)
const payments = computed(() => invoice.value.payments ?? [])
const paidAmountDisplay = computed(() => {
    return payments.value.reduce((total, payment) => {
        if (payment?.cancelled_at) return total
        if (!payment?.reference) return total
        return total + Number(payment.amount ?? 0)
    }, 0)
})
const balanceAmountDisplay = computed(() => {
    const invoiceAmount = Number(invoice.value.invoice_amount ?? 0)
    return invoiceAmount - paidAmountDisplay.value
})

/* =========================
   UI STATE
========================= */
const showPaymentModal = ref(false)
const showEditPaymentModal = ref(false)
const showCancelModal = ref(false)

const selectedPayment = ref(null)

/* =========================
   STATUS BADGE
========================= */
const statusClass = computed(() => {
    switch (invoice.value.status) {
        case 'confirmed':
            return 'bg-amber-100 text-amber-700'
        case 'partially_paid':
            return 'bg-blue-100 text-blue-700'
        case 'paid':
            return 'bg-green-100 text-green-700'
        default:
            return 'bg-gray-100 text-gray-600'
    }
})

/* =========================
   ATTACHMENT
========================= */
const vendorInvoice = computed(() => {
    return invoice.value.attachments?.[0] ?? null
})

/* =========================
   ACTIONS
========================= */
function editPayment(payment) {
    selectedPayment.value = payment
    showEditPaymentModal.value = true
}

function openCancel(payment) {
    selectedPayment.value = payment
    showCancelModal.value = true
}
</script>

<template>
    <AuthenticatedLayout>

        <!-- ================= HEADER ================= -->
        <template #header>
            <div class="flex justify-between items-center">

                <!-- LEFT: BACK + TITLE -->
                <div class="flex items-center gap-3">

                    <!-- BACK BUTTON -->
                    <Link
                        :href="route('ap-invoices.index')"
                        class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-indigo-600"
                    >
                        <i class="mdi mdi-arrow-left text-lg"></i>
                        Back
                    </Link>

                    <div class="border-l h-6 mx-2"></div>

                    <!-- TITLE -->
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">
                            AP Invoice
                        </h2>
                        <p class="text-sm text-gray-500">
                            {{ invoice.invoice_number }}
                        </p>
                    </div>
                </div>

                <!-- RIGHT: STATUS -->
                <span
                    class="px-3 py-1 text-sm rounded-full capitalize"
                    :class="statusClass"
                >
                    {{ invoice.status.replace('_', ' ') }}
                </span>
            </div>
        </template>
        <div class="p-6 space-y-6">

            <!-- ================= SUMMARY ================= -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white border rounded-lg p-4">
                    <div class="text-sm text-gray-500">Supplier</div>
                    <div class="font-semibold">
                        {{ invoice.supplier?.company_name }}
                    </div>
                </div>

                <div class="bg-white border rounded-lg p-4">
                    <div class="text-sm text-gray-500">Purchase Order</div>
                    <div class="font-semibold">
                        {{ invoice.purchase_order?.code }}
                    </div>
                </div>

                <div class="bg-white border rounded-lg p-4">
                    <div class="text-sm text-gray-500">Due Date</div>
                    <div class="font-semibold">
                        {{ formatDate(invoice.due_date) }}
                    </div>
                </div>
            </div>

            <!-- ================= FINANCIAL ================= -->
            <div class="bg-white border rounded-lg p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <div class="text-sm text-gray-500">Invoice Amount</div>
                    <div class="text-lg font-semibold">
                        {{ formatCurrency(invoice.invoice_amount) }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Paid Amount</div>
                    <div class="text-lg font-semibold text-green-600">
                        {{ formatCurrency(paidAmountDisplay) }}
                    </div>
                </div>

                <div>
                    <div class="text-sm text-gray-500">Outstanding Balance</div>
                    <div class="text-lg font-semibold text-red-600">
                        {{ formatCurrency(balanceAmountDisplay) }}
                    </div>
                </div>
            </div>

            <!-- ================= DETAILS ================= -->
            <div class="bg-white border rounded-lg p-4 text-sm space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-500">Invoice Date</span>
                    <span>{{ formatDate(invoice.invoice_date) }}</span>
                </div>

                <div class="flex justify-between">
                    <span class="text-gray-500">Remarks</span>
                    <span>{{ invoice.remarks ?? '-' }}</span>
                </div>
            </div>

            <!-- ================= ATTACHMENT ================= -->
            <div class="bg-white border rounded-lg p-4">
                <h3 class="font-semibold mb-3">Vendor Invoice</h3>

                <div
                    v-if="vendorInvoice"
                    class="flex justify-between items-center bg-gray-50 border rounded px-4 py-3 text-sm"
                >
                    <div class="flex items-center gap-2">
                        <i class="mdi mdi-file-document-outline"></i>
                        {{ vendorInvoice.original_name }}
                    </div>

                    <a
                        :href="`/storage/${vendorInvoice.file_path}`"
                        target="_blank"
                        class="text-indigo-600 hover:underline"
                    >
                        View
                    </a>
                </div>

                <div v-else class="text-sm text-gray-400">
                    No attachment found
                </div>
            </div>

            <!-- ================= PAYMENTS ================= -->
            <div class="bg-white border rounded-lg p-4 space-y-4">

                <div class="flex justify-between items-center">
                    <h3 class="font-semibold">Payments</h3>

                    <button
                        class="px-4 py-2 bg-indigo-600 text-white rounded text-sm"
                        @click="showPaymentModal = true"
                    >
                        Add Payment
                    </button>
                </div>

                <!-- PAYMENT TABLE -->
                <div v-if="payments.length" class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 text-gray-700">
                            <tr>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-left">Slip No</th>
                                <th class="px-4 py-2 text-left">Reference</th>
                                <th class="px-4 py-2 text-left">Attachment</th>
                                <th class="px-4 py-2 text-left">Remarks</th>
                                <th class="px-4 py-2 text-left">Recorded By</th>
                                <th class="px-4 py-2 text-left">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="payment in payments"
                                :key="payment.uuid"
                                class="border-t"
                                :class="payment.cancelled_at ? 'bg-red-50 text-gray-400' : ''"
                            >
                                <td class="px-4 py-2">
                                    {{ formatDate(payment.payment_date) }}
                                </td>

                                <td class="px-4 py-2 text-right font-medium">
                                    {{ formatCurrency(payment.amount) }}
                                </td>

                                <td class="px-4 py-2">
                                    {{ payment.payment_slip_no ?? '-' }}
                                </td>

                                <td class="px-4 py-2">
                                    {{ payment.reference ?? '-' }}
                                </td>

                                <td class="px-4 py-2">
                                    <div
                                        v-if="payment.attachments?.length"
                                        class="flex flex-wrap gap-2"
                                    >
                                        <a
                                            v-for="file in payment.attachments"
                                            :key="file.id"
                                            :href="`/storage/${file.file_path}`"
                                            target="_blank"
                                            class="px-2 py-1 bg-indigo-50 text-indigo-700 rounded text-xs hover:bg-indigo-100"
                                        >
                                            {{ file.original_name }}
                                        </a>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">-</span>
                                </td>

                                <td class="px-4 py-2">
                                    {{ payment.remarks ?? '-' }}
                                </td>

                                <td class="px-4 py-2 text-sm">
                                    {{ payment.created_by?.name ?? '-' }}
                                </td>

                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">

                                        <!-- EDIT -->
                                        <button
                                            class="text-indigo-600 hover:text-indigo-800"
                                            title="Edit payment"
                                            :disabled="payment.cancelled_at"
                                            :class="payment.cancelled_at ? 'opacity-50 cursor-not-allowed' : ''"
                                            @click="editPayment(payment)"
                                        >
                                            <i class="mdi mdi-pencil-outline text-lg"></i>
                                        </button>

                                        <!-- CANCEL -->
                                        <button
                                            class="text-red-600 hover:text-red-800"
                                            title="Cancel payment"
                                            :disabled="payment.cancelled_at"
                                            :class="payment.cancelled_at ? 'opacity-50 cursor-not-allowed' : ''"
                                            @click="openCancel(payment)"
                                        >
                                            <i class="mdi mdi-close-circle-outline text-lg"></i>
                                        </button>

                                        <!-- CANCELLED BADGE -->
                                        <span
                                            v-if="payment.cancelled_at"
                                            class="inline-flex items-center gap-1 text-xs text-gray-500 italic"
                                        >
                                            <i class="mdi mdi-cancel"></i>
                                            Cancelled
                                        </span>

                                    </div>
                                </td>

                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="text-sm text-gray-400">
                    No payments recorded.
                </div>

            </div>

        </div>
    </AuthenticatedLayout>

    <!-- ================= CREATE PAYMENT ================= -->
    <CreatePaymentModal
        :show="showPaymentModal"
        :invoice="invoice"
        @close="showPaymentModal = false"
    />

    <!-- ================= EDIT PAYMENT ================= -->
    <CreatePaymentModal
        :show="showEditPaymentModal"
        :invoice="invoice"
        :payment="selectedPayment"
        @close="showEditPaymentModal = false"
    />

    <!-- ================= CANCEL PAYMENT ================= -->
    <CancelPaymentModal
        :show="showCancelModal"
        :payment="selectedPayment"
        @close="showCancelModal = false"
        @success="showCancelModal = false"
    />
</template>
