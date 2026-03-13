<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

/* =========================
   PAGE / AUTH
========================= */
const page = usePage()
const authUserId = computed(() => page.props.auth?.user?.data?.id)

/* =========================
   FORMATTERS
========================= */
const { formatCurrency, formatDateTime } = useFormat()

/* =========================
   EMITS
========================= */
const emit = defineEmits(['approve', 'pay', 'delete'])

/* =========================
   PROPS
========================= */
const props = defineProps({
    topups: {
        type: Object, // Inertia pagination object
        required: true,
    },
    status: {
        type: String, // requested | verified_own_department | verified_project_department | rejected | payment
        required: true,
    },
})

/* =========================
   COLUMN CONFIG
========================= */
const columnsByStatus = {
    requested: [
        'topup_no',
        'wallet',
        'amount',
        'requested_by',
        'requested_at',
        'action',
    ],

    verified_own_department: [
        'topup_no',
        'wallet',
        'amount',
        'verified_by',
        'verified_at',
        'action',
    ],

    verified_project_department: [
        'topup_no',
        'wallet',
        'amount',
        'verified_by',
        'verified_at',
        'action',
    ],

    rejected: [
        'topup_no',
        'wallet',
        'amount',
        'rejected_by',
        'rejected_at',
    ],

    payment: [
        'topup_no',
        'wallet',
        'amount',
        'approved_by',
        'approved_at',
        'payment_status',
        'payment_ref_no',
        'voucher',
    ],

}

const columnLabels = {
    topup_no: 'Top-Up No',
    wallet: 'Wallet',
    amount: 'Amount',
    requested_by: 'Requested By',
    requested_at: 'Requested At',
    approved_by: 'CEO / GM Approved By',
    approved_at: 'CEO / GM Approved At',
    verified_by: 'Own Dept Verified By',
    verified_at: 'Own Dept Verified At',
    rejected_by: 'Rejected By',
    rejected_at: 'Rejected At',
    payment_status: 'Payment Status',
    voucher: 'Voucher',
    payment_ref_no: 'Payment Ref No',
    action: 'Action',
}

const visibleColumns = computed(
    () => columnsByStatus[props.status] ?? []
)

/* =========================
   CELL RENDERER
========================= */
function renderCell(row, col) {
    switch (col) {
        case 'topup_no':
            return row.topup_no ?? '-'

        case 'wallet':
            if (row.wallet?.context_type === 'office') {
                return 'Office'
            }
            return row.wallet?.project?.name ?? 'Project'

        case 'amount':
            return formatCurrency(row.amount)

        case 'requested_by':
            return row.requester?.name ?? '-'

        case 'approved_by':
            return row.approver?.name ?? '-'

        case 'verified_by':
            return row.verifier?.name ?? '-'

        case 'requested_at':
            return row.created_at
                ? formatDateTime(row.created_at)
                : '-'

        case 'approved_at':
            return row.approved_at
                ? formatDateTime(row.approved_at)
                : '-'

        case 'verified_at':
            return row.verified_at
                ? formatDateTime(row.verified_at)
                : '-'

        case 'rejected_by':
            return row.rejector?.name ?? '-'

        case 'rejected_at':
            return row.rejected_at
                ? formatDateTime(row.rejected_at)
                : '-'

        case 'payment_status':
            return row.status === 'paid' ? 'Paid' : 'Pending'

        case 'payment_ref_no':
            return row.payment_ref_no ?? '-'

        default:
            return ''
    }
}
</script>

<template>
    <div class="overflow-x-auto border rounded bg-white">
        <table class="min-w-full text-sm">

            <!-- ================= HEADER ================= -->
            <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th
                        v-for="col in visibleColumns"
                        :key="col"
                        class="px-4 py-3 text-left text-xs font-semibold uppercase"
                        :class="{
                            'text-right': col === 'amount',
                            'text-center': col === 'action' || col === 'voucher'
                        }"
                    >
                        {{ columnLabels[col] }}
                    </th>
                </tr>
            </thead>

            <!-- ================= BODY ================= -->
            <tbody>
                <tr
                    v-for="topup in topups.data"
                    :key="topup.id"
                    class="border-t hover:bg-gray-50"
                >
                    <td
                        v-for="col in visibleColumns"
                        :key="col"
                        class="px-4 py-2"
                        :class="{
                            'text-right tabular-nums': col === 'amount',
                            'text-center': col === 'action' || col === 'voucher'
                        }"
                    >
                        <!-- ================= ACTIONS ================= -->
                        <template v-if="col === 'action'">
                            <!-- APPROVE -->
                            <button
                                v-if="status === 'requested'"
                                class="text-indigo-600 hover:text-indigo-800"
                                :title="topup.wallet?.context_type === 'project' ? 'Project Dept Verified' : 'Own Dept Verified'"
                                @click="emit('approve', topup)"
                            >
                                <i class="mdi mdi-check-decagram-outline text-lg"></i>
                            </button>

                            <button
                                v-if="status === 'verified_own_department' || status === 'verified_project_department'"
                                class="text-indigo-600 hover:text-indigo-800"
                                title="CEO / GM Approved"
                                @click="emit('approve', topup)"
                            >
                                <i class="mdi mdi-check-circle-outline text-lg"></i>
                            </button>

                            <!-- PAY -->
                            <button
                                v-if="status === 'payment' && topup.status === 'approved'"
                                class="text-green-600 hover:text-green-800"
                                title="Pay"
                                @click="emit('pay', topup)"
                            >
                                <i class="mdi mdi-cash-check text-lg"></i>
                            </button>

                            <!-- DELETE (REQUESTER ONLY) -->
                            <button
                                v-if="
                                    status === 'requested' &&
                                    authUserId &&
                                    topup.requested_by === authUserId
                                "
                                class="text-red-600 hover:text-red-800 ml-2"
                                title="Delete"
                                @click="emit('delete', topup)"
                            >
                                <i class="mdi mdi-trash-can-outline text-lg"></i>
                            </button>
                        </template>

                        <!-- ================= VOUCHER ================= -->
                        <template v-else-if="col === 'voucher'">
                            <Link
                                v-if="topup.attachment?.url"
                                :href="topup.attachment?.url"
                                target="_blank"
                                title="View Voucher"
                                class="text-gray-600 hover:text-gray-800"
                            >
                                <i class="mdi mdi-file-eye-outline text-lg"></i>
                            </Link>
                            <span v-else>-</span>
                        </template>

                        <!-- ================= NORMAL CELL ================= -->
                        <span v-else>
                            {{ renderCell(topup, col) }}
                        </span>
                    </td>
                </tr>

                <!-- ================= EMPTY STATE ================= -->
                <tr v-if="!topups.data.length">
                    <td
                        :colspan="visibleColumns.length"
                        class="py-6 text-center text-gray-400"
                    >
                        No top-up records found
                    </td>
                </tr>
            </tbody>

        </table>
    </div>
</template>
