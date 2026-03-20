<script setup>
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

const page = usePage()
const authUserId = computed(() => page.props.auth?.user?.data?.id)

const { formatCurrency, formatDateTime } = useFormat()

const emit = defineEmits(['approve', 'pay', 'delete'])

const props = defineProps({
    topups: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
        required: true,
    },
})

const columnsByStatus = {
    my_in_progress: ['topup_no', 'wallet', 'status', 'amount', 'requested_by', 'requested_at', 'action'],
    my_rejected: ['topup_no', 'wallet', 'status', 'amount', 'requested_by', 'requested_at'],
    my_completed: ['topup_no', 'wallet', 'status', 'amount', 'requested_by', 'requested_at', 'payment_ref_no', 'voucher'],
    checked: ['topup_no', 'wallet', 'status', 'amount', 'requested_by', 'requested_at', 'action'],
    verified: ['topup_no', 'wallet', 'status', 'amount', 'verified_by', 'verified_at', 'action'],
    approval: ['topup_no', 'wallet', 'status', 'amount', 'approved_by', 'approved_at'],
    rejected: ['topup_no', 'wallet', 'status', 'amount', 'rejected_by', 'rejected_at'],
    payment: ['topup_no', 'wallet', 'status', 'amount', 'payment_status', 'payment_ref_no', 'voucher', 'action'],
    all_non_draft: ['topup_no', 'wallet', 'status', 'amount', 'requested_by', 'requested_at', 'payment_ref_no', 'voucher', 'action'],
}

const columnLabels = {
    topup_no: 'Top-Up No',
    wallet: 'Wallet',
    status: 'Status',
    amount: 'Amount',
    requested_by: 'Requested By',
    requested_at: 'Requested At',
    approved_by: 'CEO / GM Approved By',
    approved_at: 'CEO / GM Approved At',
    verified_by: 'Verified By',
    verified_at: 'Verified At',
    rejected_by: 'Rejected By',
    rejected_at: 'Rejected At',
    payment_status: 'Payment Status',
    voucher: 'Voucher',
    payment_ref_no: 'Payment Ref No',
    action: 'Action',
}

const visibleColumns = computed(() => columnsByStatus[props.status] ?? columnsByStatus.checked)

function walletLabel(row) {
    if (row.wallet?.context_type === 'office') return 'Office'
    return row.wallet?.project?.name ?? 'Project'
}

function normalizedStatus(row) {
    if (row.status === 'requested') return 'Checked'
    if (row.status === 'verified_own_department' || row.status === 'verified_project_department') return 'Verified'
    if (row.status === 'approved') return 'CEO / GM Approved'
    if (row.status === 'paid') return 'Paid'
    if (row.status === 'rejected') return 'Rejected'
    return String(row.status ?? '-')
}

function statusBadgeClass(row) {
    if (row.status === 'requested') return 'bg-amber-100 text-amber-700 border border-amber-200'
    if (row.status === 'verified_own_department' || row.status === 'verified_project_department') return 'bg-sky-100 text-sky-700 border border-sky-200'
    if (row.status === 'approved') return 'bg-violet-100 text-violet-700 border border-violet-200'
    if (row.status === 'paid') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    if (row.status === 'rejected') return 'bg-red-100 text-red-700 border border-red-200'
    return 'bg-gray-100 text-gray-700 border border-gray-200'
}

function paymentStatusText(row) {
    return row.status === 'paid' ? 'Paid' : 'Pending Payment'
}

function paymentStatusBadgeClass(row) {
    if (row.status === 'paid') return 'bg-emerald-100 text-emerald-700 border border-emerald-200'
    return 'bg-amber-100 text-amber-700 border border-amber-200'
}

function canTriggerApproval(row) {
    return ['requested', 'verified_own_department', 'verified_project_department'].includes(row.status)
}

function approvalActionTitle(row) {
    if (row.status === 'requested') return row.wallet?.context_type === 'project' ? 'Mark as Verified (Project Department)' : 'Mark as Verified (Own Department)'
    return 'CEO / GM Approve'
}

function canPay(row) {
    return row.status === 'approved'
}

function canDelete(row) {
    return row.status === 'requested' && authUserId.value && row.requested_by === authUserId.value
}

function renderCell(row, col) {
    switch (col) {
        case 'topup_no':
            return row.topup_no ?? '-'
        case 'wallet':
            return walletLabel(row)
        case 'amount':
            return formatCurrency(row.amount)
        case 'status':
            return normalizedStatus(row)
        case 'requested_by':
            return row.requester?.name ?? '-'
        case 'approved_by':
            return row.approver?.name ?? '-'
        case 'verified_by':
            return row.verifier?.name ?? '-'
        case 'requested_at':
            return row.created_at ? formatDateTime(row.created_at) : '-'
        case 'approved_at':
            return row.approved_at ? formatDateTime(row.approved_at) : '-'
        case 'verified_at':
            return row.verified_at ? formatDateTime(row.verified_at) : '-'
        case 'rejected_by':
            return row.rejector?.name ?? '-'
        case 'rejected_at':
            return row.rejected_at ? formatDateTime(row.rejected_at) : '-'
        case 'payment_status':
            return paymentStatusText(row)
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
                        <template v-if="col === 'action'">
                            <button
                                v-if="canTriggerApproval(topup)"
                                class="text-indigo-600 hover:text-indigo-800"
                                :title="approvalActionTitle(topup)"
                                @click="emit('approve', topup)"
                            >
                                <i class="mdi mdi-check-decagram-outline text-lg"></i>
                            </button>

                            <button
                                v-if="canPay(topup)"
                                class="ml-2 text-green-600 hover:text-green-800"
                                title="Pay"
                                @click="emit('pay', topup)"
                            >
                                <i class="mdi mdi-cash-check text-lg"></i>
                            </button>

                            <button
                                v-if="canDelete(topup)"
                                class="text-red-600 hover:text-red-800 ml-2"
                                title="Delete"
                                @click="emit('delete', topup)"
                            >
                                <i class="mdi mdi-trash-can-outline text-lg"></i>
                            </button>
                        </template>

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

                        <span
                            v-else-if="col === 'status'"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="statusBadgeClass(topup)"
                        >
                            {{ normalizedStatus(topup) }}
                        </span>

                        <span
                            v-else-if="col === 'payment_status'"
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="paymentStatusBadgeClass(topup)"
                        >
                            {{ paymentStatusText(topup) }}
                        </span>

                        <span v-else>
                            {{ renderCell(topup, col) }}
                        </span>
                    </td>
                </tr>

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
