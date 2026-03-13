<script setup>
import { computed } from 'vue'
import { amountToWords } from '@/helpers/string'

const props = defineProps({
    claim: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: () => ({}),
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

function signatureUrl(user) {
    if (!user) return null
    if (user.signature) return user.signature
    if (user.signature_url) return user.signature_url
    if (user.signature_path) return `/storage/${String(user.signature_path).replace(/^\/+/, '')}`
    return null
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
           p-[12mm] text-[13px] leading-relaxed
           border border-gray-300"
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
    <div class="flex justify-between items-start border-b-2 border-gray-300 pb-4 mb-6 relative z-10">

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
    <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 relative z-10 border border-gray-300 rounded px-4 py-3">
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
        <thead class="bg-gray-100 border-b-2 border-gray-400">
            <tr>
                <th class="border border-gray-400 px-2 py-2 w-[28px] text-center">#</th>
                <th class="border border-gray-400 px-2 py-2 text-left">Expense Title</th>
                <th class="border border-gray-400 px-2 py-2 text-left">Description</th>
                <th class="border border-gray-400 px-2 py-2 text-left">Type</th>
                <th class="border border-gray-400 px-2 py-2 w-[95px] text-center">Ref No</th>
                <th class="border border-gray-400 px-2 py-2 w-[95px] text-center">Receipt Date</th>
                <th class="border border-gray-400 px-2 py-2 w-[110px] text-right">
                    Amount (RM)
                </th>
            </tr>
        </thead>

        <tbody>
            <tr
                v-for="(item, index) in claim.items ?? []"
                :key="item.id"
            >
                <td class="border border-gray-300 px-2 py-2 text-center">
                    {{ index + 1 }}
                </td>
                <td class="border border-gray-300 px-2 py-2">
                    {{ item.title }}
                </td>
                <td class="border border-gray-300 px-2 py-2">
                    {{ item.description ?? '-' }}
                </td>
                <td class="border border-gray-300 px-2 py-2">
                    {{ item.claim_type ?? '-' }}
                </td>
                <td class="border border-gray-300 px-2 py-2 text-center">
                    {{ item.receipt_no }}
                </td>
                <td class="border border-gray-300 px-2 py-2 text-center">
                    {{ formatDate(item.receipt_date) }}
                </td>
                <td class="border border-gray-300 px-2 py-2 text-right tabular-nums">
                    {{ formatCurrency(item.amount) }}
                </td>
            </tr>

            <tr v-if="!claim.items?.length">
                <td colspan="7" class="border border-gray-300 px-2 py-6 text-center text-gray-400">
                    No claim items
                </td>
            </tr>
        </tbody>
    </table>

    <!-- =====================
         TOTAL
    ====================== -->
    <div class="flex justify-end mb-10 relative z-10">
        <div class="w-[260px] border-t-2 border-gray-400 pt-3">
            <div class="flex justify-between">
                <span class="font-medium">Total Amount</span>
                <span class="font-bold tabular-nums">
                    {{ formatCurrency(claim.total_amount) }}
                </span>
            </div>
        </div>
    </div>

    <div class="mb-10 relative z-10">
        <div class="font-medium mb-1">Stage Remarks</div>
        <div class="border border-gray-300 px-3 py-2 space-y-1 text-xs">
            <div
                v-for="(log, idx) in (claim.remark_log ?? []).slice().reverse()"
                :key="`${idx}-${log?.at ?? ''}`"
                class="border-b last:border-b-0 pb-1 last:pb-0"
            >
                <div class="font-medium">
                    {{ (log?.from ?? '-').toUpperCase() }} -> {{ (log?.to ?? '-').toUpperCase() }}
                </div>
                <div>{{ log?.remark || '-' }}</div>
                <div class="text-[11px] text-gray-500">
                    {{ log?.user_name ?? 'System' }} • {{ log?.at ?? '-' }}
                </div>
            </div>
            <div v-if="!(claim.remark_log ?? []).length">-</div>
        </div>
    </div>

    <!-- =====================
         AMOUNT IN WORDS
    ====================== -->
    <div class="mb-10 relative z-10">
        <div class="font-medium mb-1">Amount In Words</div>
        <div class="border border-gray-300 px-3 py-2 min-h-[40px] whitespace-pre-wrap">
            {{ amountToWords(claim.total_amount) || '-' }}
        </div>
    </div>

    <!-- =====================
         APPROVALS
    ====================== -->
    <div class="grid grid-cols-4 gap-6 mt-16 text-sm relative z-10">
        <div>
            <div class="h-10 mb-1 flex items-end">
                <img
                    v-if="signatureUrl(claim.issuer)"
                    :src="signatureUrl(claim.issuer)"
                    alt="Prepared by signature"
                    class="h-8 max-w-[120px] object-contain"
                >
                <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
            </div>
            <div class="mb-3 border-b-2 border-gray-300"></div>
            <div>Prepared By</div>
            <div class="text-xs text-gray-500">
                {{ claim.issuer?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.submitted_at) }}
            </div>
        </div>

        <div>
            <div class="h-10 mb-1 flex items-end">
                <img
                    v-if="signatureUrl(claim.checker)"
                    :src="signatureUrl(claim.checker)"
                    alt="Checked by signature"
                    class="h-8 max-w-[120px] object-contain"
                >
                <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
            </div>
            <div class="mb-3 border-b-2 border-gray-300"></div>
            <div>Checked By</div>
            <div class="text-xs text-gray-500">
                {{ claim.checker?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.checked_at) }}
            </div>
        </div>

        <div>
            <div class="h-10 mb-1 flex items-end">
                <img
                    v-if="signatureUrl(claim.approver)"
                    :src="signatureUrl(claim.approver)"
                    alt="Approved by signature"
                    class="h-8 max-w-[120px] object-contain"
                >
                <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
            </div>
            <div class="mb-3 border-b-2 border-gray-300"></div>
            <div>Approved By</div>
            <div class="text-xs text-gray-500">
                {{ claim.approver?.name ?? '-' }}
            </div>
            <div class="text-xs text-gray-500">
                {{ formatDate(claim.approved_at) }}
            </div>
        </div>

        <div>
            <div class="h-10 mb-1 flex items-end">
                <img
                    v-if="signatureUrl(claim.payer)"
                    :src="signatureUrl(claim.payer)"
                    alt="Paid by signature"
                    class="h-8 max-w-[120px] object-contain"
                >
                <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
            </div>
            <div class="mb-3 border-b-2 border-gray-300"></div>
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
