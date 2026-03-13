<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

import PurchaseRequestActions from './PurchaseRequestActions.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import { useFormat } from '@/Composables/useFormat'

const { formatCurrency } = useFormat()

const emit = defineEmits(['view', 'view-pr', 'delivery', 'payable', 'payment-slip'])

const props = defineProps({
    prs: {
        type: Object, // Inertia paginator
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
})

/* =========================
   COLUMNS BY STATUS
========================= */
const columnsByStatus = {
    draft: [
        'title',
        'project_linked',
        'total_amount',
        'items_count',
        'action',
    ],

    submitted: [
        'pr_no',
        'title',
        'project_linked',
        'requester',
        'total_amount',
        'submitted_at',
        'action',
    ],

    verified_own_department: [
        'pr_no',
        'title',
        'project_linked',
        'requester',
        'total_amount',
        'submitted_at',
        'action',
    ],

    verified_project_department: [
        'pr_no',
        'title',
        'project_linked',
        'requester',
        'total_amount',
        'submitted_at',
        'action',
    ],

    verified_purchasing_department: [
        'pr_no',
        'title',
        'project_linked',
        'requester',
        'total_amount',
        'submitted_at',
        'action',
    ],

    po: [
        'pr_no',
        'project_linked',
        'po_no',
        'approved_quotation',
        'reviewer_remark',
        'action',
    ],

    payment: [
        'pr_no',
        'project_linked',
        'po_no',
        'payment_status',
        'requester',
        'total_amount',
        'approved_quotation',
        'action',
    ],

    rejected: [
        'pr_no',
        'title',
        'project_linked',
        'requester',
        'total_amount',
        'submitted_at',
        'action',
    ],
}

const columnLabels = {
    pr_no: 'PR No',
    po_no: 'PO No',
    payment_status: 'Payment Status',
    title: 'Title / Purpose',
    project_linked: 'Project Linked',
    requester: 'Requested By',
    total_amount: 'Total Amount',
    items_count: 'Items',
    submitted_at: 'Submitted At',
    approved_by: 'Approved By',
    approved_at: 'Approved At',
    reviewer_remark: 'Approver Remark',
    approved_quotation: 'Approved Quotation',
    action: 'Action',
}

const visibleColumns = computed(() =>
    columnsByStatus[props.status] ?? []
)

/* =========================
   SUBTOTAL
========================= */
const pageSubtotal = computed(() => {
    if (!props.prs?.data) return 0

    return props.prs.data.reduce((sum, row) => {
        return sum + Number(row.total_amount ?? 0)
    }, 0)
})

const subtotalColspan = computed(() => {
    let exclude = 1 // total_amount
    if (visibleColumns.value.includes('action')) exclude++
    return visibleColumns.value.length - exclude
})

/* =========================
   DELETE
========================= */
const showDeleteModal = ref(false)
const deletingPR = ref(null)

function askDelete(pr) {
    deletingPR.value = pr
    showDeleteModal.value = true
}

function confirmDelete() {
    if (!deletingPR.value) return

    router.delete(
        route('purchase-request.destroy', deletingPR.value.uuid),
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['purchaseRequests', 'counts'] })
                closeDelete()
            },
        }
    )
}

function closeDelete() {
    showDeleteModal.value = false
    deletingPR.value = null
}

/* =========================
   ROW CLICK (ISSUED ONLY)
========================= */
function onRowClick(pr) {
    if (props.status !== 'po') return
    if (!pr.purchase_order?.uuid) return

    router.visit(
        route('purchase-orders.deliveries.index', pr.purchase_order.uuid)
    )
}

/* =========================
   HELPERS
========================= */
function rowClass() {
    return 'hover:bg-gray-50'
}

function renderCell(row, col) {
    switch (col) {
        case 'pr_no':
            return row.code

        case 'po_no':
            return row.purchase_order?.code ?? '-'

        case 'reviewer_remark':
            return row.reviewer_remark ?? '-'

        case 'payment_status':
            return row.purchase_order?.ap_invoice?.status
                ? String(row.purchase_order.ap_invoice.status).replaceAll('_', ' ')
                : 'pending'

        case 'title':
            return row.title

        case 'project_linked':
            return row.project?.name
                ? `Yes (${row.project.name})`
                : 'No'

        case 'requester':
            return row.requester?.name ?? '-'

        case 'total_amount':
            return formatCurrency(row.total_amount)

        case 'items_count':
            return row.items_count ?? 0

        case 'submitted_at':
            return row.submitted_at ?? '-'

        case 'approved_by':
            return row.approver?.name ?? '-'

        case 'approved_at':
            return row.approved_at ?? '-'

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
                    v-for="pr in prs.data"
                    :key="pr.uuid"
                    :class="[
                        'border-t',
                        rowClass(),
                        (status === 'po' || status === 'payment')
                            ? 'cursor-pointer hover:bg-indigo-50'
                            : ''
                    ]"
                    @click="onRowClick(pr)"
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
                        <!-- ACTIONS -->
                        <PurchaseRequestActions
                            v-if="col === 'action'"
                            :pr="pr"
                            :status="status"
                            @delete="askDelete"
                            @view="emit('view', $event)"
                            @view-pr="emit('view-pr', $event)"
                            @delivery="emit('delivery', $event)"
                            @payable="emit('payable', $event)"
                            @payment-slip="emit('payment-slip', $event)"
                        />

                        <!-- APPROVED QUOTATION ICON -->
                        <a
                            v-else-if="col === 'approved_quotation'
                                && pr.approved_quotation?.attachment?.url"
                            :href="pr.approved_quotation.attachment.url"
                            target="_blank"
                            @click.stop
                            class="inline-flex items-center justify-center text-indigo-600 hover:text-indigo-800"
                            title="View approved quotation"
                        >
                            <i class="mdi mdi-paperclip text-lg"></i>
                        </a>

                        <span
                            v-else-if="col === 'approved_quotation'"
                            class="text-gray-400"
                        >
                            -
                        </span>

                        <span
                            v-else-if="col === 'payment_status'"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                            :class="{
                                'bg-amber-100 text-amber-800': renderCell(pr, col) === 'pending',
                                'bg-blue-100 text-blue-800': renderCell(pr, col) === 'confirmed',
                                'bg-indigo-100 text-indigo-800': renderCell(pr, col) === 'partially paid',
                                'bg-green-100 text-green-800': renderCell(pr, col) === 'paid',
                            }"
                        >
                            {{ renderCell(pr, col) }}
                        </span>

                        <!-- NORMAL CELLS -->
                        <span v-else>
                            {{ renderCell(pr, col) }}
                        </span>
                    </td>
                </tr>

                <!-- EMPTY -->
                <tr v-if="!prs.data.length">
                    <td
                        :colspan="visibleColumns.length"
                        class="py-6 text-center text-gray-400"
                    >
                        No purchase requests found
                    </td>
                </tr>
            </tbody>

            <!-- SUBTOTAL -->
            <tfoot
                v-if="prs.data.length && visibleColumns.includes('total_amount')"
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

        <!-- DELETE CONFIRM -->
        <DeleteConfirmation
            v-if="showDeleteModal"
            title="Delete Purchase Request"
            message="This action cannot be undone. Are you sure?"
            @confirm="confirmDelete"
            @close="closeDelete"
        />
    </div>
</template>
