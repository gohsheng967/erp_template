<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

import Actions from './Actions.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const props = defineProps({
    claims: {
        type: Object, // Inertia pagination object
        required: true,
    },
    status: {
        type: String, // draft | submitted | approved | rejected | paid
        required: true,
    },
})

/* =========================
   COLUMN CONFIG BY STATUS
========================= */

const columnsByStatus = {
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

    approved: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'approved_by',
        'approved_at',
    ],

    paid: [
        'claim_no',
        'title',
        'project',
        'total_amount',
        'paid_at',
        'payment_ref',
    ],
}

const columnLabels = {
    claim_no: 'Claim No',
    title: 'Title',
    project: 'Project',
    total_amount: 'Total Amount',
    items_progress: 'Items / Total',
    submitted_at: 'Submitted At',
    approved_by: 'Approved By',
    approved_at: 'Approved At',
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

/* =========================
   HELPERS
========================= */

function formatCurrency(value) {
    return new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(value ?? 0)
}

function rowClass(row) {
    // ✅ draft highlight when items == total
    if (
        props.status === 'draft' &&
        Number(row.items_total) === Number(row.total_amount)
    ) {
        return 'bg-green-50 hover:bg-green-100'
    }

    return 'hover:bg-gray-50'
}

function renderCell(row, col) {
    switch (col) {
        case 'claim_no':
            return row.claim_no

        case 'title':
            return row.title

        case 'project':
            return row.project?.name ?? '-'

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

        case 'payment_ref':
            return row.payment_ref ?? '-'

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
                            @delete="askDelete"
                        />

                        <!-- NORMAL CELL -->
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

    </div>
</template>
