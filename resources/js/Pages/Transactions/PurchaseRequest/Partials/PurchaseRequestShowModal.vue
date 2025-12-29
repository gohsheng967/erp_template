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
  if (status === 'approved' && !selectedQuotationId.value) {
    toast?.show?.('Please select a quotation before approving', 'error')
    return
  }

  submitting.value = true

  try {
    await axios.post(
      route('purchase-request.approval', fullRequest.value.uuid),
      {
        status,
        remark: approvalRemark.value,
        quotation_id:
          status === 'approved'
            ? selectedQuotationId.value
            : null,
      }
    )

    toast?.show?.(
      status === 'approved'
        ? 'Purchase Request approved'
        : 'Purchase Request rejected',
      'success'
    )

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
          <div class="font-semibold uppercase">
            {{ fullRequest.status }}
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
              @click="selectedQuotationId = q.id"
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

        <!-- ACTIONS -->
        <div v-if="fullRequest.status === 'submitted'" class="space-y-3">
          <label class="text-sm font-medium">Approval Remark</label>

          <textarea
            v-model="approvalRemark"
            rows="4"
            class="w-full border rounded px-3 py-2 text-sm"
          />

          <button
            class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
            :disabled="submitting || !selectedQuotationId"
            @click="submitDecision('approved')"
          >
            Approve
          </button>


          <button
            class="w-full py-2 bg-red-600 text-white rounded"
            :disabled="submitting"
            @click="submitDecision('rejected')"
          >
            Reject
          </button>
        </div>
      </div>

    </div>
  </div>
</div>
</template>
