<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import SupplierForm from '@/Pages/Suppliers/Partials/SupplierForm.vue'
import DeleteConfirmation from '@/Components/DeleteConfirmation.vue'

const fileInputRef = ref(null)
const showDeleteModal = ref(false)
const deletingQuotation = ref(null)

/* =========================
   INJECT
========================= */
const toast = inject('toast', null)

/* =========================
   PROPS
========================= */
const props = defineProps({
    pr: {
        type: Object,
        required: true,
    },
    isDraft: {
        type: Boolean,
        required: true,
    },
})

/* =========================
   STATE
========================= */
const showSupplierModal = ref(false)

const suppliers = ref([])
const availableQuotations = ref([])

const selectedSupplierId = ref(null)
const selectedQuotationId = ref(null)
const quotationFile = ref(null)

const quotationForm = ref({
    quotation_no: '',
    amount: null,
    delivery_time: null,
    terms: '',
})

/* =========================
   EXISTING ATTACHED QUOTATIONS
========================= */
const quotations = computed(() =>
    props.pr.quotations ?? []
)

/* =========================
   LOAD SUPPLIERS
========================= */
onMounted(loadSuppliers)

async function loadSuppliers() {
    try {
        const res = await axios.get(route('suppliers.simple-list'))
        suppliers.value = res.data ?? []
    } catch (e) {
        console.error(e)
        toast?.value?.show(e, 'error')
    }
}

async function refreshSuppliers() {
    await loadSuppliers()
}

/* =========================
   LOAD AVAILABLE QUOTATIONS
========================= */
watch(selectedSupplierId, async (supplierUuid) => {
    selectedQuotationId.value = null
    availableQuotations.value = []

    if (!supplierUuid) return

    try {
        const res = await axios.get(
            route('purchase-request.quotations', {
                uuid: props.pr.uuid,
                supplier_uuid: supplierUuid,
            })
        )

        availableQuotations.value = res.data ?? []
    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to load quotations', 'error')
    }
})

/* =========================
   MUTUAL EXCLUSION
========================= */
watch(selectedQuotationId, (val) => {
    if (val) {
        quotationForm.value = {
            quotation_no: '',
            amount: null,
            delivery_time: null,
            terms: '',
        }
        quotationFile.value = null
    }
})

/* =========================
   ATTACH QUOTATION
========================= */
async function attachQuotation() {
    if (!selectedSupplierId.value) {
        toast?.value?.show('Please select supplier', 'error')
        return
    }

    if (!selectedQuotationId.value && !quotationFile.value) {
        toast?.value?.show(
            'Select existing quotation or upload new quotation',
            'error'
        )
        return
    }

    const formData = new FormData()
    formData.append('supplier_uuid', selectedSupplierId.value)

    if (selectedQuotationId.value) {
        // existing quotation
        formData.append('quotation_id', selectedQuotationId.value)
    } else {
        // new quotation
        formData.append('quotation_no', quotationForm.value.quotation_no)
        formData.append('amount', quotationForm.value.amount ?? 0)
        formData.append('delivery_time', quotationForm.value.delivery_time ?? 0)
        formData.append('terms', quotationForm.value.terms ?? '')

        if (quotationFile.value) {
            formData.append('file', quotationFile.value)
        }
    }

    try {
        await axios.post(
            route('purchase-request.quotations.attach', props.pr.uuid),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )

        toast?.value?.show('Quotation attached successfully', 'success')

        // reset
        selectedSupplierId.value = null
        selectedQuotationId.value = null
        quotationFile.value = null
        quotationForm.value = {
            quotation_no: '',
            amount: null,
            delivery_time: null,
            terms: '',
        }
        availableQuotations.value = []

        router.reload({
            only: ['pr'],
            preserveScroll: true,
        })
    } catch (e) {
        console.error(e)
        toast?.value?.show(e, 'error')
    }
}

/* =========================
   REMOVE (PLACEHOLDER)
========================= */
function askRemoveQuotation(q) {
    deletingQuotation.value = q
    showDeleteModal.value = true
}

async function confirmRemoveQuotation() {
    if (!deletingQuotation.value) return

    try {
        await axios.delete(
            route('purchase-request.detach-quotation', {
                uuid: props.pr.uuid,
                quotationUuid: deletingQuotation.value.uuid,
            })
        )

        toast?.value?.show('Quotation removed', 'success')

        showDeleteModal.value = false
        deletingQuotation.value = null

        router.reload({
            only: ['pr'],
            preserveScroll: true,
        })
    } catch (e) {
        console.error(e)
        toast?.value?.show('Failed to remove quotation', 'error')
    }
}

function closeDeleteModal() {
    showDeleteModal.value = false
    deletingQuotation.value = null
}

function onFileSelect(e) {
    quotationFile.value = e.target.files[0] ?? null
}

function triggerFilePicker() {
    fileInputRef.value?.click()
}

function clearFile() {
    quotationFile.value = null
}
</script>

<template>
<section class="bg-white rounded-lg shadow border p-6 space-y-6">

    <!-- HEADER -->
    <div class="flex justify-between items-center">
        <h3 class="font-semibold text-lg">
            Suppliers & Quotations
        </h3>

        <button
            v-if="isDraft"
            @click="showSupplierModal = true"
            class="px-3 py-1 bg-indigo-600 text-white rounded text-sm"
        >
            + New Supplier
        </button>
    </div>

    <!-- ATTACH -->
    <div
        v-if="isDraft"
        class="border rounded-lg p-4 bg-gray-50 space-y-5"
    >
        <!-- SUPPLIER -->
        <div>
            <label class="text-sm font-medium">Supplier</label>
            <select
                v-model="selectedSupplierId"
                class="w-full border rounded px-3 py-2"
            >
                <option :value="null">Select supplier</option>
                <option
                    v-for="s in suppliers"
                    :key="s.id"
                    :value="s.uuid"
                >
                    {{ s.company_name }}
                </option>
            </select>

            <p class="text-xs text-gray-500 mt-1">
                If you just created a supplier, please select it from the list.
            </p>
        </div>

        <!-- EXISTING -->
        <div>
            <label class="text-sm font-medium">Existing Quotation</label>
            <select
                v-model="selectedQuotationId"
                :disabled="!availableQuotations.length"
                class="w-full border rounded px-3 py-2"
            >
                <option :value="null">
                    {{ availableQuotations.length
                        ? 'Select quotation'
                        : 'No quotations available'
                    }}
                </option>

                <option
                    v-for="q in availableQuotations"
                    :key="q.id"
                    :value="q.id"
                >
                    {{ q.quotation_no }} — {{ q.amount }} — {{ q.delivery_time }} days
                </option>
            </select>
        </div>

        <!-- OR -->
        <div class="flex items-center gap-3 text-xs text-gray-400">
            <span class="flex-1 border-t"></span>
            OR CREATE NEW
            <span class="flex-1 border-t"></span>
        </div>

        <!-- NEW -->
        <div
            class="grid grid-cols-1 md:grid-cols-2 gap-4"
            :class="{ 'opacity-50 pointer-events-none': selectedQuotationId }"
        >
            <div>
                <label class="text-sm font-medium">Quotation No</label>
                <input v-model="quotationForm.quotation_no" class="input" />
            </div>

            <div>
                <label class="text-sm font-medium">Amount</label>
                <input type="number" v-model.number="quotationForm.amount" class="input" />
            </div>

            <div>
                <label class="text-sm font-medium">Delivery Time (days)</label>
                <input type="number" v-model.number="quotationForm.delivery_time" class="input" />
            </div>

            <div class="md:col-span-2">
                <label class="text-sm font-medium">Notes</label>
                <textarea v-model="quotationForm.terms" rows="2" class="input" />
            </div>

            <div class="md:col-span-2">
                <label class="text-sm font-medium mb-1 block">
                    Upload Quotation
                </label>

                <!-- UPLOAD BOX -->
                <div
                    class="relative border-2 border-dashed rounded-lg p-4
                        flex items-center gap-4 cursor-pointer
                        hover:border-indigo-400 transition"
                    :class="quotationFile
                        ? 'border-indigo-400 bg-indigo-50'
                        : 'border-gray-300 bg-white'"
                    @click="triggerFilePicker"
                >
                    <!-- ICON -->
                    <div class="text-3xl">
                        <i
                            class="mdi"
                            :class="quotationFile
                                ? 'mdi-file-pdf-box text-red-500'
                                : 'mdi-upload text-gray-400'"
                        ></i>
                    </div>

                    <!-- TEXT -->
                    <div class="flex-1">
                        <p class="text-sm font-medium">
                            {{ quotationFile
                                ? quotationFile.name
                                : 'Click to upload quotation (PDF / Image)' }}
                        </p>

                        <p class="text-xs text-gray-500">
                            {{ quotationFile
                                ? 'Click to change file'
                                : 'PDF, JPG, PNG supported' }}
                        </p>
                    </div>

                    <!-- CLEAR -->
                    <button
                        v-if="quotationFile"
                        @click.stop="clearFile"
                        class="text-gray-400 hover:text-red-500"
                        title="Remove file"
                    >
                        <i class="mdi mdi-close-circle-outline text-xl"></i>
                    </button>

                    <!-- REAL INPUT -->
                    <input
                        ref="fileInputRef"
                        type="file"
                        class="hidden"
                        accept=".pdf,.jpg,.jpeg,.png"
                        @change="onFileSelect"
                    />
                </div>
            </div>

        </div>

        <div class="flex justify-end">
            <button
                @click="attachQuotation"
                class="px-4 py-2 bg-green-600 text-white rounded"
            >
                Attach Quotation
            </button>
        </div>
    </div>

    <!-- LIST -->
    <div v-if="quotations.length">
        <table class="min-w-full text-sm border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-2 text-left">Supplier</th>
                    <th class="border px-3 py-2 text-left">Quotation No</th>
                    <th class="border px-3 py-2 text-right">Amount</th>
                    <th class="border px-3 py-2 text-right">Delivery Days</th>
                    <th class="border px-3 py-2 text-center w-20">Action</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="q in quotations" :key="q.id">
                    <td class="border px-3 py-2">{{ q.supplier?.company_name }}</td>
                    <td class="border px-3 py-2">{{ q.quotation_no }}</td>
                    <td class="border px-3 py-2 text-right">{{ q.amount }}</td>
                    <td class="border px-3 py-2 text-right">{{ q.delivery_time }}</td>
                    <td class="border px-3 py-2 text-center">
                        <div class="flex items-center justify-center gap-4">
                            <a
                                v-if="q.attachment"
                                :href="q.attachment.url"
                                target="_blank"
                                class="text-indigo-600 hover:text-indigo-800"
                                title="View quotation"
                            >
                                <i class="mdi mdi-eye-outline"></i>
                            </a>

                            <button
                                v-if="isDraft"
                                @click="askRemoveQuotation(q)"
                                class="text-red-600 hover:text-red-800"
                                title="Remove"
                            >
                                <i class="mdi mdi-delete"></i>
                            </button>

                        </div>
                    </td>

                </tr>
            </tbody>
        </table>
    </div>

    <div v-else class="text-sm text-gray-400">
        No quotations attached
    </div>

    <DeleteConfirmation
        v-if="showDeleteModal"
        title="Remove Quotation"
        message="This quotation will be detached from the purchase request. This action cannot be undone."
        @confirm="confirmRemoveQuotation"
        @close="closeDeleteModal"
    />

    <!-- SUPPLIER MODAL -->
    <SupplierForm
        v-if="showSupplierModal"
        mode="create"
        @close="showSupplierModal = false"
        @saved="() => {
            showSupplierModal = false
            refreshSuppliers()
        }"
    />
</section>
</template>

<style scoped>
.input {
    @apply w-full border rounded px-3 py-2;
}
</style>
