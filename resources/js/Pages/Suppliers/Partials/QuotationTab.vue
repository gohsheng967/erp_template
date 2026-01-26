<script setup>
import { ref, inject } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'

import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'
import AddQuotationsModal from '@/Components/AddQuotationsModal.vue'

const toast = inject('toast', null)
const showTerms = ref(false)
const activeTerms = ref('')
function openTerms(q) {
  activeTerms.value = q.terms
  showTerms.value = true
}

const showAddToPR = ref(false)

/* ======================
   PROPS
====================== */
const props = defineProps({
  supplier: {
    type: Object,
    required: true,
  },
  quotations: {
    type: Object, // Laravel paginator
    required: true,
  },
})

/* ======================
   STATE
====================== */
const selected = ref([])
const activeQuotationId = ref(null)

/* upload (Pattern B) */
const uploadItems = ref([])
const uploading = ref(false)
const isDragging = ref(false)
const fileInput = ref(null)

/* delete modal */
const showDeleteConfirm = ref(false)
const deletingQuotation = ref(null)

/* ======================
   FILE HANDLING (Pattern B)
====================== */
function addFiles(fileList) {
  Array.from(fileList)
    .filter(f => f.type === 'application/pdf')
    .forEach(file => {
      uploadItems.value.push({
        file,
        amount: '',
        quotation_no: '',
        delivery_time: '',
        terms: '',
      })
    })
}

function onFileChange(e) {
  addFiles(e.target.files)
}

function onDrop(e) {
  e.preventDefault()
  isDragging.value = false
  addFiles(e.dataTransfer.files)
}

function removeUploadItem(index) {
  uploadItems.value.splice(index, 1)
}

/* ======================
   UPLOAD
====================== */
async function uploadQuotations() {
  if (!uploadItems.value.length) {
    toast?.value?.show('Please add at least one quotation', 'warning')
    return
  }

  for (const q of uploadItems.value) {
    if (q.amount === '' || Number(q.amount) <= 0) {
      toast?.value?.show('Amount must be greater than 0', 'warning')
      return
    }
  }

  uploading.value = true
  const form = new FormData()

  uploadItems.value.forEach((q, i) => {
    form.append(`quotations[${i}][file]`, q.file)
    form.append(`quotations[${i}][amount]`, q.amount)
    form.append(`quotations[${i}][quotation_no]`, q.quotation_no)
    form.append(`quotations[${i}][delivery_time]`, q.delivery_time)
    form.append(`quotations[${i}][terms]`, q.terms)
  })

  try {
    await axios.post(
      route('suppliers.purchase-quotations.upload', props.supplier.uuid),
      form
    )

    toast?.value?.show(
      `${uploadItems.value.length} quotation(s) uploaded`,
      'success'
    )

    uploadItems.value = []
    router.reload({ preserveScroll: true })
  } catch (err) {
    toast?.value?.show(
      err.response?.data?.message || 'Upload failed',
      'error'
    )
  } finally {
    uploading.value = false
  }
}

/* ======================
   DELETE (WITH MODAL)
====================== */
function requestDeleteQuotation(q) {
  if (q.pr_count > 0) return

  deletingQuotation.value = q
  showDeleteConfirm.value = true
}

async function confirmDeleteQuotation() {
  if (!deletingQuotation.value) return

  try {
    await axios.delete(
      route('suppliers.purchase-quotations.destroy', [
        props.supplier.uuid,
        deletingQuotation.value.id
      ])
    )

    toast?.value?.show('Quotation deleted', 'success')
    router.reload({ preserveScroll: true })
  } catch (err) {
    toast?.value?.show(
      err.response?.data?.message || 'Delete failed',
      'error'
    )
  } finally {
    showDeleteConfirm.value = false
    deletingQuotation.value = null
  }
}

function handleAddSuccess() {
  showAddToPR.value = false
  selected.value = []

  toast?.value?.show(
    'Quotation(s) added to Purchase Request',
    'success'
  )

  router.reload({ preserveScroll: true })
}

/* ======================
   LINKED PR WIDGET
====================== */
function toggleWidget(id) {
  activeQuotationId.value =
    activeQuotationId.value === id ? null : id
}

</script>

<template>
  <div class="space-y-6">

    <!-- =====================
         UPLOAD (Pattern B)
    ====================== -->
    <div
      class="border-2 border-dashed rounded-xl p-6 cursor-pointer"
      :class="isDragging
        ? 'border-indigo-500 bg-indigo-50'
        : 'border-gray-300 hover:border-indigo-400'"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop="onDrop"
      @click="fileInput.click()"
    >
      <div class="text-center space-y-2 pointer-events-none">
        <i class="mdi mdi-cloud-upload-outline text-3xl text-indigo-600"></i>
        <p class="text-sm font-medium">Upload quotation PDFs</p>
        <p class="text-xs text-gray-500">Drag & drop or click to browse</p>
      </div>

      <input
        ref="fileInput"
        type="file"
        accept="application/pdf"
        multiple
        class="hidden"
        @change="onFileChange"
      />
    </div>

    <!-- =====================
         UPLOAD ITEMS
    ====================== -->
    <div v-if="uploadItems.length" class="space-y-4">
      <div
        v-for="(q, index) in uploadItems"
        :key="index"
        class="border rounded-lg p-4 bg-blue-50"
      >
        <div class="flex justify-between items-center mb-3">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
              <i class="mdi mdi-file-pdf-box text-xl"></i>
            </div>
            <span class="text-sm font-medium truncate">
              {{ q.file.name }}
            </span>
          </div>

          <button
            @click="removeUploadItem(index)"
            class="text-gray-400 hover:text-red-500"
          >
            <i class="mdi mdi-close-circle-outline"></i>
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <input v-model="q.amount" type="number" step="0.01"
            placeholder="Amount *"
            class="border rounded-md px-2 py-1.5 text-sm" />

          <input v-model="q.quotation_no" type="text" 
            placeholder="Quotation No *"
            class="border rounded-md px-2 py-1.5 text-sm" />

          <input v-model="q.delivery_time" type="number"
            placeholder="Delivery"
            class="border rounded-md px-2 py-1.5 text-sm" />

          <textarea v-model="q.terms" rows="1"
            placeholder="Terms"
            class="border rounded-md px-2 py-1.5 text-sm resize-none"></textarea>
        </div>
      </div>

      <div class="flex justify-end">
        <button
          @click="uploadQuotations"
          :disabled="uploading"
          class="px-5 py-2 bg-indigo-600 text-white rounded-md disabled:opacity-40"
        >
          {{ uploading ? 'Uploading…' : 'Upload Quotations' }}
        </button>
      </div>
    </div>


    <div
      v-if="selected.length"
      class="flex justify-end mb-3"
    >
      <button
        @click="showAddToPR = true"
        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700"
      >
        Add to Purchase Request ({{ selected.length }})
      </button>
    </div>

    <!-- =====================
         QUOTATION LIST
    ====================== -->
    <div class="bg-white border rounded-md overflow-visible">
      <table class="w-full text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-3"></th>
            <th class="px-4 py-3 text-center">Quotation No</th>
            <th class="px-4 py-3 text-center">Amount</th>
            <th class="px-4 py-3 text-center">Delivery</th>
            <th class="px-4 py-3 text-center">Notes</th>
            <th class="px-4 py-3 text-center">PRs</th>
            <th class="px-4 py-3 text-center">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="q in quotations.data" :key="q.id" class="border-t">
            <td class="text-center">
              <input
                type="checkbox"
                :value="q.id"
                v-model="selected"
              />
            </td>

            <td class="text-center py-2">{{ q.quotation_no }}</td>
            <td class="text-center py-2">{{ q.amount }}</td>
            <td class="text-center py-2">{{ q.delivery_time }}</td>
            <td class="px-4 py-2 text-center">
              <button
                v-if="q.terms"
                @click="openTerms(q)"
                class="text-indigo-600 hover:text-indigo-800"
                title="View notes"
              >
                <i class="mdi mdi-note-text-outline text-lg"></i>
              </button>

              <span
                v-else
                class="text-gray-300"
                title="No notes"
              >
                <i class="mdi mdi-note-off-outline text-lg"></i>
              </span>
            </td>

            <td class="text-center relative py-2">
              <button
                v-if="q.pr_count > 0"
                @click="toggleWidget(q.id)"
                class="px-2 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs"
              >
                {{ q.pr_count }}
              </button>
              <span v-else class="text-gray-400">0</span>

              <div
                v-if="activeQuotationId === q.id"
                class="absolute right-0 mt-2 w-56 bg-white border rounded-lg shadow p-3 z-50"
              >
                <p class="text-xs font-semibold mb-2">Linked PRs</p>
                <ul class="space-y-1">
                  <li v-for="pr in q.purchase_requests" :key="pr.id">
                    <Link
                      :href="route('purchase-request.show', pr.id)"
                      target="_blank"
                      class="text-indigo-600 hover:underline text-sm"
                    >
                      {{ pr.code }}
                    </Link>
                  </li>
                </ul>
              </div>
            </td>

            <td class="text-center flex justify-center gap-3 py-2">
              <a
                v-if="q.attachment && q.attachment.url"
                :href="q.attachment.url"
                target="_blank"
                class="text-indigo-500 hover:text-indigo-700"
                title="View PDF"
              >
                <i class="mdi mdi-eye-outline"></i>
              </a>

              <button
                v-if="q.pr_count === 0"
                @click="requestDeleteQuotation(q)"
                class="text-red-500 hover:text-red-700"
                title="Delete quotation"
              >
                <i class="mdi mdi-delete-outline"></i>
              </button>

              <span v-else class="text-gray-300">
                <i class="mdi mdi-delete-outline"></i>
              </span>
            </td>
          </tr>

          <tr v-if="!quotations.data.length">
            <td colspan="6" class="text-center py-6 text-gray-400">
              No quotations uploaded
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- =====================
         PAGINATION
    ====================== -->
    <div
      v-if="quotations.links && quotations.links.length > 1"
      class="flex justify-end mt-4"
    >
      <nav class="flex gap-1">
        <button
          v-for="link in quotations.links"
          :key="link.label"
          v-html="link.label"
          :disabled="!link.url"
          @click="link.url && router.visit(link.url, { preserveScroll: true })"
          class="px-3 py-1 border rounded text-sm"
          :class="{
            'bg-indigo-600 text-white': link.active,
            'text-gray-400': !link.url
          }"
        />
      </nav>
    </div>

    <!-- =====================
         DELETE CONFIRMATION
    ====================== -->
    <DeleteConfirmation
      v-if="showDeleteConfirm"
      title="Delete Quotation"
      message="This quotation will be permanently deleted. This action cannot be undone."
      @confirm="confirmDeleteQuotation"
      @close="showDeleteConfirm = false"
    />

    <AddQuotationsModal
        v-if="showAddToPR"
        :quotation-ids="selected"
        @close="showAddToPR = false"
        @success="handleAddSuccess"
      />


  <!-- =====================
      TERMS MODAL
  ===================== -->
  <div
    v-if="showTerms"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
  >
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">

      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">
          Quotation Notes
        </h3>
        <button
          class="text-gray-400 hover:text-gray-600"
          @click="showTerms = false"
        >
          ✕
        </button>
      </div>

      <!-- Body -->
      <div class="px-6 py-6">
        <pre class="whitespace-pre-wrap text-sm text-gray-700">
          {{ activeTerms || '-' }}
        </pre>
      </div>

      <!-- Footer -->
      <div class="flex justify-end px-6 py-4 border-t bg-gray-50">
        <button
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
          @click="showTerms = false"
        >
          Close
        </button>
      </div>

    </div>
  </div>


  </div>
</template>
