<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

/* =========================
   INJECTIONS
========================= */
const toast = inject('toast', null)
// useFormat is optional for later (phone / display), not required here
// const { formatPhone } = inject('useFormat', {})

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    supplier: {
        type: Object,
        default: null,
    },
    mode: {
        type: String,
        required: true, // create | edit
    },
})

const emit = defineEmits(['close', 'saved'])

const isEdit = computed(() => props.mode === 'edit')

/* =========================
   FORM
========================= */
const form = useForm({
    company_name: props.supplier?.company_name ?? '',
    registration_no: props.supplier?.registration_no ?? '',
    contact_person: props.supplier?.contact_person ?? '',
    contact_phone: props.supplier?.contact_phone ?? '',
    email: props.supplier?.email ?? '',
    address: props.supplier?.address ?? '',
    status: props.supplier?.status ?? 'active',
})

/* =========================
   SUBMIT
========================= */
function submit() {
    if (isEdit.value) {
        form.post(
            route('suppliers.update', props.supplier.uuid),
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast?.value?.show('Supplier updated', 'success')
                    emit('saved')
                    emit('close')
                },
                onError: () => {
                    toast?.value?.show('Failed to update supplier', 'error')
                },
            }
        )
    } else {
        form.post(route('suppliers.store'), {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Supplier created', 'success')
                emit('saved')
                emit('close')
            },
            onError: (e) => {
                console.log(e)
                toast?.value?.show(e, 'error')
            },
        })
    }
}
</script>

<template>
    <!-- BACKDROP -->
    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center">
        <!-- MODAL -->
        <div class="bg-white rounded-xl w-full max-w-lg shadow-lg">
            <!-- HEADER -->
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-semibold">
                    {{ isEdit ? 'Edit Supplier' : 'Create Supplier' }}
                </h2>

                <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="label">Company Name *</label>
                    <input
                        v-model="form.company_name"
                        class="input"
                        placeholder="Supplier company name"
                    />
                    <div v-if="form.errors.company_name" class="error">
                        {{ form.errors.company_name }}
                    </div>
                </div>

                <div>
                    <label class="label">Registration No</label>
                    <input
                        v-model="form.registration_no"
                        class="input"
                        placeholder="Registration number"
                    />
                </div>

                <div>
                    <label class="label">Contact Person</label>
                    <input
                        v-model="form.contact_person"
                        class="input"
                        placeholder="PIC name"
                    />
                </div>

                <div>
                    <label class="label">Contact Phone</label>
                    <input
                        v-model="form.contact_phone"
                        class="input"
                        placeholder="+60..."
                    />
                </div>

                <div>
                    <label class="label">Email</label>
                    <input
                        v-model="form.email"
                        class="input"
                        placeholder="example@email.com"
                    />
                </div>

                <div>
                    <label class="label">Address</label>
                    <textarea
                        v-model="form.address"
                        class="input"
                        rows="3"
                        placeholder="Supplier address"
                    />
                </div>

                <div v-if="isEdit">
                    <label class="label">Status</label>
                    <select v-model="form.status" class="input">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blacklisted">Blacklisted</option>
                    </select>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-2">
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 border rounded-lg"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg disabled:opacity-50"
                >
                    {{ isEdit ? 'Update' : 'Create' }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.label {
    @apply block text-sm font-medium text-gray-700 mb-1;
}

.input {
    @apply w-full border rounded-lg px-3 py-2 text-sm
           focus:outline-none focus:ring focus:ring-indigo-200;
}

.error {
    @apply text-xs text-red-600 mt-1;
}
</style>
