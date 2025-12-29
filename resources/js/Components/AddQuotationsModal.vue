<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

/* ======================
   PROPS / EMITS
====================== */
const props = defineProps({
  quotationIds: {
    type: Array,
    required: true,
  },
})

const emit = defineEmits(['close', 'success'])

/* ======================
   STATE
====================== */
const mode = ref('existing') // existing | new
const submitting = ref(false)
const loading = ref(false)

/* --- init data --- */
const departments = ref([])
const projects = ref([])
const purchaseRequests = ref([])

/* --- search --- */
const searchPR = ref('')

/* --- existing PR --- */
const selectedPR = ref('')

/* --- new PR --- */
const newPR = ref({
  title: '',
  purpose: '',
  department_id: '',
  project_id: null,
  required_date: ''
})

/* ======================
   INIT FORM (ONE API)
====================== */
async function initForm() {
  loading.value = true

  try {
    const { data } = await axios.get(
      route('purchase-request.init-form'),
      {
        params: { search: searchPR.value },
      }
    )

    departments.value = data.departments
    purchaseRequests.value = data.purchase_requests
    projects.value = data.projects
  } finally {
    loading.value = false
  }
}

/* ======================
   LIFECYCLE
====================== */
onMounted(initForm)

/* ======================
   SEARCH (DEBOUNCE)
====================== */
let timer = null
watch(searchPR, () => {
  clearTimeout(timer)
  timer = setTimeout(initForm, 300)
})

/* ======================
   COMPUTED
====================== */
const canSubmit = computed(() => {
  if (mode.value === 'existing') {
    return !!selectedPR.value
  }

  return (
    newPR.value.title.trim() &&
    newPR.value.department_id
  )
})

/* ======================
   SUBMIT
====================== */
function submit() {
  if (!canSubmit.value) return

  submitting.value = true

  router.post(
    route('purchase-request.add-quote'),
    {
      purchase_request_id:
        mode.value === 'existing'
          ? selectedPR.value
          : null,

      create_new: mode.value === 'new',

      new_pr:
        mode.value === 'new'
          ? {
              title: newPR.value.title,
              required_date: newPR.required_date,
              purpose: newPR.value.purpose,
              department_id: newPR.value.department_id,
              project_id: newPR.value.project_id,
            }
          : null,

      quotation_ids: props.quotationIds,
    },
    {
      preserveScroll: true,
      onSuccess: () => emit('success'),
      onFinish: () => (submitting.value = false),
    }
  )
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-xl">

      <!-- =====================
           HEADER
      ====================== -->
      <div class="flex items-center justify-between px-6 py-4 border-b">
        <h3 class="text-lg font-semibold text-gray-800">
          Add to Purchase Request
        </h3>
        <button
          class="text-gray-400 hover:text-gray-600"
          @click="emit('close')"
        >
          ✕
        </button>
      </div>

      <!-- =====================
           BODY
      ====================== -->
      <div class="px-6 py-5 space-y-5">

        <!-- MODE SWITCH -->
        <div class="flex gap-4">
          <label class="flex items-center gap-2 text-sm">
            <input type="radio" value="existing" v-model="mode" />
            Existing PR
          </label>

          <label class="flex items-center gap-2 text-sm">
            <input type="radio" value="new" v-model="mode" />
            Create New PR
          </label>
        </div>

        <!-- =====================
             EXISTING PR
        ====================== -->
        <div v-if="mode === 'existing'">
          <input
            v-model="searchPR"
            placeholder="Search PR code / title"
            class="w-full border rounded-md px-3 py-2 text-sm mb-2"
          />

          <select
            v-model="selectedPR"
            class="w-full border rounded-md px-3 py-2 text-sm"
          >
            <option disabled value="">
              {{ loading ? 'Loading...' : '-- Select Purchase Request --' }}
            </option>

            <option
              v-for="pr in purchaseRequests"
              :key="pr.id"
              :value="pr.id"
            >
              {{ pr.code }} – {{ pr.title }}
            </option>
          </select>
        </div>

        <!-- =====================
             CREATE NEW PR
        ====================== -->
        <div v-else class="space-y-4">

          <!-- Title -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              PR Title *
            </label>
            <input
              v-model="newPR.title"
              type="text"
              class="mt-1 w-full border rounded-md px-3 py-2 text-sm"
              placeholder="e.g. Office Equipment Purchase"
            />
          </div>

          <!-- Purpose -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Purpose *
            </label>
            <textarea
              v-model="newPR.purpose"
              rows="2"
              class="mt-1 w-full border rounded-md px-3 py-2 text-sm"
              placeholder="Training"
            />
          </div>

          <!-- date -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Required Date *
            </label>
            <input
              v-model="newPR.required_date"
              type="date"
              class="mt-1 w-full border rounded-md px-3 py-2 text-sm"
            />
          </div>

          <!-- Department -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Department *
            </label>
            <select
              v-model="newPR.department_id"
              class="mt-1 w-full border rounded-md px-3 py-2 text-sm"
            >
              <option disabled value="">
                -- Select Department --
              </option>
              <option
                v-for="d in departments"
                :key="d.id"
                :value="d.id"
              >
                {{ d.name }}
              </option>
            </select>
          </div>

          <!-- Project (Optional) -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Project
            </label>
            <select
              v-model="newPR.project_id"
              class="mt-1 w-full border rounded-md px-3 py-2 text-sm"
            >
              <option :value="null">
                -- No Project (Department Purchase) --
              </option>
              <option
                v-for="p in projects"
                :key="p.id"
                :value="p.id"
              >
                {{ p.code }} – {{ p.name }}
              </option>
            </select>
          </div>


        </div>

        <!-- INFO -->
        <p class="text-xs text-gray-500">
          {{ quotationIds.length }} quotation(s) will be linked
        </p>
      </div>

      <!-- =====================
           FOOTER
      ====================== -->
      <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50">
        <button
          @click="emit('close')"
          class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
        >
          Cancel
        </button>

        <button
          :disabled="!canSubmit || submitting"
          @click="submit"
          class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-40"
        >
          {{ submitting ? 'Processing…' : 'Confirm' }}
        </button>
      </div>

    </div>
  </div>
</template>
