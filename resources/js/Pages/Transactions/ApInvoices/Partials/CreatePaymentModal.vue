<script setup>
import { ref, inject, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    show: Boolean,
    invoice: Object,
    payment: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close', 'success'])

/* =========================
   DEPENDENCIES
========================= */
const toast = inject('toast', null)

/* =========================
   MODE
========================= */
const isEdit = computed(() => !!props.payment)

/* =========================
   FORM
========================= */
const form = useForm({
    amount: '',
    payment_date: '',
    reference: '',
    remarks: '',
    proofs: [],
    proof_mode: 'add', // add | replace
})

const replaceProofs = ref(false)
const fileInput = ref(null)

/* =========================
   WATCHERS
========================= */
watch(
    () => props.show,
    (val) => {
        if (val) initForm()
    }
)

/* =========================
   INIT
========================= */
function initForm() {
    form.reset()
    form.clearErrors()

    replaceProofs.value = false
    form.proof_mode = 'add'

    if (fileInput.value) fileInput.value.value = ''

    if (isEdit.value) {
        form.amount = props.payment.amount
        form.payment_date = props.payment.payment_date
        form.reference = props.payment.reference
        form.remarks = props.payment.remarks
    }
}

/* =========================
   METHODS
========================= */
function close() {
    emit('close')
}

function onFileChange(e) {
    form.proofs = Array.from(e.target.files)
}

function removeFile(index) {
    form.proofs.splice(index, 1)
}

function submit() {
    if (form.processing) return

    const routeName = isEdit.value
        ? 'ap-invoices.payments.update'
        : 'ap-invoices.payments.store'

    const routeParam = isEdit.value
        ? props.payment.uuid
        : props.invoice.uuid

    if (isEdit.value) {
        form.proof_mode = replaceProofs.value ? 'replace' : 'add'
    }

    form.post(
        route(routeName, routeParam),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show(
                    isEdit.value
                        ? 'Payment updated successfully'
                        : 'Payment recorded successfully',
                    'success'
                )
                emit('success')
                close()
            },
            onError: () => {
                toast?.value?.show('Failed to save payment', 'error')
            },
        }
    )
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">

        <!-- BACKDROP -->
        <div class="absolute inset-0 bg-black/40" @click="close"></div>

        <!-- MODAL -->
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-xl z-10">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">
                        {{ isEdit ? 'Edit Payment' : 'Record Payment' }}
                    </h3>
                    <p class="text-sm text-gray-500">
                        AP Invoice · {{ invoice.invoice_number }}
                    </p>
                </div>

                <button
                    @click="close"
                    class="text-gray-400 hover:text-gray-600 text-xl"
                >
                    ×
                </button>
            </div>

            <!-- BODY -->
            <div class="p-6 space-y-5">

                <!-- BALANCE INFO -->
                <div
                    v-if="!isEdit"
                    class="flex justify-between items-center bg-amber-50 border border-amber-200 rounded-lg px-4 py-3"
                >
                    <div>
                        <div class="text-sm text-amber-700">
                            Outstanding Balance
                        </div>
                        <div class="text-lg font-semibold text-amber-900">
                            {{ invoice.balance_amount }}
                        </div>
                    </div>
                </div>

                <!-- FORM GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- AMOUNT -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Amount
                        </label>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            class="mt-1 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.amount" class="text-xs text-red-600 mt-1">
                            {{ form.errors.amount }}
                        </p>
                    </div>

                    <!-- DATE -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Payment Date
                        </label>
                        <input
                            v-model="form.payment_date"
                            type="date"
                            class="mt-1 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.payment_date" class="text-xs text-red-600 mt-1">
                            {{ form.errors.payment_date }}
                        </p>
                    </div>

                </div>

                <!-- REFERENCE -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Reference
                    </label>
                    <input
                        v-model="form.reference"
                        type="text"
                        placeholder="Bank / Cheque / Transfer Ref"
                        class="mt-1 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <!-- REMARKS -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Remarks
                    </label>
                    <textarea
                        v-model="form.remarks"
                        rows="3"
                        class="mt-1 w-full rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                    ></textarea>
                </div>

                <!-- PROOF MODE (EDIT ONLY) -->
                <div
                    v-if="isEdit"
                    class="bg-gray-50 border rounded-lg p-3 text-sm"
                >
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="replaceProofs"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Replace existing payment proofs
                    </label>

                    <p class="text-xs text-gray-500 mt-1">
                        Existing proofs will be removed and replaced by uploaded files.
                    </p>
                </div>

                <!-- PAYMENT PROOF -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Payment Proof
                    </label>

                    <label
                        class="flex flex-col items-center justify-center border-2 border-dashed rounded-lg px-4 py-6 cursor-pointer hover:border-indigo-400 hover:bg-indigo-50 transition"
                    >
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="hidden"
                            @change="onFileChange"
                        />

                        <div class="text-sm text-gray-600">
                            Click to upload or drag files here
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            JPG, PNG, PDF (max 5MB)
                        </div>
                    </label>

                    <ul v-if="form.proofs.length" class="mt-3 space-y-2">
                        <li
                            v-for="(file, index) in form.proofs"
                            :key="index"
                            class="flex justify-between items-center bg-gray-50 border rounded-md px-3 py-2 text-sm"
                        >
                            <span class="truncate">
                                {{ file.name }}
                            </span>

                            <button
                                type="button"
                                class="text-red-500 hover:text-red-700"
                                @click="removeFile(index)"
                            >
                                Remove
                            </button>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-3 bg-gray-50 rounded-b-xl">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="px-5 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Saving…' : (isEdit ? 'Update Payment' : 'Record Payment') }}
                </button>
            </div>

        </div>
    </div>
</template>
