<script setup>
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'
import { amountToWords } from '@/helpers/string'

const { formatCurrency } = useFormat()

const props = defineProps({
    slip: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
})

const slip = computed(() => props.slip ?? {})
const claim = computed(() => slip.value?.source ?? {})

function signerFromRemarkLog(targetStatus) {
    const logs = Array.isArray(claim.value?.remark_log) ? claim.value.remark_log : []
    const matched = [...logs].reverse().find((log) => log?.to === targetStatus)
    if (!matched) return null
    return {
        id: matched.user_id ?? null,
        name: matched.user_name ?? '-',
    }
}

const claimStageSigners = computed(() => {
    const transactionApproved = claim.value?.approver ?? signerFromRemarkLog('ceo_approved')

    return [
        { label: 'Submitted by', user: claim.value?.issuer ?? null },
        { label: 'Checked by', user: claim.value?.checker ?? null },
        { label: 'Verified by', user: signerFromRemarkLog('verified') },
        { label: 'Tx Approved by', user: transactionApproved },
        { label: 'Prepared by', user: slip.value?.creator ?? null },
        { label: 'Slip Approved by', user: slip.value?.approved_by ?? null },
        { label: 'Done by', user: claim.value?.payer ?? null },
    ]
})

const projectName = computed(() => claim.value?.project?.name ?? 'Others')

function formatDate(value) {
    if (!value) return '-'
    return new Date(value).toLocaleDateString('en-GB')
}

const slipDate = computed(() => slip.value.payment_date ?? claim.value?.approved_at ?? claim.value?.created_at)
const slipNumber = computed(() => slip.value.slip_no ?? '-')

const amount = computed(() => Number(slip.value.amount ?? claim.value?.total_amount ?? 0))
const amountWords = computed(() => amountToWords(amountDue.value) || '-')
const lessRetention = computed(() => Number(slip.value.less_retention ?? 0))
const lessRecoupment = computed(() => Number(slip.value.less_recoupment ?? 0))
const lessMaterialOb = computed(() => Number(slip.value.less_material_ob ?? 0))
const lessPaidPreviously = computed(() => Number(slip.value.less_paid_previously ?? 0))
const lessRetentionLabel = computed(() => slip.value.less_retention_label || 'Less - Retention')
const lessRecoupmentLabel = computed(() => slip.value.less_recoupment_label || 'Less - Recoupment Advance Payment')
const lessMaterialObLabel = computed(() => slip.value.less_material_ob_label || 'Less - Payment Material Purchased OB')
const lessPaidPreviouslyLabel = computed(() => slip.value.less_paid_previously_label || 'Less - Amount Paid Previously')
const remarkLabel = computed(() => slip.value.remark_label || 'Remarks')
const lessTotal = computed(() =>
    lessRetention.value +
    lessRecoupment.value +
    lessMaterialOb.value +
    lessPaidPreviously.value
)
const amountDue = computed(() => Math.max(amount.value - lessTotal.value, 0))

function signatureUrl(user) {
    if (!user) return null
    const raw = user.signature ?? user.signature_url ?? user.signature_path ?? null
    if (!raw) return null

    const value = String(raw).trim()
    if (!value) return null
    if (/^https?:\/\//i.test(value)) return value
    if (value.startsWith('data:image/')) return value
    if (user.id) return `/media/signatures/${user.id}`
    if (value.startsWith('/storage/')) return value
    if (value.startsWith('storage/')) return `/${value}`
    return `/storage/${value.replace(/^\/+/, '')}`
}

function onSignatureImageError(event) {
    const img = event?.target
    if (!img) return
    img.classList.add('hidden')
    const fallback = img.nextElementSibling
    if (fallback) fallback.classList.remove('hidden')
}

function formatLess(value) {
    return value > 0 ? formatCurrency(value) : '-'
}
</script>

<template>
    <div
        class="claim-print relative bg-white text-gray-800 mx-auto
               w-[210mm] min-h-[297mm]
               p-[12mm] text-[13px] leading-relaxed"
    >
        <div class="flex items-center justify-between mb-4">
            <div class="text-sm font-semibold">
                [PROJECT / ACCOUNT] COPY
            </div>
            <div class="border border-gray-400 px-6 py-2 font-semibold text-sm">
                CERTIFICATE OF PAYMENT
            </div>
        </div>

        <div class="border border-gray-400">
            <div class="border-b border-gray-400 p-2 flex">
                <div class="w-40 text-xs">PROJECT NAME :</div>
                <div class="flex-1 text-xs font-semibold">
                    {{ projectName }}
                </div>
            </div>

            <div class="border-b border-gray-400 p-2">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex">
                        <div class="w-40">Contractor's Name</div>
                        <div class="flex-1 font-semibold">
                            {{ claim.issuer?.name ?? '-' }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Contractor's Works</div>
                        <div class="flex-1 font-semibold">
                            {{ claim.title ?? '-' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-b border-gray-400 p-2">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex">
                        <div class="w-40">Original Contract Value</div>
                        <div class="flex-1 font-semibold">
                            {{ formatCurrency(amount) }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Approved Variation To Date</div>
                        <div class="flex-1 font-semibold">-</div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Adjusted Contract Amount</div>
                        <div class="flex-1 font-semibold">
                            {{ formatCurrency(amount) }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Date of Completion</div>
                        <div class="flex-1 font-semibold">-</div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Cert No & Date</div>
                        <div class="flex-1 font-semibold">
                            {{ slipNumber }} / {{ formatDate(slipDate) }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Payment Terms</div>
                        <div class="flex-1 font-semibold">FULL</div>
                    </div>
                </div>
            </div>

            <div class="border-b border-gray-400 p-2 text-xs">
                <div class="font-medium mb-1">Notes</div>
                <div class="min-h-[30px]">
                    {{ slip.payment_slip_remark ?? '-' }}
                </div>
            </div>

            <div class="p-2 text-xs">
                <table class="w-full border border-gray-300">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border px-2 py-1 text-left">Account Remarks</th>
                            <th class="border px-2 py-1 text-left w-32">Amount (RM)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="border px-2 py-1">Gross Valuation</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatCurrency(amount) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">{{ lessRetentionLabel }}</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessRetention) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">{{ lessRecoupmentLabel }}</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessRecoupment) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">{{ lessMaterialObLabel }}</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessMaterialOb) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">{{ lessPaidPreviouslyLabel }}</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessPaidPreviously) }}
                            </td>
                        </tr>
                        <tr class="font-semibold">
                            <td class="border px-2 py-1">Amount Due For Payment No.1</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatCurrency(amountDue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-400 p-2 text-xs">
                <div class="flex gap-4">
                    <div class="w-40">Amount In Words</div>
                    <div class="flex-1 font-semibold">
                        {{ amountWords }}
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-400 p-2 text-xs">
                <div class="flex gap-4">
                    <div class="w-40">{{ remarkLabel }}</div>
                    <div class="flex-1">
                        Charge to: <span class="font-semibold">{{ projectName }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-400 p-2 text-xs">
                <div
                    class="grid gap-4"
                    :style="{ gridTemplateColumns: `repeat(${claimStageSigners.length}, minmax(0, 1fr))` }"
                >
                    <div v-for="stage in claimStageSigners" :key="stage.label">
                        <div class="h-14 mb-2 border-b border-gray-400 flex items-end">
                            <template v-if="signatureUrl(stage.user)">
                                <img
                                    :src="signatureUrl(stage.user)"
                                    :alt="`${stage.label} signature`"
                                    class="h-12 object-contain"
                                    @error="onSignatureImageError"
                                />
                                <div class="hidden text-[11px] text-gray-400 italic">No signature</div>
                            </template>
                        </div>
                        <div class="font-medium">{{ stage.label }}</div>
                        <div class="text-gray-500">{{ stage.user?.name ?? '-' }}</div>
                    </div>
                </div>
                <div class="mt-4 text-xs text-gray-500">
                    Company Bank Account:
                    <span class="font-semibold">
                        {{ slip.company_bank_account?.bank_name ?? '-' }}
                        {{ slip.company_bank_account?.account_no ?? '' }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

