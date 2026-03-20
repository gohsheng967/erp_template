<script setup>
import { ref, computed, nextTick  } from 'vue'
import { router } from '@inertiajs/vue3'
import { getCurrentInstance } from 'vue'

import Actions from './Actions.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import ClaimShowModal from '@/Pages/Transactions/Claims/Partials/ClaimShowModal.vue'

import { useFormat } from '@/Composables/useFormat'
const { formatCurrency } = useFormat()

const props = defineProps({
    claims: {
        type: Object, // Inertia pagination object
        required: true,
    },
    status: {
        type: String, // draft | submitted | checked | verified | approved | payment | rejected
        required: true,
    },
})

/* =========================
   COLUMN CONFIG BY STATUS
========================= */

const columnsByStatus = {
    all_non_draft: [
        'claim_no',
        'title',
        'project',
        'status',
        'total_amount',
        'submitted_at',
        'action',
    ],

    my_in_progress: [
        'claim_no',
        'title',
        'project',
        'status',
        'total_amount',
        'submitted_at',
        'action',
    ],

    my_rejected: [
        'claim_no',
        'title',
        'project',
        'status',
        'total_amount',
        'submitted_at',
        'action',
    ],

    my_completed: [
        'claim_no',
        'title',
        'project',
        'status',
        'total_amount',
        'submitted_at',
        'action',
    ],

    draft: [
        'title',
        'project',
        'total_amount',
        'items_progress',
        'action',
    ],

    submitted: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'submitted_at',
        'action',
    ],

    checked: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'submitted_at',
        'action',
    ],

    verified: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'submitted_at',
        'action',
    ],

    rejected: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'submitted_at',
        'action',
    ],

    approved: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'submitted_at',
        'action',
    ],

    payment: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'payment_status',
        'action',
    ],
}

const columnLabels = {
    claim_no: 'Claim No',
    title: 'Title',
    project: 'Project',
    total_amount: 'Total Amount',
    status: 'Status',
    items_progress: 'Items / Total',
    submitted_at: 'Submitted At',
    approved_by: 'Approved By',
    approved_at: 'Approved At',
    payment_status: 'Payment Status',
    paid_at: 'Paid At',
    payment_ref: 'Payment Ref',
    action: 'Action',
}

const visibleColumns = computed(() =>
    columnsByStatus[props.status] ?? []
)

/* =========================
   SUBTOTAL
========================= */

const pageSubtotal = computed(() => {
    if (!props.claims?.data) return 0

    return props.claims.data.reduce((sum, row) => {
        return sum + Number(row.total_amount ?? 0)
    }, 0)
})

const subtotalColspan = computed(() => {
    let exclude = 1 // total_amount column
    if (visibleColumns.value.includes('action')) exclude++
    return visibleColumns.value.length - exclude
})

/* =========================
   DELETE CONFIRMATION
========================= */

const showDeleteModal = ref(false)
const deletingClaim = ref(null)

const showViewModal = ref(false)
const viewingClaim = ref(null)

function openView(claim) {
    viewingClaim.value = claim
    showViewModal.value = true
}

function closeView() {
    showViewModal.value = false
    viewingClaim.value = null
}

function askDelete(claim) {
    deletingClaim.value = claim
    showDeleteModal.value = true
}

function confirmDelete() {
    if (!deletingClaim.value) return

    router.delete(
        route('claims.destroy', deletingClaim.value.uuid),
        {
            preserveScroll: true,
            onSuccess: () => {
                // 🔄 refresh list + badges
                router.reload({ only: ['claims', 'counts'] })

                showDeleteModal.value = false
                deletingClaim.value = null
            },
        }
    )
}

function closeDelete() {
    showDeleteModal.value = false
    deletingClaim.value = null
}

function refreshList() {
    router.reload({
        only: ['claims', 'counts'],
        preserveScroll: true,
    })
}

function printClaim(claim = null) {
    if (claim) {
        viewingClaim.value = claim
        showViewModal.value = true
    }

    nextTick(() => {
        setTimeout(() => window.print(), 300)
    })
}
/* =========================
   HELPERS
========================= */

function rowClass(row) {
    if (row.deleted_at) {
        return 'bg-rose-50 hover:bg-rose-100'
    }

    // ✅ draft highlight when items == total
    if (
        ['draft', 'my_in_progress'].includes(props.status) &&
        row.status === 'draft' &&
        Number(row.items_total) === Number(row.total_amount)
    ) {
        return 'bg-green-50 hover:bg-green-100'
    }

    return 'hover:bg-gray-50'
}

function statusText(row) {
    if (row.deleted_at) return 'Cancelled'

    return String(row.status ?? '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase())
}

function statusBadgeClass(row) {
    if (row.deleted_at) return 'bg-rose-100 text-rose-700 border border-rose-200'

    const status = String(row.status ?? '')
    if (status === 'draft') return 'bg-slate-100 text-slate-700 border border-slate-200'
    if (status === 'submitted') return 'bg-amber-100 text-amber-700 border border-amber-200'
    if (status === 'checked') return 'bg-sky-100 text-sky-700 border border-sky-200'
    if (status === 'verified') return 'bg-indigo-100 text-indigo-700 border border-indigo-200'
    if (status === 'approved' || status === 'ceo_approved') return 'bg-violet-100 text-violet-700 border border-violet-200'
    if (status === 'paid') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    if (status === 'rejected') return 'bg-red-100 text-red-700 border border-red-200'

    return 'bg-gray-100 text-gray-700 border border-gray-200'
}

function paymentStatusText(row) {
    return row.status === 'paid' ? 'Paid' : 'Pending Payment'
}

function paymentStatusBadgeClass(row) {
    if (row.status === 'paid') {
        return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    }

    return 'bg-amber-100 text-amber-700 border border-amber-200'
}

function renderCell(row, col) {
    switch (col) {
        case 'claim_no':
            return row.claim_no

        case 'title':
            return row.title

        case 'project':
            return row.project?.name ?? '-'

        case 'status':
            return statusText(row)

        case 'total_amount':
            return formatCurrency(row.total_amount)

        case 'items_progress':
            return `${row.items_count ?? 0} / ${formatCurrency(row.items_sum_amount  )}`

        case 'submitted_at':
            return row.submitted_at ?? '-'

        case 'approved_by':
            return row.approved_by?.name ?? '-'

        case 'approved_at':
            return row.approved_at ?? '-'

        case 'paid_at':
            return row.paid_at ?? '-'

        case 'payment_status':
            return paymentStatusText(row)

        case 'payment_ref':
            return row.payment_ref_no ?? '-'

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
                    v-for="claim in claims.data"
                    :key="claim.uuid"
                    :class="['border-t', rowClass(claim)]"
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
                        <Actions
                            v-if="col === 'action'"
                            :claim="claim"
                            :status="status"
                            @view="openView"
                            @delete="askDelete"
                        />

                        <!-- NORMAL CELL -->
                        <span
                            v-else-if="col === 'status'"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="statusBadgeClass(claim)"
                        >
                            {{ statusText(claim) }}
                        </span>

                        <span
                            v-else-if="col === 'payment_status'"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="paymentStatusBadgeClass(claim)"
                        >
                            {{ paymentStatusText(claim) }}
                        </span>

                        <span v-else>
                            {{ renderCell(claim, col) }}
                        </span>
                    </td>
                </tr>

                <!-- EMPTY STATE -->
                <tr v-if="!claims.data.length">
                    <td
                        :colspan="visibleColumns.length"
                        class="py-6 text-center text-gray-400"
                    >
                        No claims found
                    </td>
                </tr>
            </tbody>

            <!-- SUBTOTAL -->
            <tfoot
                v-if="claims.data.length && visibleColumns.includes('total_amount')"
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

        <!-- DELETE CONFIRMATION -->
        <DeleteConfirmation
            v-if="showDeleteModal"
            title="Delete Claim"
            message="This action cannot be undone. Are you sure you want to delete this claim?"
            @confirm="confirmDelete"
            @close="closeDelete"
        />

        <ClaimShowModal
            v-if="showViewModal"
            :claim="viewingClaim"
            :hide-approval-actions="status.startsWith('my_')"
            @close="closeView"
            @refresh="refreshList"
            @print="printClaim"
        />

    </div>
</template>
