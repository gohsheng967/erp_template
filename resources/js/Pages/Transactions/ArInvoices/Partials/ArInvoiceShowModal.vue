<script setup>
import { ref, watch, computed, nextTick, inject } from 'vue'
import axios from 'axios'
import ArInvoiceShowA4 from './ArInvoiceShowA4.vue'
import { useFormat } from '@/Composables/useFormat'

/* =========================
   TOAST
========================= */
const toast = inject('toast', null)
const { formatCurrency, formatDate } = useFormat()

/* =========================
   PROPS / EMITS
========================= */
const printing = ref(false)

const props = defineProps({
  invoice: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['close', 'refresh'])

/* =========================
   STATE
========================= */
const loading = ref(false)
const submitting = ref(false)

const fullInvoice = ref(null)
const company = ref(null)
const approvalRemark = ref('')

/* =========================
   RECEIPTS (NEW ENTRIES)
========================= */
const receipts = ref([
  { amount: '', reference: '', file: null },
])

/* =========================
   COMPUTED
========================= */
const receiptHistory = computed(() =>
  fullInvoice.value?.receipts ?? []
)

const historyReceived = computed(() =>
  (fullInvoice.value?.receipts ?? []).reduce(
    (sum, r) => sum + Number(r.amount || 0),
    0
  )
)

const totalReceived = computed(() =>
  receipts.value.reduce(
    (sum, r) => sum + Number(r.amount || 0),
    0
  )
)

const outstanding = computed(() =>
  Math.max(
    Number(fullInvoice.value?.total_amount || 0)
      - historyReceived.value
      - totalReceived.value,
    0
  )
)

const dueStatus = computed(() => {
  if (!fullInvoice.value?.due_date) return 'none'
  const dueDate = new Date(fullInvoice.value.due_date)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const inFiveDays = new Date(today)
  inFiveDays.setDate(today.getDate() + 5)

  if (dueDate < today) return 'overdue'
  if (dueDate <= inFiveDays) return 'due_soon'
  return 'on_track'
})


/* =========================
   LOAD INVOICE
========================= */
async function loadInvoice() {
  if (!props.invoice?.uuid) return

  loading.value = true
  approvalRemark.value = ''
  receipts.value = [{ amount: '', reference: '', file: null }]

  try {
    const res = await axios.get(
      route('ar-invoices.show', props.invoice.uuid)
    )

    fullInvoice.value = res.data.invoice
    company.value = res.data.company

  } catch {
    toast?.value?.show('Failed to load invoice.', 'error')
  } finally {
    loading.value = false
  }
}

watch(
  () => props.invoice.uuid,
  () => loadInvoice(),
  { immediate: true }
)

/* =========================
   APPROVAL
========================= */
async function submitDecision(status) {
  if (!fullInvoice.value) return

  submitting.value = true

  try {
    await axios.post(
      route('ar-invoices.approval', fullInvoice.value.uuid),
      { status, remark: approvalRemark.value }
    )

    toast?.value?.show(
      status === 'approved'
        ? 'Invoice approved.'
        : 'Invoice rejected.',
      'success'
    )

    await loadInvoice()
    emit('refresh')

  } catch (e) {
    toast?.value?.show(
      e.response?.data?.message || 'Approval failed.',
      'error'
    )
  } finally {
    submitting.value = false
  }
}

/* =========================
   RECEIPT HELPERS
========================= */
function addReceipt() {
  receipts.value.push({ amount: '', reference: '', file: null })
}

function removeReceipt(index) {
  receipts.value.splice(index, 1)
}

/* =========================
   MARK AS RECEIVED
========================= */
async function markAsReceived() {
  const validReceipts = receipts.value.filter(
    r => Number(r.amount) > 0
  )

  if (!validReceipts.length) {
    toast?.value?.show(
      'Please enter at least one valid receipt amount.',
      'error'
    )
    return
  }

  submitting.value = true

  try {
    const fd = new FormData()

    validReceipts.forEach((r, i) => {
      fd.append(`receipts[${i}][amount]`, Number(r.amount))
      fd.append(`receipts[${i}][reference]`, r.reference || '')
      if (r.file) {
        fd.append(`receipts[${i}][file]`, r.file)
      }
    })

    await axios.post(
      route('ar-invoices.received', fullInvoice.value.uuid),
      fd,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    )

    toast?.value?.show('Receipt(s) saved successfully.', 'success')

    await loadInvoice()
    emit('refresh')

  } catch (e) {
    toast?.value?.show(
      e.response?.data?.message || 'Failed to save receipts.',
      'error'
    )
  } finally {
    submitting.value = false
  }
}

/* =========================
   CLOSE / PRINT
========================= */
function closeModal() {
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
<div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
  <h2 class="font-semibold text-lg">
    Invoice Preview — {{ fullInvoice?.invoice_no }}
  </h2>

  <div class="ml-auto flex items-center gap-3">
    <button @click="printPage">Print / Save PDF</button>
    <button @click="closeModal" class="text-red-600">
      <i class="mdi mdi-close text-xl"></i>
    </button>
  </div>
</div>

<!-- BODY -->
<div class="flex h-[calc(100%-56px)] gap-6 p-6">

<!-- LEFT -->
<div class="flex-1 overflow-auto">
  <div v-if="loading" class="text-center py-24 text-gray-400">
    Loading invoice…
  </div>

  <ArInvoiceShowA4
    v-if="fullInvoice && company && !printing"
    :invoice="fullInvoice"
    :company="company"
  />

  <Teleport to="body">
    <ArInvoiceShowA4
      v-if="fullInvoice && company && printing"
      :invoice="fullInvoice"
      :company="company"
    />
  </Teleport>
</div>

<!-- RIGHT SIDEBAR -->
<div
  v-if="fullInvoice"
  class="w-[380px] bg-white border rounded-lg p-4 space-y-6 overflow-auto"
>

<!-- STATUS -->
<div>
  <div class="text-xs text-gray-500">Status</div>
  <div class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold uppercase"
       :class="{
         'bg-gray-100 text-gray-600': fullInvoice.status === 'draft',
         'bg-blue-100 text-blue-700': fullInvoice.status === 'issued',
         'bg-amber-100 text-amber-700': fullInvoice.status === 'approved',
         'bg-emerald-100 text-emerald-700': fullInvoice.status === 'received',
         'bg-red-100 text-red-700': fullInvoice.status === 'rejected',
         'bg-slate-100 text-slate-700': fullInvoice.status === 'cancelled'
       }"
  >
    {{ fullInvoice.status }}
  </div>
</div>

<!-- SUMMARY -->
<div class="rounded-lg border bg-gray-50 p-3 space-y-2">
  <div class="flex items-center justify-between text-xs text-gray-500">
    <span>Invoice Summary</span>
    <span
      v-if="fullInvoice.due_date"
      class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
      :class="{
        'bg-red-100 text-red-700': dueStatus === 'overdue',
        'bg-amber-100 text-amber-700': dueStatus === 'due_soon',
        'bg-emerald-100 text-emerald-700': dueStatus === 'on_track',
        'bg-gray-100 text-gray-600': dueStatus === 'none'
      }"
    >
      {{
        dueStatus === 'overdue'
          ? 'Overdue'
          : dueStatus === 'due_soon'
            ? 'Due Soon'
            : dueStatus === 'on_track'
              ? 'On Track'
              : 'No Due Date'
      }}
    </span>
  </div>

  <div class="grid grid-cols-2 gap-2 text-sm">
    <div class="rounded bg-white p-2 border">
      <div class="text-xs text-gray-500">Total</div>
      <div class="font-semibold text-gray-800">
        {{ formatCurrency(fullInvoice.total_amount) }}
      </div>
    </div>
    <div class="rounded bg-white p-2 border">
      <div class="text-xs text-gray-500">Received</div>
      <div class="font-semibold text-emerald-700">
        {{ formatCurrency(historyReceived) }}
      </div>
    </div>
    <div class="rounded bg-white p-2 border col-span-2">
      <div class="text-xs text-gray-500">Outstanding</div>
      <div class="font-semibold text-red-700">
        {{ formatCurrency(outstanding) }}
      </div>
    </div>
  </div>

  <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
    <div class="rounded bg-white p-2 border">
      <div class="text-gray-500">Payment Term</div>
      <div class="font-medium text-gray-800">
        {{ fullInvoice.payment_term_days ?? '-' }}<span v-if="fullInvoice.payment_term_days !== null && fullInvoice.payment_term_days !== undefined"> days</span>
      </div>
    </div>
    <div class="rounded bg-white p-2 border">
      <div class="text-gray-500">Due Date</div>
      <div class="font-medium text-gray-800">
        {{ formatDate(fullInvoice.due_date) ?? '-' }}
      </div>
    </div>
  </div>
</div>

<!-- APPROVAL -->
<div v-if="fullInvoice.status === 'issued'" class="space-y-3">
  <textarea
    v-model="approvalRemark"
    rows="4"
    class="w-full border rounded px-3 py-2 text-sm"
    placeholder="Approval / rejection remark"
  />

  <button
    class="w-full py-2 bg-green-600 text-white rounded"
    @click="submitDecision('approved')"
    :disabled="submitting"
  >
    Approve
  </button>

  <button
    class="w-full py-2 bg-red-600 text-white rounded"
    @click="submitDecision('rejected')"
    :disabled="submitting"
  >
    Reject
  </button>
</div>

<!-- RECEIVE PAYMENT -->
<div v-if="fullInvoice.status === 'approved'" class="space-y-4">

  <div class="font-semibold text-sm">Receive Payment</div>

  <div
    v-for="(r, index) in receipts"
    :key="index"
    class="border rounded p-3 space-y-2"
  >
    <input
      v-model="r.amount"
      type="number"
      step="0.01"
      class="w-full border rounded px-3 py-2 text-sm"
      placeholder="Amount received"
    />

    <input
      v-model="r.reference"
      class="w-full border rounded px-3 py-2 text-sm"
      placeholder="Receipt / bank reference"
    />

    <input
      type="file"
      class="text-sm"
      @change="e => r.file = e.target.files[0]"
    />

    <button
      v-if="receipts.length > 1"
      class="text-xs text-red-600"
      @click="removeReceipt(index)"
    >
      Remove
    </button>
  </div>

  <button
    class="w-full border border-dashed py-2 text-sm"
    @click="addReceipt"
  >
    + Add another receipt
  </button>

  <div class="text-sm">
    <div>Total received: <b>{{ totalReceived.toFixed(2) }}</b></div>
    <div class="text-gray-500">
      Outstanding: {{ outstanding.toFixed(2) }}
    </div>
  </div>

  <button
    class="w-full py-2 bg-emerald-600 text-white rounded disabled:opacity-40"
    :disabled="submitting || totalReceived <= 0"
    @click="markAsReceived"
  >
    Save Receipts
  </button>
</div>

<!-- RECEIPT HISTORY -->
<div v-if="receiptHistory.length" class="pt-4 border-t space-y-3">

  <div class="text-sm font-semibold">
    Payment History
  </div>

  <div
    v-for="r in receiptHistory"
    :key="r.id"
    class="border rounded p-3 text-sm bg-gray-50 space-y-1"
  >
    <div class="flex justify-between">
      <span class="font-medium">
        {{ Number(r.amount).toFixed(2) }}
      </span>
      <span class="text-xs text-gray-500">
        {{ new Date(r.received_at).toLocaleDateString() }}
      </span>
    </div>

    <div class="text-xs text-gray-600">
      Ref: {{ r.reference || '-' }}
    </div>

    <div v-if="r.attachment_path" class="text-xs">
      <a
        :href="`/storage/${r.attachment_path}`"
        target="_blank"
        class="text-indigo-600 hover:underline"
      >
        {{ r.attachment_name }}
      </a>
    </div>

    <div class="text-[11px] text-gray-400">
      Received by {{ r.receiver?.name ?? 'System' }}
    </div>
  </div>

</div>

</div>
</div>
</div>
</div>
</template>
