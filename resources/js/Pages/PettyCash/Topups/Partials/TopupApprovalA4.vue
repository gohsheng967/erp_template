<script setup>
import { computed } from 'vue'
import { useFormat } from '@/Composables/useFormat'

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
        case 'verified_own_department':
        case 'verified_project_department':
            return { text: 'VERIFIED', color: 'rgba(37,99,235,0.15)' }
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

const statusLabel = computed(() => {
    switch (props.topup.status) {
        case 'verified_own_department':
            return 'Own Dept Verified'
        case 'verified_project_department':
            return 'Project Dept Verified'
        case 'approved':
            return 'CEO / GM Approved'
        case 'paid':
            return 'Payment Completed'
        default:
            return capitalize(props.topup.status ?? '')
    }
})

const verifiedByLabel = computed(() => {
    if (props.topup.status === 'verified_project_department' || props.topup.wallet?.context_type === 'project') {
        return 'Project Dept Verified By'
    }
    return 'Own Dept Verified By'
})

const approvalStages = computed(() => [
    {
        label: 'Requested by',
        user: props.topup.requester ?? null,
        at: props.topup.created_at ?? null,
    },
    {
        label: verifiedByLabel.value.replace('Verified By', 'Verified by'),
        user: props.topup.verifier ?? null,
        at: props.topup.verified_at ?? null,
    },
    {
        label: 'CEO / GM Approved by',
        user: props.topup.approver ?? null,
        at: props.topup.approved_at ?? null,
    },
])

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
                        {{ statusLabel }}
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

        <div
            class="mb-6 relative z-10"
        >
            <div
                class="grid gap-4"
                :style="{ gridTemplateColumns: `repeat(${approvalStages.length}, minmax(0, 1fr))` }"
            >
                <div v-for="stage in approvalStages" :key="stage.label">
                    <div class="h-10 mb-1 flex items-end">
                        <template v-if="signatureUrl(stage.user)">
                            <img
                                :src="signatureUrl(stage.user)"
                                :alt="`${stage.label} signature`"
                                class="h-8 max-w-[120px] object-contain"
                                @error="onSignatureImageError"
                            >
                            <div class="hidden text-[11px] text-gray-400 italic">No signature</div>
                        </template>
                        <div v-else class="text-[11px] text-gray-400 italic">No signature</div>
                    </div>
                    <div class="mb-3 border-b-2 border-gray-300"></div>
                    <div>{{ stage.label }}</div>
                    <div class="text-xs text-gray-500">
                        {{ stage.user?.name ?? '-' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ stage.at ? formatDateTime(stage.at) : '-' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-16 text-xs text-gray-400 text-center relative z-10">
            Generated by Infinite ERP - {{ new Date().toLocaleString() }}
        </div>
    </div>
</template>
