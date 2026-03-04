<script setup>
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'
import { amountToWords } from '@/helpers/string'
import SignatureSection from '@/Components/Document/SignatureSection.vue'

const { formatCurrency } = useFormat()

const props = defineProps({
    invoice: {
        type: Object,
        required: true,
    },
    slip: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
})

const projectName = computed(() => {
    return props.invoice.purchase_order?.purchase_request?.project?.name
        ?? props.invoice.purchase_order?.code
        ?? 'Project'
})

function formatDate(value) {
    if (!value) return '-'
    return new Date(value).toLocaleDateString('en-GB')
}

const slipNumber = computed(() => props.slip.slip_no ?? '-')
const slipDate = computed(() => props.slip.payment_date ?? null)

const amount = computed(() => Number(props.slip.amount ?? 0))
const amountWords = computed(() => amountToWords(amountDue.value) || '-')

const lessRetention = computed(() => Number(props.slip.less_retention ?? 0))
const lessRecoupment = computed(() => Number(props.slip.less_recoupment ?? 0))
const lessMaterialOb = computed(() => Number(props.slip.less_material_ob ?? 0))
const lessPaidPreviously = computed(() => Number(props.slip.less_paid_previously ?? 0))
const lessTotal = computed(() =>
    lessRetention.value +
    lessRecoupment.value +
    lessMaterialOb.value +
    lessPaidPreviously.value
)
const amountDue = computed(() => Math.max(amount.value - lessTotal.value, 0))

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
                            {{ invoice.supplier?.company_name ?? '-' }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Contractor's Works</div>
                        <div class="flex-1 font-semibold">
                            AP Invoice Payment
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-b border-gray-400 p-2">
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex">
                        <div class="w-40">Original Contract Value</div>
                        <div class="flex-1 font-semibold">
                            {{ formatCurrency(invoice.invoice_amount) }}
                        </div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Approved Variation To Date</div>
                        <div class="flex-1 font-semibold">-</div>
                    </div>
                    <div class="flex">
                        <div class="w-40">Adjusted Contract Amount</div>
                        <div class="flex-1 font-semibold">
                            {{ formatCurrency(invoice.invoice_amount) }}
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
                            <td class="border px-2 py-1">Less - Retention</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessRetention) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">Less - Recoupment Advance Payment</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessRecoupment) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">Less - Payment Material Purchased OB</td>
                            <td class="border px-2 py-1 text-right">
                                {{ formatLess(lessMaterialOb) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="border px-2 py-1">Less - Amount Paid Previously</td>
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
                    <div class="w-40">Remarks</div>
                    <div class="flex-1">
                        Invoice: <span class="font-semibold">{{ invoice.invoice_number }}</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-400 p-2 text-xs">
                <div class="grid grid-cols-3 gap-6">
                    <div>
                        <div class="mb-8 border-b"></div>
                        <div>Prepared by:</div>
                        <div class="text-gray-500">-</div>
                    </div>
                    <div>
                        <div class="mb-8 border-b"></div>
                        <div>Certified by:</div>
                        <div class="text-gray-500">-</div>
                    </div>
                    <div>
                        <div class="mb-8 border-b"></div>
                        <div>Certified by / Approved by:</div>
                        <div class="text-gray-500">-</div>
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

        <SignatureSection
            class="relative z-10"
            title="Prepared Signature"
        />
    </div>
</template>
