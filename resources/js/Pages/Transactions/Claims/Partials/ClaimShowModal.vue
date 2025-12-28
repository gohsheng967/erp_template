<script setup>
import { ref, watch, computed, nextTick } from 'vue'
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
const paymentRef = ref('')
const voucherFile = ref(null)

/* =========================
   COMPUTED
========================= */

// Claim-level payment vouchers (attachments)
const paymentVouchers = computed(() =>
    fullClaim.value?.attachments?.filter(
        a => a.category === 'payment_voucher'
    ) ?? []
)

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
    paymentRef.value = ''
    voucherFile.value = null

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

        await loadClaim()
        emit('refresh')

    } catch (e) {
        console.error('Approval failed', e)
    } finally {
        submitting.value = false
    }
}

/* =========================
   MARK AS PAID (ATTACHMENT)
========================= */

async function markAsPaid() {
    if (!fullClaim.value) return

    submitting.value = true

    try {
        const fd = new FormData()
        fd.append('payment_ref', paymentRef.value)
        if (voucherFile.value) {
            fd.append('voucher', voucherFile.value)
        }

        await axios.post(
            route('claims.paid', fullClaim.value.uuid),
            fd
        )

        await loadClaim()
        emit('refresh')

    } catch (e) {
        console.error('Mark paid failed', e)
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
    v-if="fullClaim && company && !printing"
    :claim="fullClaim"
    :company="company"
    />

    <Teleport to="body">
    <ClaimShowA4
        v-if="fullClaim && company && printing"
        :claim="fullClaim"
        :company="company"
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

<!-- ACTIONS -->
<div v-if="fullClaim.status === 'submitted'" class="space-y-3">
    <label class="text-sm font-medium">Approval Remark</label>

    <textarea
        v-model="approvalRemark"
        rows="4"
        class="w-full border rounded px-3 py-2 text-sm"
        placeholder="Approval / rejection remark"
    />

    <button
        class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
        @click="submitDecision('approved')"
        :disabled="submitting"
    >
        Approve
    </button>

    <button
        class="w-full py-2 bg-red-600 text-white rounded disabled:opacity-40"
        @click="submitDecision('rejected')"
        :disabled="submitting"
    >
        Reject
    </button>
</div>

<div v-if="fullClaim.status === 'approved'" class="space-y-3">
    <label class="text-sm font-medium">Payment Reference</label>

    <input
        v-model="paymentRef"
        class="w-full border rounded px-3 py-2 text-sm"
        placeholder="Payment reference"
    />

    <input
        type="file"
        class="text-sm"
        @change="e => voucherFile = e.target.files[0]"
    />

    <button
        class="w-full py-2 bg-indigo-600 text-white rounded disabled:opacity-40"
        @click="markAsPaid"
        :disabled="submitting"
    >
        Mark as Paid
    </button>
</div>

<!-- ATTACHMENTS (ALWAYS VISIBLE) -->
<div class="pt-4 border-t space-y-4">

    <div class="text-sm font-semibold">
        Attachments
    </div>

    <!-- Payment Vouchers -->
    <div>
        <div class="text-xs font-medium text-gray-500 mb-1">
            Payment Reference No
        </div>
        <ul class="space-y-1 text-sm">
            <li>{{ fullClaim?.payment_ref_no }}</li>
        </ul>

        <div class="text-xs font-medium text-gray-500 mb-1">
            Payment Vouchers
        </div>

        <ul class="space-y-1 text-sm">
            <li
                v-for="file in paymentVouchers"
                :key="file.id"
            >
                <a
                    :href="`/storage/${file.file_path}`"
                    target="_blank"
                    class="text-indigo-600 hover:underline"
                >
                    {{ file.original_name }}
                </a>
            </li>

            <li
                v-if="!paymentVouchers.length"
                class="text-xs text-gray-400"
            >
                No payment voucher uploaded
            </li>
        </ul>
    </div>

    <hr>
    <!-- Item Receipts -->
    <div>
        <div class="text-xs font-medium text-gray-500 mb-1">
            Expense Receipts
        </div>

        <ul class="space-y-1 text-sm">
            <li
                v-for="file in itemReceipts"
                :key="file.id"
            >
                <a
                    :href="`/storage/${file.file_path}`"
                    target="_blank"
                    class="text-indigo-600 hover:underline"
                >
                    {{ file.original_name }}
                </a>
                <span class="text-xs text-gray-400 ml-1">
                    ({{ file.item_title }})
                </span>
            </li>

            <li
                v-if="!itemReceipts.length"
                class="text-xs text-gray-400"
            >
                No expense receipts uploaded
            </li>
        </ul>
    </div>

</div>

</div>
</div>
</div>
</div>
</template>

