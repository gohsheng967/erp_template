<script setup>
import { ref, computed, inject } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

/* =========================
   Props
========================= */
const props = defineProps({
  claim: Object,
  claimTypes: Object,
})

/* =========================
   Toast (REF-BASED)
========================= */
const toast = inject('toast', null)

/* =========================
   State
========================= */
const showSubmitConfirm = ref(false)
const todayDate = (() => {
  const now = new Date()
  const year = now.getFullYear()
  const month = String(now.getMonth() + 1).padStart(2, '0')
  const day = String(now.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
})()

/* =========================
   Form
========================= */
const form = useForm({
  items: (props.claim.items ?? []).map(item => ({
    id: item.id,
    title: item.title ?? '',
    description: item.description ?? '',
    receipt_no: item.receipt_no ?? '',
    receipt_date: item.receipt_date ?? '',
    claim_type: item.claim_type ?? '',
    amount: item.amount ?? 0,

    // new uploads
    attachment: [],

    // existing attachments
    existing_attachments: item.attachments ?? [],
    _existing_attachments: (item.attachments ?? []).map(a => a.id),

    _fileInput: null,
  })),
  total_amount: props.claim.total_amount ?? 0,
  status: 'draft',
})

/* =========================
   Computed
========================= */
const subtotal = computed(() =>
  form.items.reduce((sum, i) => sum + Number(i.amount || 0), 0)
)

/* =========================
   Attachment helpers
========================= */
function handleFiles(files, item) {
  if (!files?.length) return

  for (const file of files) {
    if (item.attachment.length + item._existing_attachments.length >= 3) {
      toast?.value?.show('Maximum 3 attachments allowed.', 'error')
      return
    }

    file._preview = URL.createObjectURL(file)
    item.attachment.push(file)
  }
}

function removeExistingAttachment(item, attachmentId) {
  item.existing_attachments =
    item.existing_attachments.filter(a => a.id !== attachmentId)

  item._existing_attachments =
    item._existing_attachments.filter(id => id !== attachmentId)
}

/* =========================
   Item actions
========================= */
function addItem() {
  form.items.push({
    title: '',
    description: '',
    receipt_no: '',
    receipt_date: '',
    claim_type: '',
    amount: 0,
    attachment: [],
    existing_attachments: [],
    _existing_attachments: [],
    _fileInput: null,
  })
}

function removeItem(index) {
  form.items.splice(index, 1)
}

/* =========================
   Form actions
========================= */
function submitClaim(status) {
  form.status = status

  form
    .transform((data) => ({
      ...data,
      items: (data.items ?? []).map(({ _fileInput, existing_attachments, ...item }) => item),
    }))
    .post(route('claims.update', props.claim.uuid), {
      preserveScroll: true,
      forceFormData: true,

      onSuccess: () => {
        toast?.value?.show(
          status === 'submitted'
            ? 'Claim submitted successfully.'
            : 'Draft saved successfully.',
          'success'
        )
      },

      onError: () => {
        const first = Object.values(form.errors)?.[0]
        const msg = Array.isArray(first) ? first[0] : first
        toast?.value?.show(
          msg || (status === 'submitted' ? 'Submission failed.' : 'Draft save failed.'),
          'error'
        )
      },
    })
}

function saveDraft() {
  submitClaim('draft')
}

function confirmSubmit() {
  showSubmitConfirm.value = false
  submitClaim('submitted')
}
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold text-gray-800">
        Edit Draft Claim
      </h2>
    </template>

    <div class="max-w-7xl mx-auto space-y-6">

      <button class="btn-outline" @click="addItem">
        + Add Expense Item
      </button>

      <!-- ITEMS -->
      <div
        v-for="(item, index) in form.items"
        :key="index"
        class="bg-white border rounded-lg p-6 shadow-sm space-y-4"
        :class="{
          'border-red-500': Object.keys(form.errors)
            .some(e => e.startsWith(`items.${index}.`))
        }"
      >
        <div class="flex justify-between items-center">
          <h3 class="font-semibold">Expense Item {{ index + 1 }}</h3>
          <button class="text-red-500 text-sm" @click="removeItem(index)">
            Remove
          </button>
        </div>

        <input v-model="item.title" class="input w-full" placeholder="Title" />
        <textarea v-model="item.description" class="input w-full" rows="2" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <input v-model="item.receipt_no" class="input" placeholder="Receipt No" />
          <input v-model="item.receipt_date" type="date" class="input" :max="todayDate" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <select v-model="item.claim_type" class="input">
            <option value="">Select Claim Type</option>
            <option v-for="(label, key) in claimTypes" :key="key" :value="key">
              {{ label }}
            </option>
          </select>

          <input
            v-model="item.amount"
            type="number"
            step="0.01"
            class="input text-right"
          />
        </div>

        <!-- ATTACHMENTS -->
        <div class="space-y-2">
          <label class="label">Attachments (max 3)</label>

          <!-- existing -->
          <div
            v-for="att in item.existing_attachments"
            :key="'old-' + att.id"
            class="flex justify-between items-center border p-2 rounded"
          >
            <a :href="att.url" target="_blank" class="text-blue-600 text-sm">
              {{ att.original_name }}
            </a>
            <button
              class="text-red-500 text-xs"
              @click="removeExistingAttachment(item, att.id)"
            >
              Remove
            </button>
          </div>

          <!-- new -->
          <div
            v-for="(file, i) in item.attachment"
            :key="'new-' + i"
            class="flex justify-between items-center border p-2 rounded bg-gray-50"
          >
            <span class="text-sm">{{ file.name }}</span>
            <button
              class="text-red-500 text-xs"
              @click="item.attachment.splice(i, 1)"
            >
              Remove
            </button>
          </div>

          <input
            type="file"
            multiple
            class="hidden"
            :ref="el => item._fileInput = el"
            @change="e => handleFiles(e.target.files, item)"
          />

          <button
            class="border border-dashed rounded p-2 text-sm text-gray-500"
            @click="item._fileInput.click()"
          >
            + Add attachment
          </button>
        </div>
      </div>

      <!-- TOTAL -->
      <div class="flex justify-end">
        <div class="w-80 bg-white p-4 rounded shadow">
          <div class="flex justify-between">
            <span>Total</span>
            <input v-model="form.total_amount" class="input w-32 text-right" />
          </div>
        </div>
      </div>

      <!-- ACTIONS -->
      <div class="flex justify-end gap-3">
        <Link :href="route('claims.index')" class="btn-secondary">Cancel</Link>
        <button class="btn-outline" @click="saveDraft">Save Draft</button>
        <button class="btn-primary" @click="showSubmitConfirm = true">
          Submit
        </button>
      </div>
    </div>

    <ConfirmModal
      :show="showSubmitConfirm"
      title="Submit Claim?"
      message="Once submitted, this claim can no longer be edited."
      confirm-text="Yes, Submit Claim"
      cancel-text="Cancel"
      :danger="true"
      @confirm="confirmSubmit"
      @cancel="showSubmitConfirm = false"
    />
  </AuthenticatedLayout>
</template>
