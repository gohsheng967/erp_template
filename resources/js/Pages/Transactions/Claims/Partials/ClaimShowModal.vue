<script setup>
import { ref, watch, computed, nextTick, inject } from 'vue'
import axios from 'axios'
import ClaimShowA4 from './ClaimShowA4.vue'

/* =========================
   PROPS / EMITS
========================= */
const printing = ref(false)

const props = defineProps({
    claim: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'refresh', 'print'])

/* =========================
   STATE
========================= */

const loading = ref(false)
const submitting = ref(false)

const fullClaim = ref(null)
const company = ref(null)

const approvalRemark = ref('')
const toast = inject('toast', null)

const reviewFlowLabels = {
    submitted: 'Mark as Checked',
    checked: 'Mark as Verified',
    verified: 'Approve',
    approved: 'CEO Approve',
}

const reviewFlowNextStatus = {
    submitted: 'checked',
    checked: 'verified',
    verified: 'approved',
    approved: 'ceo_approved',
}

const canReview = computed(() =>
    ['submitted', 'checked', 'verified', 'approved'].includes(fullClaim.value?.status)
)

const rejectAction = computed(() =>
    fullClaim.value?.status === 'approved' ? 'rejected' : 'draft'
)

const rejectActionLabel = computed(() =>
    fullClaim.value?.status === 'approved'
        ? 'Reject'
        : 'Return for Further Modification'
)

const stageRemarks = computed(() => {
    const logs = fullClaim.value?.remark_log ?? []
    if (!Array.isArray(logs)) return []

    return logs
        .map((row, index) => ({
            key: `${index}-${row?.at ?? ''}`,
            label: `${(row?.from ?? '-').toUpperCase()} -> ${(row?.to ?? '-').toUpperCase()}`,
            value: row?.remark || '-',
            meta: `${row?.user_name ?? 'System'} - ${row?.at ?? '-'}`,
        }))
        .reverse()
})

/* =========================
   COMPUTED
========================= */

// Claim-level payment vouchers (attachments)
const paymentVouchers = computed(() => {
    const slipFiles = fullClaim.value?.payment_slip?.attachments ?? []
    const claimFiles = fullClaim.value?.attachments?.filter(
        a => a.category === 'payment_voucher'
    ) ?? []

    return [...slipFiles, ...claimFiles]
})

// Item-level receipts (flattened, optional helper)
const itemReceipts = computed(() =>
    fullClaim.value?.items?.flatMap(item =>
        (item.attachments ?? []).map(a => ({
            ...a,
            item_title: item.title,
        }))
    ) ?? []
)

/* =========================
   LOAD CLAIM (ALWAYS FRESH)
========================= */

async function loadClaim() {
    if (!props.claim?.uuid) return

    loading.value = true
    fullClaim.value = null
    company.value = null
    approvalRemark.value = ''

    try {
        const res = await axios.get(
            route('claims.show', props.claim.uuid)
        )

        fullClaim.value = res.data.claim
        company.value = res.data.company

    } catch (e) {
        console.error('Failed to load claim', e)
    } finally {
        loading.value = false
    }
}

/* =========================
   WATCH (OPEN MODAL)
========================= */

watch(
    () => props.claim.uuid,
    () => loadClaim(),
    { immediate: true }
)

/* =========================
   APPROVE / REJECT
========================= */

async function submitDecision(status) {
    if (!fullClaim.value) return

    submitting.value = true

    try {
        await axios.post(
            route('claims.approval', fullClaim.value.uuid),
            {
                status,
                remark: approvalRemark.value,
            }
        )

        if (status === 'draft' || status === 'rejected') {
            emit('refresh')
            emit('close')
            return
        }

        await loadClaim()
        emit('refresh')

    } catch (e) {
        console.error('Approval failed', e)
        const msg =
            e?.response?.data?.message
            ?? e?.response?.data?.errors?.status?.[0]
            ?? 'Failed to update claim status.'
        toast?.value?.show(msg, 'error')
    } finally {
        submitting.value = false
    }
}

/* =========================
   CLOSE
========================= */

function closeModal() {
    emit('refresh')
    emit('close')
}

function printPage() {
  printing.value = true

  nextTick(() => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.print()
        printing.value = false
      })
    })
  })
}

</script>

<template>
<div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print">

<div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">

<!-- HEADER -->
<div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center no-print">
    
    <!-- LEFT: TITLE -->
    <h2 class="font-semibold text-lg">
        Claim Preview — {{ fullClaim?.claim_no }}
    </h2>

    <!-- RIGHT: ACTIONS -->
    <div class="ml-auto flex items-center gap-3">
            <button class="no-print" @click="printPage">
            Print / Save PDF
            </button>


        <button
            @click="closeModal"
            class="text-red-600"
        >
            <i class="mdi mdi-close text-xl"></i>
        </button>
    </div>
</div>

<!-- BODY -->
<div class="flex h-[calc(100%-56px)] gap-6 p-6">

<!-- LEFT: A4 DOCUMENT -->
<div class="flex-1 overflow-auto">
    <div v-if="loading" class="text-center py-24 text-gray-400">
        Loading claim document…
    </div>

    <ClaimShowA4
    v-if="fullClaim && !printing"
    :claim="fullClaim"
    :company="company || {}"
    />

    <Teleport to="body">
    <ClaimShowA4
        v-if="fullClaim && printing"
        :claim="fullClaim"
        :company="company || {}"
    />
    </Teleport>
</div>

<!-- RIGHT: SIDEBAR -->
<div
    v-if="fullClaim"
    class="w-[360px] shrink-0 bg-white border rounded-lg p-4 space-y-6 overflow-auto no-print"
>

<!-- STATUS -->
<div>
    <div class="text-xs text-gray-500">Status</div>
    <div class="font-semibold uppercase">
        {{ fullClaim.status }} 
    </div>
</div>

<!-- ATTACHMENTS -->
<div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-3">
    <div class="flex items-center justify-between">
        <div class="text-sm font-semibold text-slate-800">Attachments</div>
        <div class="text-[11px] text-slate-500">
            {{ paymentVouchers.length + itemReceipts.length }} file(s)
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-2">
        <div class="flex items-center justify-between">
            <div class="text-xs font-semibold text-slate-700 uppercase tracking-wide">Payment Vouchers</div>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
                {{ paymentVouchers.length }}
            </span>
        </div>

        <div class="text-xs text-slate-500">
            Ref: {{ fullClaim?.payment_ref_no || '-' }}
        </div>

        <ul class="space-y-1 text-sm">
            <li
                v-for="file in paymentVouchers"
                :key="`voucher-${file.id}`"
                class="rounded border border-slate-200 bg-slate-50 px-2 py-1"
            >
                <a
                    :href="`/storage/${file.file_path}`"
                    target="_blank"
                    class="text-indigo-600 hover:text-indigo-700 hover:underline break-all"
                >
                    {{ file.original_name }}
                </a>
            </li>

            <li v-if="!paymentVouchers.length" class="text-xs text-slate-400">
                <span v-if="fullClaim.status == 'paid' && !fullClaim.paid_by">Petty Cash</span>
                <span v-else>No payment vouchers uploaded</span>
            </li>
        </ul>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-3 space-y-2">
        <div class="flex items-center justify-between">
            <div class="text-xs font-semibold text-slate-700 uppercase tracking-wide">Expense Receipts</div>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">
                {{ itemReceipts.length }}
            </span>
        </div>

        <ul class="space-y-1 text-sm">
            <li
                v-for="file in itemReceipts"
                :key="`receipt-${file.id}`"
                class="rounded border border-slate-200 bg-slate-50 px-2 py-1"
            >
                <a
                    :href="`/storage/${file.file_path}`"
                    target="_blank"
                    class="text-indigo-600 hover:text-indigo-700 hover:underline break-all"
                >
                    {{ file.original_name }}
                </a>
                <div class="text-xs text-slate-500">
                    {{ file.item_title }}
                </div>
            </li>

            <li v-if="!itemReceipts.length" class="text-xs text-slate-400">
                No expense receipts uploaded
            </li>
        </ul>
    </div>
</div>

<div class="border rounded-lg p-3 space-y-2">
    <div class="text-sm font-semibold">Stage Remarks</div>
    <div
        v-for="row in stageRemarks"
        :key="row.key"
        class="text-xs border-b last:border-b-0 pb-2 last:pb-0"
    >
        <div class="font-medium text-gray-700">{{ row.label }}</div>
        <div class="text-gray-900">{{ row.value }}</div>
        <div class="text-[11px] text-gray-500">{{ row.meta }}</div>
    </div>
    <div v-if="!stageRemarks.length" class="text-xs text-gray-400">No stage remarks yet</div>
</div>

<!-- ACTIONS -->
<div v-if="canReview" class="space-y-3">
    <label class="text-sm font-medium">Approval Remark</label>

    <textarea
        v-model="approvalRemark"
        rows="4"
        class="w-full border rounded px-3 py-2 text-sm"
        placeholder="Approval / rejection remark"
    />

    <button
        class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
        @click="submitDecision(reviewFlowNextStatus[fullClaim.status])"
        :disabled="submitting"
    >
        {{ reviewFlowLabels[fullClaim.status] }}
    </button>

    <button
        class="w-full py-2 bg-red-600 text-white rounded disabled:opacity-40"
        @click="submitDecision(rejectAction)"
        :disabled="submitting"
    >
        {{ rejectActionLabel }}
    </button>
</div>

</div>
</div>
</div>
</div>
</template>

