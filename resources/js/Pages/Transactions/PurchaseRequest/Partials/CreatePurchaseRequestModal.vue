<script setup>
import { ref, reactive, watch, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

/* =========================
   TOAST
========================= */
const toast = inject('toast', null)

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
})

const emit = defineEmits(['close', 'created'])

/* =========================
   STATE
========================= */
const loading = ref(false)
const errors = ref({})

const form = reactive({
    title: '',
    purpose: '',
    required_date: '',
    department_id: null,
    project_id: null,
    requester_remark: '',
})

/* =========================
   INIT FORM DATA
========================= */
const departments = ref([])
const projects = ref([])

async function loadInitData() {
    try {
        const res = await axios.get(
            route('purchase-request.init-form')
        )

        departments.value = res.data.departments ?? []
        projects.value = res.data.projects ?? []

    } catch (e) {
        console.error(e)
        toast?.value?.show(
            'Failed to load form data',
            'error'
        )
    }
}

/* =========================
   WATCH OPEN
========================= */
watch(
    () => props.show,
    (val) => {
        if (val) {
            resetForm()
            loadInitData()
        }
    }
)

/* =========================
   ACTIONS
========================= */
function submit() {
    if (loading.value) return

    loading.value = true
    errors.value = {}

    router.post(
        route('purchase-request.store'),
        form,
        {
            preserveScroll: true,

            onError: (e) => {
                errors.value = e

                toast?.value?.show(
                    'Failed to create Purchase Request',
                    'error'
                )
            },

            onSuccess: () => {
                toast?.value?.show(
                    'Purchase Request created',
                    'success'
                )

                emit('created')
                emit('close')
            },

            onFinish: () => {
                loading.value = false
            },
        }
    )
}

function close() {
    emit('close')
}

function resetForm() {
    form.title = ''
    form.purpose = ''
    form.department_id = null
    form.project_id = null
    form.requester_remark = ''
    errors.value = {}
}
</script>

<template>
<div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
>
<div class="bg-white w-full max-w-xl rounded-lg shadow-xl">

    <!-- HEADER -->
    <div class="px-6 py-4 border-b flex items-center">
        <h3 class="font-semibold text-lg">
            Create Purchase Request
        </h3>

        <button
            @click="close"
            class="ml-auto text-gray-400 hover:text-gray-600"
        >
            <i class="mdi mdi-close text-xl"></i>
        </button>
    </div>

    <!-- BODY -->
    <div class="px-6 py-5 space-y-4">

        <!-- TITLE -->
        <div>
            <label class="text-sm font-medium">
                Title <span class="text-red-500">*</span>
            </label>
            <input
                v-model="form.title"
                class="w-full border rounded px-3 py-2"
                placeholder="e.g. Laptop purchase"
            />
            <div v-if="errors.title" class="text-xs text-red-600 mt-1">
                {{ errors.title }}
            </div>
        </div>

        <!-- PURPOSE -->
        <div>
            <label class="text-sm font-medium">Purpose</label>
            <textarea
                v-model="form.purpose"
                rows="3"
                class="w-full border rounded px-3 py-2"
                placeholder="Reason for this purchase"
            />
            <div v-if="errors.purpose" class="text-xs text-red-600 mt-1">
                {{ errors.purpose }}
            </div>
        </div>

        <div>
            <label class="text-sm font-medium">
                Required Date <span class="text-red-500">*</span>
            </label>
            <input
                v-model="form.required_date"
                class="w-full border rounded px-3 py-2"
                type="date"
            />
            <div v-if="errors.required_date" class="text-xs text-red-600 mt-1">
                {{ errors.required_date }}
            </div>
        </div>


        <!-- DEPARTMENT -->
        <div>
            <label class="text-sm font-medium">Department</label>
            <select
                v-model="form.department_id"
                class="w-full border rounded px-3 py-2"
            >
                <option :value="null">Select department</option>
                <option
                    v-for="d in departments"
                    :key="d.id"
                    :value="d.id"
                >
                    {{ d.name }}
                </option>
            </select>
            <div v-if="errors.department_id" class="text-xs text-red-600 mt-1">
                {{ errors.department_id }}
            </div>
        </div>

        <!-- PROJECT -->
        <div>
            <label class="text-sm font-medium">Project</label>
            <select
                v-model="form.project_id"
                class="w-full border rounded px-3 py-2"
            >
                <option :value="null">No project</option>
                <option
                    v-for="p in projects"
                    :key="p.id"
                    :value="p.id"
                >
                    {{ p.name }}
                </option>
            </select>
            <div v-if="errors.project_id" class="text-xs text-red-600 mt-1">
                {{ errors.project_id }}
            </div>
        </div>

        <!-- REMARK -->
        <div>
            <label class="text-sm font-medium">Requester Remark</label>
            <textarea
                v-model="form.requester_remark"
                rows="2"
                class="w-full border rounded px-3 py-2"
                placeholder="Optional remark"
            />
            <div v-if="errors.requester_remark" class="text-xs text-red-600 mt-1">
                {{ errors.requester_remark }}
            </div>
        </div>

    </div>

    <!-- FOOTER -->
    <div class="px-6 py-4 border-t flex justify-end gap-3">
        <button
            class="px-4 py-2 rounded bg-gray-200"
            @click="close"
            :disabled="loading"
        >
            Cancel
        </button>

        <button
            class="px-4 py-2 rounded bg-indigo-600 text-white
                   disabled:opacity-40"
            @click="submit"
            :disabled="loading"
        >
            Create
        </button>
    </div>

</div>
</div>
</template>
