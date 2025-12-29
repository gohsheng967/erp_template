<script setup>
import { computed } from 'vue'

const props = defineProps({
    claim: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        required: true,
    },
})

/* =====================
   WATERMARK (PRINT SAFE)
===================== */
const watermark = computed(() => {
    switch (props.claim.status) {
        case 'paid':
            return { text: 'PAID', color: 'rgba(34,197,94,0.15)' }
        case 'rejected':
            return { text: 'REJECTED', color: 'rgba(239,68,68,0.15)' }
        case 'void':
            return { text: 'VOID', color: 'rgba(107,114,128,0.20)' }
        default:
            return null
    }
})

/* =====================
   HELPERS
===================== */
function formatDate(date) {
    if (!date) return '-'
    return new Date(date).toLocaleDateString('en-GB')
}

function formatCurrency(value) {
    return new Intl.NumberFormat('en-MY', {
        style: 'currency',
        currency: 'MYR',
        minimumFractionDigits: 2,
    }).format(value ?? 0)
}
</script>

<template>
<!-- =====================
     A4 PRINT ROOT
     (Playwright targets this)
====================== -->
<div
    class="claim-print relative bg-white text-gray-800 mx-auto
           w-[210mm] min-h-[297mm]
           p-[12mm] text-[13px] leading-relaxed"
>

    <!-- =====================
         WATERMARK
    ====================== -->
    <div
        v-if="watermark"
        class="absolute inset-0 flex items-center justify-center
               pointer-events-none select-none z-0"
    >
        <div
            class="text-[120px] font-extrabold rotate-[-25deg]
                   -translate-y-[120px]"
            :style="{ color: watermark.color }"
        >
            {{ watermark.text }}
        </div>
    </div>

    <!-- =====================
         HEADER
    ====================== -->
    <div class="flex justify-between items-start border-b pb-4 mb-6 relative z-10">

        <!-- COMPANY INFO -->
        <div class="flex gap-3 max-w-[65%]">
            <div>
                <div class="font-semibold text-base">
                    {{ company.company_name }}
                </div>
                <div class="text-xs text-gray-500">
                    Reg No: {{ company.company_reg_no }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ company.address }}
                </div>
                <div class="text-xs text-gray-500">
                    Tel: {{ company.office_number }}
                </div>
            </div>
        </div>

        <!-- DOCUMENT META -->
        <div class="text-right whitespace-nowrap">
            <div class="text-lg font-bold tracking-wide">
                CLAIM FORM
            </div>
            <div class="text-sm">
                Claim No: {{ claim.claim_no }}
            </div>
            <div class="text-xs mt-1">
                Status:
                <span class="font-semibold uppercase">
                    {{ claim.status }}
                </span>
            </div>
        </div>
    </div>

    <!-- =====================
         CLAIM INFO
    ====================== -->
    <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 relative z-10">
        <div>
            <span class="font-medium">Employee</span>:
            {{ claim.issuer?.name ?? '-' }}
        </div>

        <div>
            <span class="font-medium">Project</span>:
            {{ claim.project?.name ?? '-' }}
        </div>

        <div>
            <span class="font-medium">Submitted Date</span>:
            {{ formatDate(claim.submitted_at) }}
        </div>

        <div>
            <span class="font-medium">Currency</span>: MYR
        </div>
    </div>

    <!-- =====================
         ITEMS TABLE
    ====================== -->
    <table class="w-full border border-gray-400 mb-6 relative z-10">
        <thead class="bg-gray-100">
            <tr>
                <th class="border px-2 py-2 w-[28px] text-center">#</th>
                <th class="border px-2 py-2 text-left">Expense Title</th>
                <th class="border px-2 py-2 text-left">Description</th>
                <th class="border px-2 py-2 text-left">Type</th>
                <th class="border px-2 py-2 w-[95px] text-center">Ref No</th>
                <th class="border px-2 py-2 w-[95px] text-center">Receipt Date</th>
                <th class="border px-2 py-2 w-[110px] text-right">
                    Amount (RM)
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="(item, index) in claim.items ?? []"
                :key="item.id"
            >
                <td class="border px-2 py-2 text-center">
                    {{ index + 1 }}
                </td>
                <td class="border px-2 py-2">
                    {{ item.title }}
                </td>
                <td class="border px-2 py-2">
                    {{ item.description ?? '-' }}
                </td>
                <td class="border px-2 py-2">
                    {{ item.claim_type ?? '-' }}
                </td>
                <td class="border px-2 py-2 text-center">
                    {{ item.receipt_no }}
                </td>
                <td class="border px-2 py-2 text-center">
                    {{ formatDate(item.receipt_date) }}
                </td>
                <td class="border px-2 py-2 text-right tabular-nums">
                    {{ formatCurrency(item.amount) }}
                </td>
            </tr>

            <tr v-if="!claim.items?.length">
                <td
                    colspan="7"
                    class="border px-2 py-6 text-center text-gray-400"
                >
                    No claim items
                </td>
            </tr>
        </tbody>
    </table>

    <!-- =====================
         TOTAL
    ====================== -->
    <div class="flex justify-end mb-10 relative z-10">
        <div class="w-[260px] border-t pt-3">
            <div class="flex justify-between">
                <span class="font-medium">Total Amount</span>
                <span class="font-bold tabular-nums">
                    {{ formatCurrency(claim.total_amount) }}
                </span>
            </div>
        </div>
    </div>

    <!-- =====================
         REMARKS
    ====================== -->
    <div class="mb-10 relative z-10">
        <div class="font-medium mb-1">Remarks</div>
        <div class="border px-3 py-2 min-h-[60px] whitespace-pre-wrap">
            {{ claim.remark ?? '-' }}
        </div>
    </div>

    <!-- =====================
         APPROVALS
    ====================== -->
    <div class="grid grid-cols-3 gap-10 mt-16 text-sm relative z-10">
        <div>
            <div class="mb-8 border-b"></div>
            <div>Prepared By</div>
            <div class="text-xs text-gray-500">
                {{ claim.issuer?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.submitted_at) }}
            </div>
        </div>

        <div>
            <div class="mb-8 border-b"></div>
            <div>Approved By</div>
            <div class="text-xs text-gray-500">
                {{ claim.approver?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.approved_at) }}
            </div>
        </div>

        <div>
            <div class="mb-8 border-b"></div>
            <div>Paid By</div>
            <div class="text-xs text-gray-500">
                {{ claim.payer?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.paid_at) }}
            </div>
        </div>
    </div>

    <!-- =====================
         FOOTER
    ====================== -->
    <div class="mt-16 text-xs text-gray-400 text-center relative z-10">
        Generated by Infinite ERP • {{ new Date().toLocaleString() }}
    </div>

</div>
</template>
