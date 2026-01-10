<script setup>
import { ref, watch, inject, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    vehicleUuid: {
        type: String,
        required: true,
    },
    mode: {
        type: String,
        default: 'create', // create | edit | renew
    },
    roadtax: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close', 'saved'])
const toast = inject('toast', null)

/* =========================
   FILE PREVIEW
========================= */
const filePreview = ref(null)

/* =========================
   FORM
========================= */
const form = useForm({
    start_date: '',
    expiry_date: '',
    document: null,
})

/* =========================
   WATCH EDIT / RENEW
========================= */
watch(
    () => [props.roadtax, props.mode],
    ([v, mode]) => {
        if (!v) {
            form.start_date = ''
            form.expiry_date = ''
            form.document = null
            filePreview.value = null
            return
        }

        if (mode === 'edit') {
            form.start_date  = normalizeDate(v.start_date)
            form.expiry_date = normalizeDate(v.expiry_date)
        } else if (mode === 'renew') {
            form.start_date = ''
            form.expiry_date = ''
        }

        form.document = null
        filePreview.value = null
    },
    { immediate: true }
)

/* =========================
   FILE HANDLERS
========================= */
function onFileChange(e) {
    const file = e.target.files[0]
    if (!file) return

    form.document = file
    filePreview.value = file.name
}

function removeFile() {
    form.document = null
    filePreview.value = null
}

/* =========================
   SUBMIT
========================= */
function submit() {
    const isEdit  = props.mode === 'edit'
    const isRenew = props.mode === 'renew'

    let url

    if (isEdit) {
        url = route('inventory.vehicles.roadtax.update', [
            props.vehicleUuid,
            props.roadtax.id,
        ])
    } else if (isRenew) {
        url = route('inventory.vehicles.roadtax.renew', props.vehicleUuid)
    } else {
        url = route('inventory.vehicles.roadtax.store', props.vehicleUuid)
    }

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onBefore: () => {
            if (isEdit) {
                form._method = 'PUT'
            }
        },
        onSuccess: () => {
            toast?.value?.show(
                isEdit
                    ? 'Roadtax updated'
                    : isRenew
                        ? 'Roadtax renewed'
                        : 'Roadtax added',
                'success'
            )
            emit('saved')
            emit('close')
        },
        onError: () => {
            toast?.value?.show(
                'Please correct the highlighted fields.',
                'error'
            )
        },
    })
}


/* =========================
   UI
========================= */
const title = computed(() => {
    if (props.mode === 'edit') return 'Edit Roadtax'
    if (props.mode === 'renew') return 'Renew Roadtax'
    return 'Add Roadtax'
})

const hasExistingDocument = computed(() => {
    return props.mode === 'edit' && !!props.roadtax?.attachment
})

const existingDocumentUrl = computed(() => {
    return props.roadtax?.attachment?.url ?? null
})

function normalizeDate(date) {
    if (!date) return ''
    return date.substring(0, 10)
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-xl">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold">{{ title }}</h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="px-6 py-4 space-y-4">

                <!-- PERIOD -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Start Date</label>
                        <input type="date" v-model="form.start_date" class="input w-full" />
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Expiry Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" v-model="form.expiry_date" class="input w-full" />
                        <div v-if="form.errors.expiry_date" class="text-xs text-red-500 mt-1">
                            {{ form.errors.expiry_date }}
                        </div>
                    </div>
                </div>

                <!-- DOCUMENT -->
                <div>
                    <label class="text-sm font-medium">Roadtax Document</label>

                    <!-- EXISTING DOCUMENT -->
                    <div
                        v-if="hasExistingDocument && !filePreview"
                        class="mt-2 flex items-center justify-between
                            rounded-lg border bg-gray-50 px-3 py-2 text-xs"
                    >
                        <div class="flex items-center gap-2 text-gray-600">
                            <i class="mdi mdi-file-document-outline"></i>
                            Current document attached
                        </div>

                        <a
                            :href="existingDocumentUrl"
                            target="_blank"
                            class="text-indigo-600 hover:underline"
                        >
                            View
                        </a>
                    </div>

                    <!-- UPLOAD -->
                    <label
                        class="mt-2 w-full h-24 border-2 border-dashed rounded-lg
                            flex flex-col items-center justify-center cursor-pointer
                            transition
                            hover:border-indigo-400 hover:bg-indigo-50"
                    >
                        <input
                            type="file"
                            name="document"
                            accept=".pdf,image/*"
                            class="hidden"
                            @change="onFileChange"
                        />

                        <i class="mdi mdi-file-upload-outline text-2xl text-gray-400 mb-1"></i>

                        <span class="text-xs text-gray-400">
                            {{ filePreview ? 'Replace document' : 'Click to upload roadtax document' }}
                        </span>
                    </label>

                    <div
                        v-if="filePreview"
                        class="mt-2 flex items-center gap-2 text-xs text-gray-600"
                    >
                        <i class="mdi mdi-file-check-outline text-green-600"></i>
                        {{ filePreview }}

                        <button
                            class="text-red-600 hover:underline ml-2"
                            type="button"
                            @click="removeFile"
                        >
                            Remove
                        </button>
                    </div>

                    <div v-if="form.errors.document" class="text-xs text-red-500 mt-1">
                        {{ form.errors.document }}
                    </div>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-2">
                <button class="px-4 py-2 bg-gray-200 rounded" @click="emit('close')">
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ mode === 'create' ? 'Create' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</template>
