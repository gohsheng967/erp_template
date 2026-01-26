<script setup>
import { ref, computed, inject } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

/* =========================
   PROPS
========================= */
const props = defineProps({
  invoice: {
    type: Object,
    required: true,
  },
})

/* =========================
   TOAST
========================= */
const toast = inject('toast', null)

/* =========================
   STATE
========================= */
const showIssueConfirm = ref(false)

/* =========================
   FORM (DRAFT ONLY)
========================= */
const form = useForm({
  items: (props.invoice.items ?? []).map(item => ({
    id: item.id,
    title: item.title ?? '',
    description: item.description ?? '',
    quantity: item.quantity ?? 1,
    unit_price: item.unit_price ?? 0,
    amount: item.amount ?? 0,
  })),

  total_amount: props.invoice.total_amount ?? 0,
  payment_term_days: props.invoice.payment_term_days ?? '',
  status: 'draft',
})

/* =========================
   COMPUTED
========================= */
const subtotal = computed(() =>
  form.items.reduce((sum, i) => sum + Number(i.amount || 0), 0)
)

/* =========================
   HELPERS
========================= */
function recalcAmount(item) {
  item.amount =
    Number(item.quantity || 0) * Number(item.unit_price || 0)
}

function showFirstError(errors, fallback = 'Operation failed.') {
  const first = Object.values(errors)?.[0]
  const msg = Array.isArray(first) ? first[0] : first
  toast?.value?.show(msg || fallback, 'error')
}

/* =========================
   ITEM ACTIONS
========================= */
function addItem() {
  form.items.push({
    title: '',
    description: '',
    quantity: 1,
    unit_price: 0,
    amount: 0,
  })
}

function removeItem(index) {
  form.items.splice(index, 1)
}

/* =========================
   FORM ACTIONS
========================= */
function saveDraft() {
  form.status = 'draft'

  form.post(route('ar-invoices.update', props.invoice.uuid), {
    preserveScroll: true,
    forceFormData: true,

    onSuccess: () => {
      toast?.value?.show('Draft saved successfully.', 'success')
    },

    onError: errors => {
      showFirstError(errors, 'Failed to save draft.')
    },
  })
}

function confirmIssue() {

  showIssueConfirm.value = false
  form.status = 'issued'

  form.post(route('ar-invoices.update', props.invoice.uuid), {
    preserveScroll: true,
    forceFormData: true,
    
    onSuccess: () => {
      toast?.value?.show('Invoice issued successfully.', 'success')
    },

    onError: errors => {

      showFirstError(errors, 'Failed to issue invoice.')
    },
  })
}
</script>

<template>
<AuthenticatedLayout>
  <template #header>
    <h2 class="text-xl font-semibold text-gray-800">
      Edit Draft Invoice
    </h2>
  </template>

  <div class="max-w-7xl mx-auto space-y-6">

    <!-- ADD ITEM -->
    <button class="btn-outline" @click="addItem">
      + Add Invoice Item
    </button>

    <!-- ITEMS -->
    <div
      v-for="(item, index) in form.items"
      :key="index"
      class="bg-white border rounded-lg p-6 shadow-sm space-y-4"
    >
      <div class="flex justify-between items-center">
        <h3 class="font-semibold">
          Item {{ index + 1 }}
        </h3>

        <button
          class="text-red-500 text-sm"
          @click="removeItem(index)"
        >
          Remove
        </button>
      </div>

      <input
        v-model="item.title"
        class="input w-full"
        placeholder="Service / Product title"
      />

      <textarea
        v-model="item.description"
        class="input w-full"
        rows="2"
        placeholder="Optional description"
      />

      <div class="grid grid-cols-3 gap-4">
        <input
          v-model.number="item.quantity"
          type="number"
          min="0"
          step="0.01"
          class="input"
          placeholder="Qty"
          @input="recalcAmount(item)"
        />

        <input
          v-model.number="item.unit_price"
          type="number"
          min="0"
          step="0.01"
          class="input"
          placeholder="Unit price"
          @input="recalcAmount(item)"
        />

        <input
          :value="Number(item.amount || 0).toFixed(2)"
          class="input text-right bg-gray-100"
          readonly
        />
      </div>
    </div>

    <!-- TOTAL -->
    <div class="flex justify-end">
      <div class="w-80 bg-white p-4 rounded shadow space-y-3">
        <div class="flex justify-between items-center">
          <span class="font-medium">Total</span>
          <input
            v-model.number="form.total_amount"
            class="input w-32 text-right"
          />
        </div>

        <div class="flex justify-between items-center">
          <span class="font-medium">Payment Term (Days)</span>
          <input
            v-model.number="form.payment_term_days"
            type="number"
            min="0"
            step="1"
            class="input w-32 text-right"
          />
        </div>

        <div
          class="text-xs text-right mt-1"
          :class="{ 'text-red-600': subtotal !== Number(form.total_amount) }"
        >
          Items total: {{ subtotal.toFixed(2) }}
        </div>
      </div>
    </div>

    <!-- ACTIONS -->
    <div class="flex justify-end gap-3">
      <Link
        :href="route('ar-invoices.index')"
        class="btn-secondary"
      >
        Cancel
      </Link>

      <button
        class="btn-outline"
        @click="saveDraft"
        :disabled="form.processing"
      >
        Save Draft
      </button>

      <button
        class="btn-primary"
        @click="showIssueConfirm = true"
        :disabled="form.processing"
      >
        Issue Invoice
      </button>
    </div>
  </div>

  <!-- CONFIRM ISSUE -->
  <ConfirmModal
    :show="showIssueConfirm"
    title="Issue Invoice?"
    message="Once issued, this invoice can no longer be edited."
    confirm-text="Yes, Issue Invoice"
    cancel-text="Cancel"
    :danger="true"
    @confirm="confirmIssue"
    @cancel="showIssueConfirm = false"
  />
</AuthenticatedLayout>
</template>
