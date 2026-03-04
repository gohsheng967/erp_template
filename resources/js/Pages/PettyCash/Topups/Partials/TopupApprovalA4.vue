<script setup>
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'
import SignatureSection from '@/Components/Document/SignatureSection.vue'

const { capitalize, formatCurrency, formatDateTime } = useFormat()

const props = defineProps({
    topup: {
        type: Object,
        required: true,
    },
    company: {
        type: Object,
        default: null,
    },
})

const watermark = computed(() => {
    switch (props.topup.status) {
        case 'approved':
            return { text: 'APPROVED', color: 'rgba(34,197,94,0.15)' }
        case 'rejected':
            return { text: 'REJECTED', color: 'rgba(239,68,68,0.15)' }
        case 'paid':
            return { text: 'PAID', color: 'rgba(14,116,144,0.15)' }
        default:
            return null
    }
})

const walletLabel = computed(() => {
    if (props.topup.wallet?.context_type === 'office') {
        return 'Office'
    }
    return props.topup.wallet?.project?.name ?? 'Project'
})
</script>

<template>
    <div
        class="claim-print relative bg-white text-gray-800 mx-auto
               w-[210mm] min-h-[297mm]
               p-[12mm] text-[13px] leading-relaxed
               border border-gray-300"
    >
        <div
            v-if="watermark"
            class="absolute inset-0 flex items-center justify-center
                   pointer-events-none select-none z-0"
        >
            <div
                class="text-[110px] font-extrabold rotate-[-25deg]
                       -translate-y-[120px]"
                :style="{ color: watermark.color }"
            >
                {{ watermark.text }}
            </div>
        </div>

        <div class="flex justify-between items-start border-b-2 border-gray-300 pb-4 mb-6 relative z-10">
            <div class="flex gap-3 max-w-[65%]">
                <div>
                    <div class="font-semibold text-base">
                        {{ company?.company_name ?? 'Company' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        Reg No: {{ company?.company_reg_no ?? '-' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ company?.address ?? '-' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        Tel: {{ company?.office_number ?? '-' }}
                    </div>
                </div>
            </div>

            <div class="text-right whitespace-nowrap">
                <div class="text-lg font-bold tracking-wide">
                    PETTY CASH TOP-UP
                </div>
                <div class="text-sm">
                    Top-Up No: {{ topup.topup_no ?? '-' }}
                </div>
                <div class="text-xs mt-1">
                    Status:
                    <span class="font-semibold uppercase">
                        {{ capitalize(topup.status ?? '') }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-x-40 gap-y-2 mb-6 relative z-10 border border-gray-300 rounded px-4 py-3">
            <div>
                <span class="font-medium">Requested By</span>:
                {{ topup.requester?.name ?? '-' }}
            </div>
            <div>
                <span class="font-medium">Requested At</span>:
                {{ topup.created_at ? formatDateTime(topup.created_at) : '-' }}
            </div>
            <div>
                <span class="font-medium">Wallet</span>:
                {{ walletLabel }}
            </div>
            <div>
                <span class="font-medium">Amount</span>:
                {{ formatCurrency(topup.amount) }}
            </div>
            <div>
                <span class="font-medium">Bank</span>:
                {{ topup.bank_account?.bank_name ?? '-' }}
            </div>
            <div>
                <span class="font-medium">Account No</span>:
                {{ topup.bank_account?.account_no ?? '-' }}
            </div>
            <div>
                <span class="font-medium">Account Name</span>:
                {{ topup.bank_account?.account_name ?? '-' }}
            </div>
        </div>

        <div class="mb-4 relative z-10">
            <div class="font-medium text-xs mb-1">Reason</div>
            <div class="border border-gray-300 px-2 py-1 min-h-[40px] text-xs whitespace-pre-wrap">
                {{ topup.reason ?? '-' }}
            </div>
        </div>

        <div
            v-if="topup.status === 'rejected'"
            class="mb-4 relative z-10"
        >
            <div class="font-medium text-xs mb-1">Rejection Reason</div>
            <div class="border border-gray-300 px-2 py-1 min-h-[40px] text-xs whitespace-pre-wrap">
                {{ topup.rejected_reason ?? '-' }}
            </div>
        </div>

        <div class="grid grid-cols-2 gap-x-12 gap-y-2 mb-6 relative z-10">
            <div>
                <div class="mb-8 border-b-2 border-gray-300"></div>
                <div>Requested By</div>
                <div class="text-xs text-gray-500">
                    {{ topup.requester?.name ?? '-' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ topup.created_at ? formatDateTime(topup.created_at) : '-' }}
                </div>
            </div>

            <div>
                <div class="mb-8 border-b-2 border-gray-300"></div>
                <div>Approved By</div>
                <div class="text-xs text-gray-500">
                    {{ topup.approver?.name ?? '-' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ topup.approved_at ? formatDateTime(topup.approved_at) : '-' }}
                </div>
            </div>
        </div>

        <SignatureSection
            class="relative z-10"
            title="Prepared Signature"
        />

        <div class="mt-16 text-xs text-gray-400 text-center relative z-10">
            Generated by Infinite ERP - {{ new Date().toLocaleString() }}
        </div>
    </div>
</template>
