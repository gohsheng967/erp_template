<script setup>
import { ref, watch, inject } from 'vue'
import { useForm } from '@inertiajs/vue3'

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    vehicle: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        default: 'create', // create | edit
    },
})

const emit = defineEmits(['close', 'saved'])

/* =========================
   INJECTIONS
========================= */
const toast = inject('toast', null)

/* =========================
   IMAGE PREVIEW
========================= */
const imagePreview = ref(null)

/* =========================
   FORM
========================= */
const form = useForm({
    brand: '',
    model: '',
    engine_cc: '',
    plate_number: '',
    ownership_type: 'company',
    owner_name: '',
    status: 'active',
    remark: '',

    image: null,
    remove_image: false,
})

/* =========================
   WATCH EDIT MODE
========================= */
watch(
    () => props.vehicle,
    (v) => {
        if (!v) return

        form.brand = v.brand
        form.model = v.model
        form.engine_cc = v.vehicle?.engine_cc ?? ''
        form.plate_number = v.vehicle?.plate_number ?? ''
        form.ownership_type = v.ownership_type
        form.owner_name = v.owner_name
        form.status = v.status
        form.remark = v.remark

        // ✅ attachment-based preview
        imagePreview.value = v.attachment?.url ?? null

        form.image = null
        form.remove_image = false
    },
    { immediate: true }
)

/* =========================
   IMAGE HANDLERS
========================= */
function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) return

    form.image = file
    form.remove_image = false
    imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
    form.remove_image = true
    form.image = null
    imagePreview.value = null
}

/* =========================
   SUBMIT
========================= */
function submit() {
    const options = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            toast?.value?.show(
                props.mode === 'create'
                    ? 'Vehicle created'
                    : 'Vehicle updated',
                'success'
            )
            emit('saved')
            emit('close')
        },
        onError: () => {
            toast?.value?.show('Failed to save vehicle', 'error')
        },
    }

    if (props.mode === 'create') {
        form.post(route('inventory.vehicles.store'), options)
    } else {
        form.post(
            route('inventory.vehicles.update', props.vehicle.uuid),
            {
                ...options,
                data: {
                    ...form.data(),
                    _method: 'PUT',
                },
            }
        )
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold">
                    {{ mode === 'create' ? 'Create Vehicle' : 'Edit Vehicle' }}
                </h3>

                <button
                    @click="emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="px-6 py-4 space-y-4">

                <!-- IMAGE -->
                <div>
                    <label class="text-sm font-medium">Vehicle Image</label>

                    <div class="mt-2 flex gap-4 items-start">

                        <!-- PREVIEW / DROP ZONE -->
                        <label
                            class="relative w-32 h-32 rounded-lg border-2 border-dashed
                                   flex items-center justify-center cursor-pointer
                                   transition
                                   hover:border-indigo-400 hover:bg-indigo-50"
                        >
                            <input
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onImageChange"
                            />

                            <!-- IMAGE -->
                            <img
                                v-if="imagePreview"
                                :src="imagePreview"
                                class="absolute inset-0 object-cover w-full h-full rounded-lg"
                            />

                            <!-- PLACEHOLDER -->
                            <div
                                v-else
                                class="flex flex-col items-center text-gray-400 text-xs"
                            >
                                <i class="mdi mdi-image-plus text-2xl mb-1"></i>
                                Click to upload
                            </div>
                        </label>

                        <!-- ACTIONS -->
                        <div class="space-y-2 pt-1">
                            <div class="text-xs text-gray-500">
                                JPG, PNG, WEBP<br />
                                Max 5MB
                            </div>

                            <button
                                v-if="imagePreview"
                                type="button"
                                class="text-xs text-red-600 hover:underline"
                                @click="removeImage"
                            >
                                Remove image
                            </button>
                        </div>
                    </div>

                    <div v-if="form.errors.image" class="text-xs text-red-500 mt-1">
                        {{ form.errors.image }}
                    </div>
                </div>

                <!-- BASIC -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">
                            Brand<span class="text-red-500 text-xs">*</span>
                        </label>
                        <input v-model="form.brand" class="input w-full" />
                        <div v-if="form.errors.brand" class="text-xs text-red-500">
                            {{ form.errors.brand }}
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Model<span class="text-red-500 text-xs">*</span>
                        </label>
                        <input v-model="form.model" class="input w-full" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Engine CC</label>
                        <input
                            type="number"
                            v-model="form.engine_cc"
                            class="input w-full"
                        />
                    </div>

                    <div>
                        <label class="text-sm font-medium">
                            Plate Number<span class="text-red-500 text-xs">*</span>
                        </label>
                        <input v-model="form.plate_number" class="input w-full" />
                        <div
                            v-if="form.errors.plate_number"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.plate_number }}
                        </div>
                    </div>
                </div>

                <!-- OWNERSHIP -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm font-medium">Ownership</label>
                        <select v-model="form.ownership_type" class="input w-full">
                            <option value="company">Company</option>
                            <option value="individual">Individual</option>
                        </select>
                    </div>

                    <div v-if="form.ownership_type === 'individual'">
                        <label class="text-sm font-medium">Owner Name</label>
                        <input v-model="form.owner_name" class="input w-full" />
                    </div>
                </div>

                <!-- STATUS -->
                <div>
                    <label class="text-sm font-medium">Status</label>
                    <select v-model="form.status" class="input w-full">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>

                <!-- REMARK -->
                <div>
                    <label class="text-sm font-medium">Remark</label>
                    <textarea
                        v-model="form.remark"
                        rows="3"
                        class="input w-full"
                    />
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-2">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="emit('close')"
                >
                    Cancel
                </button>

                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ mode === 'create' ? 'Create' : 'Save' }}
                </button>
            </div>
        </div>
    </div>
</template>
