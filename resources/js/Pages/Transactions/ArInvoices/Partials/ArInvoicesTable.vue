<script setup>
import { ref, computed, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'

import Actions from './Actions.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import ArInvoiceShowModal from '@/Pages/Transactions/ArInvoices/Partials/ArInvoiceShowModal.vue'

import { useFormat } from '@/Composables/useFormat'
const { formatCurrency, formatDateTime } = useFormat()

/* =========================
   PROPS
========================= */
const props = defineProps({
    invoices: {
        type: Object, // Inertia pagination
        required: true,
    },
    status: {
        type: String, // draft | issued | approved | received | cancelled
        required: true,
    },
})

/* =========================
   COLUMNS BY STATUS
========================= */
const columnsByStatus = {
    draft: [
        'title',
        'project',
        'customer',
        'total_amount',
        'items_progress',
        'action',
    ],

    issued: [
        'invoice_no',
        'title',
        'customer',
        'project',
        'total_amount',
        'issued_at',
        'action',
    ],

    approved: [
        'invoice_no',
        'title',
        'customer',
        'project',
        'total_amount',
        'approved_by',
        'approved_at',
        'action',
    ],

    received: [
        'invoice_no',
        'title',
        'customer',
        'project',
        'total_amount',
        'received_at',
        'action',
    ],

    cancelled: [
        'invoice_no',
        'title',
        'customer',
        'project',
        'total_amount',
        'action',
    ],
}

const columnLabels = {
    invoice_no: 'Invoice No',
    title: 'Title',
    project: 'Project',
    customer: 'Customer',
    total_amount: 'Total Amount',
    items_progress: 'Items / Total',
    issued_at: 'Issued At',
    approved_by: 'Approved By',
    approved_at: 'Approved At',
    received_at: 'Received At',
    action: 'Action',
}

const visibleColumns = computed(() =>
    columnsByStatus[props.status] ?? []
)

/* =========================
   SUBTOTAL
========================= */
const pageSubtotal = computed(() => {
    if (!props.invoices?.data) return 0

    return props.invoices.data.reduce(
        (sum, row) => sum + Number(row.total_amount ?? 0),
        0
    )
})

const subtotalColspan = computed(() => {
    let exclude = 1 // total_amount
    if (visibleColumns.value.includes('action')) exclude++
    return visibleColumns.value.length - exclude
})

/* =========================
   VIEW MODAL
========================= */
const showViewModal = ref(false)
const viewingInvoice = ref(null)

function openView(invoice) {
    viewingInvoice.value = invoice
    showViewModal.value = true
}

function closeView() {
    showViewModal.value = false
    viewingInvoice.value = null
}

/* =========================
   CANCEL (REPLACES DELETE)
========================= */
const showCancelModal = ref(false)
const cancellingInvoice = ref(null)

function askCancel(invoice) {
    cancellingInvoice.value = invoice
    showCancelModal.value = true
}

function confirmCancel() {
    if (!cancellingInvoice.value) return

    router.post(
        route('ar-invoices.destroy', cancellingInvoice.value.uuid),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['invoices', 'counts'] })
                showCancelModal.value = false
                cancellingInvoice.value = null
            },
        }
    )
}

function closeCancel() {
    showCancelModal.value = false
    cancellingInvoice.value = null
}

/* =========================
   HELPERS
========================= */
function rowClass(row) {
    // highlight draft when items sum == total
    if (
        props.status === 'draft' &&
        Number(row.items_sum_amount) === Number(row.total_amount)
    ) {
        return 'bg-green-50 hover:bg-green-100'
    }

    return 'hover:bg-gray-50'
}

function renderCell(row, col) {
    switch (col) {
        case 'invoice_no':
            return row.invoice_no

        case 'title':
            return row.title

        case 'project':
            return row.project?.name ?? '-'

        case 'customer':
            return row.customer?.name ?? '-'

        case 'total_amount':
            return formatCurrency(row.total_amount)

        case 'items_progress':
            return `${row.items_count ?? 0} / ${formatCurrency(row.items_sum_amount)}`

        case 'issued_at':
            return formatDateTime(row.issued_at) ?? '-'

        case 'approved_by':
            return row.approved_by?.name ?? '-'

        case 'approved_at':
            return formatDateTime(row.approved_at) ?? '-'

        case 'received_at':
            return formatDateTime(row.received_at) ?? '-'

        default:
            return ''
    }
}
</script>

<template>
<div class="overflow-x-auto border rounded bg-white">

<table class="min-w-full text-sm">

    <!-- HEADER -->
    <thead class="bg-gray-100 text-gray-700">
        <tr>
            <th
                v-for="col in visibleColumns"
                :key="col"
                class="px-4 py-3 text-left text-xs font-semibold uppercase"
                :class="{
                    'text-right': col === 'total_amount',
                    'text-center': col === 'action'
                }"
            >
                {{ columnLabels[col] }}
            </th>
        </tr>
    </thead>

    <!-- BODY -->
    <tbody>
        <tr
            v-for="invoice in invoices.data"
            :key="invoice.uuid"
            :class="['border-t', rowClass(invoice)]"
        >
            <td
                v-for="col in visibleColumns"
                :key="col"
                class="px-4 py-2"
                :class="{
                    'text-right tabular-nums': col === 'total_amount',
                    'text-center': col === 'action'
                }"
            >
                <Actions
                    v-if="col === 'action'"
                    :invoice="invoice"
                    :status="status"
                    @view="openView"
                    @cancel="askCancel"
                />

                <span v-else>
                    {{ renderCell(invoice, col) }}
                </span>
            </td>
        </tr>

        <tr v-if="!invoices.data.length">
            <td
                :colspan="visibleColumns.length"
                class="py-6 text-center text-gray-400"
            >
                No invoices found
            </td>
        </tr>
    </tbody>

    <!-- SUBTOTAL -->
    <tfoot
        v-if="invoices.data.length && visibleColumns.includes('total_amount')"
    >
        <tr class="border-t bg-gray-50 font-semibold">
            <td
                :colspan="subtotalColspan"
                class="px-4 py-3 text-right"
            >
                Subtotal (this page)
            </td>

            <td class="px-4 py-3 text-right tabular-nums">
                {{ formatCurrency(pageSubtotal) }}
            </td>

            <td v-if="visibleColumns.includes('action')"></td>
        </tr>
    </tfoot>
</table>

<!-- CANCEL CONFIRMATION -->
<DeleteConfirmation
    v-if="showCancelModal"
    title="Cancel Invoice"
    message="This invoice will be cancelled and cannot be edited. Are you sure?"
    confirm-text="Yes, Cancel Invoice"
    @confirm="confirmCancel"
    @close="closeCancel"
/>

<!-- VIEW MODAL -->
<ArInvoiceShowModal
    v-if="showViewModal"
    :invoice="viewingInvoice"
    @close="closeView"
    @refresh="() => router.reload({ only: ['invoices', 'counts'] })"
/>

</div>
</template>
