<script setup>
import { computed, ref, inject, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

/* =========================
   EMITS / PROPS
========================= */
const emit = defineEmits(['close'])

const props = defineProps({
    po: {
        type: Object,
        required: true,
    },
})

const toast = inject('toast', null)
const { formatCurrency } = useFormat()

/* =========================
   COMPUTED
========================= */

/**
 * PO Total = Σ (qty × unit_price)
 */
const poTotalAmount = computed(() => {
    if (!props.po.items?.length) return 0

    return props.po.items.reduce((sum, item) => {
        return sum + (Number(item.quantity) * Number(item.unit_price))
    }, 0)
})

/**
 * Remaining amount (backend wins if provided)
 */
const remainingAmount = computed(() => {
    return props.po.remaining_amount ?? poTotalAmount.value
})

/* =========================
   FORM
========================= */
const form = useForm({
    purchase_order_id: props.po.id,
    invoice_number: '',
    invoice_date: '',
    due_date: '',
    invoice_amount: '',
    remarks: '',
    document: null,
})

/* =========================
   PREFILL INVOICE AMOUNT
========================= */
watch(
    () => props.po,
    () => {
        form.invoice_amount = remainingAmount.value
    },
    { immediate: true }
)

/* =========================
   FILE PREVIEW
========================= */
const filePreview = ref(null)

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
    form.post(route('ap-invoices.store'), {
        forceFormData: true,
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show(
                'AP Invoice created successfully',
                'success'
            )
            emit('close')
        },

        onError: () => {
            const firstError = Object.values(form.errors ?? {})[0]
            toast?.value?.show(
                firstError || 'Please correct the highlighted fields.',
                'error'
            )
        },
    })
}
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">

        <!-- MODAL -->
        <div
            class="bg-white rounded-xl shadow-lg w-full max-w-2xl
                   max-h-[90vh] flex flex-col"
        >

            <!-- HEADER -->
            <div class="px-6 py-4 border-b flex justify-between items-center shrink-0">
                <h3 class="text-lg font-semibold">Create AP Invoice</h3>
                <button
                    @click="emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    ✕
                </button>
            </div>

            <!-- BODY (SCROLLABLE) -->
            <div class="px-6 py-4 space-y-5 overflow-y-auto flex-1">

                <!-- PO SUMMARY -->
                <div class="rounded-lg border bg-gray-50 px-4 py-3 text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">PO No</span>
                        <span class="font-medium">{{ po.code }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Supplier</span>
                        <span>{{ po.supplier?.company_name }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-500">Remaining Amount</span>
                        <div class="text-right">
                            <div class="font-semibold text-indigo-600">
                                {{ formatCurrency(remainingAmount) }}
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ po.items?.length ?? 0 }} item(s)
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                    <!-- INVOICE NUMBER -->
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">
                            Invoice Number <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.invoice_number"
                            class="input w-full"
                            :class="{ 'border-red-500 ring-1 ring-red-300': form.errors.invoice_number }"
                            autofocus
                        />
                        <div
                            v-if="form.errors.invoice_number"
                            class="text-xs text-red-500 mt-1"
                        >
                            {{ form.errors.invoice_number }}
                        </div>
                    </div>

                    <!-- INVOICE DATE -->
                    <div>
                        <label class="text-sm font-medium">
                            Invoice Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            v-model="form.invoice_date"
                            class="input w-full"
                            :class="{ 'border-red-500 ring-1 ring-red-300': form.errors.invoice_date }"
                        />
                        <div
                            v-if="form.errors.invoice_date"
                            class="text-xs text-red-500 mt-1"
                        >
                            {{ form.errors.invoice_date }}
                        </div>
                    </div>

                    <!-- DUE DATE -->
                    <div>
                        <label class="text-sm font-medium">
                            Due Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            v-model="form.due_date"
                            class="input w-full"
                            :class="{ 'border-red-500 ring-1 ring-red-300': form.errors.due_date }"
                        />
                        <div
                            v-if="form.errors.due_date"
                            class="text-xs text-red-500 mt-1"
                        >
                            {{ form.errors.due_date }}
                        </div>
                    </div>

                    <!-- INVOICE AMOUNT (READ ONLY) -->
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">
                            Invoice Amount
                        </label>
                        <input
                            type="number"
                            v-model="form.invoice_amount"
                            readonly
                            class="input w-full bg-gray-100 cursor-not-allowed"
                        />
                        <p class="text-xs text-gray-500 mt-1">
                            Invoice amount is derived from PO items (Qty × Unit Price)
                        </p>
                    </div>

                    <!-- REMARKS -->
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">Remarks</label>
                        <textarea
                            v-model="form.remarks"
                            rows="3"
                            class="input w-full"
                        ></textarea>
                    </div>

                    <!-- ATTACHMENT -->
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium">
                            Vendor Invoice Document <span class="text-red-500">*</span>
                        </label>

                        <!-- UPLOAD ZONE -->
                        <label
                            class="mt-2 w-full h-24 border-2 border-dashed rounded-lg
                                flex flex-col items-center justify-center cursor-pointer
                                transition hover:border-indigo-400 hover:bg-indigo-50"
                            :class="{ 'border-red-400 bg-red-50': form.errors.document }"
                        >
                            <input
                                type="file"
                                accept=".pdf,image/*"
                                class="hidden"
                                @change="onFileChange"
                            />

                            <i class="mdi mdi-file-upload-outline text-2xl text-gray-400 mb-1"></i>

                            <span class="text-xs text-gray-400">
                                {{ filePreview
                                    ? 'Replace document'
                                    : 'Click to upload invoice (PDF / Image)' }}
                            </span>
                        </label>

                        <!-- FILE PREVIEW -->
                        <div
                            v-if="filePreview"
                            class="mt-2 flex items-center gap-2 text-xs text-gray-600"
                        >
                            <i class="mdi mdi-file-check-outline text-green-600"></i>
                            {{ filePreview }}
                            <button
                                type="button"
                                class="text-red-600 hover:underline ml-2"
                                @click="removeFile"
                            >
                                Remove
                            </button>
                        </div>

                        <!-- ERROR -->
                        <div
                            v-if="form.errors.document"
                            class="text-xs text-red-500 mt-1"
                        >
                            {{ form.errors.document }}
                        </div>
                    </div>

                </div>
            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-2 shrink-0">
                <button
                    class="px-4 py-2 bg-gray-200 rounded"
                    @click="emit('close')"
                >
                    Cancel
                </button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded disabled:opacity-50"
                    :disabled="form.processing || !form.document"
                    @click="submit"
                >
                    Create Invoice
                </button>
            </div>

        </div>
    </div>
</template>
