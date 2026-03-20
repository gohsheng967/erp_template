<script setup>
import { ref, watch, nextTick, computed, inject } from 'vue'
import axios from 'axios'
import PurchaseRequestShowA4 from './PurchaseRequestShowA4.vue'
import { useFormat } from '@/Composables/useFormat'

const { capitalize, formatCurrency, formatDate } = useFormat()
const toast = inject('toast', null)

const props = defineProps({
  request: Object
})

const emit = defineEmits(['close', 'refresh'])

const loading = ref(false)
const submitting = ref(false)
const printing = ref(false)

const fullRequest = ref(null)
const companyProfile = ref(null)

const approvalRemark = ref('')
const selectedQuotationId = ref(null)

const canSelectQuotation = computed(() => {
  return fullRequest.value?.status === 'verified_purchasing_department'
})
const isPendingPurchasingVerifyStage = computed(() => {
  if (!fullRequest.value) return false

  return (
    fullRequest.value.status === 'verified_project_department' ||
    (fullRequest.value.status === 'verified_own_department' && !fullRequest.value.project_id)
  )
})
const pendingPurchasingVerifyNote = computed(() => {
  if (fullRequest.value?.is_subcon_purchase_request) {
    return 'Fill Terms & Condition and Site Contact Person in Edit form, then verify to proceed to CEO / GM approve stage.'
  }

  return 'Fill Delivery Period, Terms & Condition, and Site Contact Person in Edit form, then verify to proceed to CEO / GM approve stage.'
})

function displayStatusLabel(status, isProjectLinked = false) {
  switch (status) {
    case 'draft':
      return 'Draft'
    case 'submitted':
      return 'Pending Check'
    case 'verified_own_department':
      return isProjectLinked
        ? 'Pending Project Dept Verify'
        : 'Pending Purchasing Dept Verify'
    case 'verified_project_department':
      return 'Pending Purchasing Dept Verify'
    case 'verified_purchasing_department':
      return 'Pending CEO / GM Approve'
    case 'po':
      return 'PO'
    case 'payment':
      return 'Payment'
    case 'rejected':
      return 'Rejected'
    default:
      return String(status ?? '-').replaceAll('_', ' ')
  }
}

const currentStatusLabel = computed(() => {
  return displayStatusLabel(
    fullRequest.value?.status,
    !!fullRequest.value?.project_id
  )
})

const stageRemarks = computed(() => {
  const logs = fullRequest.value?.remark_log ?? []
  if (!Array.isArray(logs)) return []

  return logs
    .map((row, index) => ({
      key: `${index}-${row?.at ?? ''}`,
      label: `${displayStatusLabel(row?.from, !!fullRequest.value?.project_id)} -> ${displayStatusLabel(row?.to, !!fullRequest.value?.project_id)}`,
      value: row?.remark || '-',
      meta: `${row?.user_name ?? 'System'} - ${row?.at ?? '-'}`,
    }))
    .reverse()
})

watch(
  () => fullRequest.value,
  (val) => {
    if (
      val?.approved_quotation_id
    ) {
      selectedQuotationId.value = val.approved_quotation_id
    }
  },
  { immediate: true }
)

/* =====================
   LOAD PR (ALWAYS FRESH)
===================== */
async function loadRequest() {
  if (!props.request?.uuid) return

  loading.value = true
  fullRequest.value = null
  companyProfile.value = null
  selectedQuotationId.value = null

  approvalRemark.value = ''

  try {
    const res = await axios.get(
      route('purchase-request.show', props.request.uuid)
    )
    fullRequest.value = res.data.request
    companyProfile.value = res.data.company
    fullRequest.value.remark_signers = res.data.remark_signers ?? {}

    if (
      fullRequest.value.approved_quotation_id
    ) {
      selectedQuotationId.value =
        fullRequest.value.approved_quotation_id
    }

  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

watch(
  () => props.request.uuid,
  loadRequest,
  { immediate: true }
)

/* =====================
   APPROVAL
===================== */
async function submitDecision(status) {
  if (!fullRequest.value) return

  // ✅ Enforce selection only for approve
  if ((status === 'create_po' || status === 'approved') && !selectedQuotationId.value) {
    toast?.show?.('Please select a quotation first', 'error')
    return
  }

  submitting.value = true

  try {
    const response = await axios.post(
      route('purchase-request.approval', fullRequest.value.uuid),
      {
        status,
        remark: approvalRemark.value,
        quotation_id: selectedQuotationId.value,
      }
    )

    const successMessage = {
      verify: 'Purchase Request verified',
      draft: 'Purchase Request returned to draft',
      approved: 'Purchase Request CEO approved and PO created',
      rejected: 'Purchase Request rejected',
      create_po: 'Purchase Order created',
    }[status] ?? 'Action successful'

    toast?.show?.(response?.data?.message ?? successMessage, 'success')

    emit('refresh')
    closeModal()
  } catch (e) {
    console.error(e)

    toast?.show?.(
      e?.response?.data?.message ?? 'Action failed',
      'error'
    )
  } finally {
    submitting.value = false
  }
}


/* =====================
   PRINT
===================== */
function printPage() {
  printing.value = true

  nextTick(() => {
    requestAnimationFrame(() => {
      window.print()
      printing.value = false
    })
  })
}

function closeModal() {
  emit('close')
}
</script>
<template>
<div class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print">
  <div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">

    <!-- HEADER -->
    <div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
      <h2 class="font-semibold text-lg">
        Purchase Request — {{ fullRequest?.code }}
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

      <!-- LEFT: A4 -->
      <div class="flex-1 overflow-auto">
        <div v-if="loading" class="py-24 text-center text-gray-400">
          Loading purchase request…
        </div>

        <PurchaseRequestShowA4
          v-if="fullRequest && !printing"
          :request="fullRequest"
          :company="companyProfile"
        />

        <Teleport to="body">
          <PurchaseRequestShowA4
            v-if="fullRequest && printing"
            :request="fullRequest"
            :company="companyProfile"
          />
        </Teleport>
      </div>

      <!-- RIGHT: SIDEBAR -->
      <div
        v-if="fullRequest"
        class="w-[360px] bg-white border rounded-lg p-4 space-y-6 overflow-auto"
      >
        <!-- STATUS -->
        <div>
          <div class="text-xs text-gray-500">Status</div>
          <div class="font-semibold">
            {{ currentStatusLabel }}
          </div>
        </div>

        <!-- QUOTATIONS -->
        <div class="space-y-2">
          <div class="text-sm font-medium text-gray-700">
            Quotations
          </div>

          <div class="space-y-2">
            <div
              v-for="q in fullRequest.quotations ?? []"
              :key="q.id"
              @click="canSelectQuotation && (selectedQuotationId = q.id)"
              class="relative cursor-pointer border rounded-md p-3 flex items-start gap-3 transition"
              :class="{
                'border-indigo-600 bg-indigo-50': selectedQuotationId === q.id,
                'border-gray-200 hover:border-gray-400': selectedQuotationId !== q.id
              }"
            >
              <!-- CHECK ICON -->
              <i
                v-if="selectedQuotationId === q.id"
                class="mdi mdi-check-circle text-indigo-600 absolute top-0 right-2"
              ></i>

              <!-- FILE ICON -->
              <i class="mdi mdi-file-document-outline text-indigo-600 mt-0.5"></i>

              <!-- CONTENT -->
              <div class="flex-1">
                <div class="flex items-center justify-between">
                  <a
                    :href="q.attachment.url"
                    target="_blank"
                    class="font-medium text-indigo-600 hover:underline"
                    @click.stop
                  >
                    {{ q.quotation_no  }}
                  </a>

                  <!-- VIEW BUTTON -->
                  <a
                    :href="q.attachment.url"
                    target="_blank"
                    @click.stop
                    class="text-xs px-2 py-0.5 border rounded text-indigo-600
                          hover:bg-indigo-50 flex items-center gap-1"
                  >
                    <i class="mdi mdi-eye-outline"></i>
                    View
                  </a>
                </div>

                <div class="text-xs text-gray-500 mt-0.5">
                  {{ formatCurrency(q.amount) }}
                </div>
              </div>

            </div>

            <div
              v-if="!fullRequest.quotations?.length"
              class="text-xs text-gray-400"
            >
              No quotations attached
            </div>
          </div>
        </div>

        <hr class="my-4">

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
        <div
          v-if="[
            'submitted',
            'verified_own_department',
            'verified_project_department',
            'verified_purchasing_department'
          ].includes(fullRequest.status)"
          class="space-y-3"
        >
          <template v-if="!isPendingPurchasingVerifyStage">
            <label class="text-sm font-medium">Stage Remark</label>

            <textarea
              v-model="approvalRemark"
              rows="4"
              class="w-full border rounded px-3 py-2 text-sm"
              placeholder="Stage remark (optional)"
            />
          </template>

          <button
            v-if="fullRequest.status === 'submitted'"
            class="w-full py-2 bg-blue-600 text-white rounded"
            :disabled="submitting"
            @click="submitDecision('verify')"
          >
            Verify & Next Stage
          </button>

          <button
            v-if="fullRequest.status === 'verified_own_department' && !!fullRequest.project_id"
            class="w-full py-2 bg-blue-600 text-white rounded"
            :disabled="submitting"
            @click="submitDecision('verify')"
          >
            Verify & Next Stage
          </button>

          <a
            v-if="isPendingPurchasingVerifyStage"
            :href="route('purchase-request.edit', fullRequest.uuid)"
            class="block w-full py-2 bg-blue-600 text-white text-center rounded"
          >
            Open Edit Form to Verify
          </a>

          <div
            v-if="isPendingPurchasingVerifyStage"
            class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700"
          >
            {{ pendingPurchasingVerifyNote }}
          </div>

          <template v-if="fullRequest.status === 'verified_purchasing_department'">
            <div class="rounded border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 space-y-1">
              <div v-if="!fullRequest.is_subcon_purchase_request"><span class="font-semibold">Delivery Period:</span> {{ fullRequest.delivery_period || '-' }}</div>
              <div><span class="font-semibold">Terms & Condition:</span> {{ fullRequest.payment_terms || '-' }}</div>
              <div><span class="font-semibold">Site Contact:</span> {{ fullRequest.site_contact?.name || '-' }}</div>
            </div>

            <button
              class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
              :disabled="submitting || !selectedQuotationId"
              @click="submitDecision('approved')"
            >
              CEO Approve & Create PO
            </button>
          </template>


          <button
            v-if="fullRequest.status !== 'po'"
            class="w-full py-2 bg-red-600 text-white rounded"
            :disabled="submitting"
            @click="submitDecision(['submitted', 'verified_own_department'].includes(fullRequest.status) ? 'draft' : 'rejected')"
          >
            {{ ['submitted', 'verified_own_department'].includes(fullRequest.status) ? 'Return to Draft' : 'Reject' }}
          </button>
        </div>
      </div>

    </div>
  </div>
</div>
</template>
