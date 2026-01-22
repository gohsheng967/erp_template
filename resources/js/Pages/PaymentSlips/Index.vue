<script setup>
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import PaymentSlipModal from '@/Pages/PettyCash/Topups/Partials/PaymentSlipModal.vue'
import ApPaymentSlipModal from '@/Pages/Transactions/ApInvoices/Partials/ApPaymentSlipModal.vue'
import ClaimPaymentSlipModal from '@/Pages/Transactions/Claims/Partials/ClaimPaymentSlipModal.vue'
import Modal from '@/Components/Modal.vue'

const page = usePage()
const { formatCurrency, formatDateTime, capitalize } = useFormat()

const slips = computed(() => page.props.slips ?? { data: [], links: [] })
const projects = computed(() => page.props.projects ?? [])
const filters = page.props.filters ?? {}

const filterForm = useForm({
    search: filters.search ?? '',
    status: filters.status ?? '',
    project_id: filters.project_id ?? '',
    voucher: filters.voucher ?? '',
    date_from: filters.date_from ?? '',
    date_to: filters.date_to ?? '',
})
const selectedSlip = ref(null)
const showSlip = ref(false)
const showApSlip = ref(false)
const showClaimSlip = ref(false)
const showUpload = ref(false)
const showCancel = ref(false)

const uploadForm = useForm({
    attachments: [],
})
const cancelForm = useForm({
    reason: '',
})

function openSlip(slip) {
    selectedSlip.value = slip

    if (slip.source_type?.includes('ApInvoice')) {
        showApSlip.value = true
        return
    }

    if (slip.source_type?.includes('Claim')) {
        showClaimSlip.value = true
        return
    }

    showSlip.value = true
}

function closeSlip() {
    showSlip.value = false
    selectedSlip.value = null
}

function closeApSlip() {
    showApSlip.value = false
    selectedSlip.value = null
}

function closeClaimSlip() {
    showClaimSlip.value = false
    selectedSlip.value = null
}

function applyFilters() {
    router.get(route('payment-slips.index'), filterForm.data(), {
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters() {
    filterForm.reset()
    applyFilters()
}

function openUpload(slip) {
    selectedSlip.value = slip
    uploadForm.reset()
    showUpload.value = true
}

function closeUpload() {
    showUpload.value = false
}

function handleFiles(event) {
    uploadForm.attachments = Array.from(event.target.files)
}

function submitUpload() {
    if (!selectedSlip.value) return

    uploadForm.post(
        route('payment-slips.upload', selectedSlip.value.id),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                closeUpload()
                router.reload({ only: ['slips'] })
            },
        }
    )
}

function openCancel(slip) {
    selectedSlip.value = slip
    cancelForm.reset()
    showCancel.value = true
}

function closeCancel() {
    showCancel.value = false
}

function submitCancel() {
    if (!selectedSlip.value) return

    cancelForm.post(
        route('payment-slips.cancel', selectedSlip.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCancel()
                router.reload({ only: ['slips'] })
            },
        }
    )
}

function slipProject(slip) {
    if (slip.source_type?.includes('PettyCashTopup')) {
        if (slip.source?.wallet?.context_type === 'office') {
            return 'Office'
        }
        return slip.source?.wallet?.project?.name ?? 'Project'
    }

    if (slip.source_type?.includes('Claim')) {
        return slip.source?.project?.name ?? 'Others'
    }

    return slip.source?.purchase_order?.purchase_request?.project?.name
        ?? slip.source?.purchase_order?.code
        ?? 'Project'
}

function slipRequester(slip) {
    if (slip.source_type?.includes('PettyCashTopup')) {
        return slip.source?.requester?.name ?? '-'
    }

    if (slip.source_type?.includes('Claim')) {
        return slip.source?.issuer?.name ?? '-'
    }

    return slip.source?.supplier?.company_name ?? '-'
}

function slipStatus(slip) {
    return slip.cancelled_at ? 'cancelled' : 'approved'
}

function slipPaidDate(slip) {
    if (!slip?.is_paid || !slip?.paid_date) {
        return '-'
    }

    return formatDateTime(slip.paid_date)
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Payment Slips
            </h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-6 space-y-4">
                    <div class="bg-white p-4 rounded-lg shadow border">
                        <div class="flex flex-wrap gap-4 items-end">
                            <div class="flex flex-col w-full md:w-1/3">
                                <label class="text-sm font-medium">Search</label>
                                <input
                                    v-model="filterForm.search"
                                    class="border rounded px-3 py-2"
                                    placeholder="Slip No / Top-Up No / Claim No / Requester"
                                    @keyup.enter="applyFilters"
                                />
                            </div>

                            <div class="flex flex-col w-40">
                                <label class="text-sm font-medium">Status</label>
                                <select
                                    v-model="filterForm.status"
                                    class="border rounded px-3 py-2"
                                >
                                    <option value="">All</option>
                                    <option value="approved">Approved</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div class="flex flex-col w-56">
                                <label class="text-sm font-medium">Project</label>
                                <select
                                    v-model="filterForm.project_id"
                                    class="border rounded px-3 py-2"
                                >
                                    <option value="">All</option>
                                    <option value="office">Office</option>
                                    <option
                                        v-for="project in projects"
                                        :key="project.id"
                                        :value="project.id"
                                    >
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex flex-col w-40">
                                <label class="text-sm font-medium">Voucher</label>
                                <select
                                    v-model="filterForm.voucher"
                                    class="border rounded px-3 py-2"
                                >
                                    <option value="">All</option>
                                    <option value="yes">Uploaded</option>
                                    <option value="no">Not Uploaded</option>
                                </select>
                            </div>

                            <div class="flex flex-col w-40">
                                <label class="text-sm font-medium">From</label>
                                <input type="date" v-model="filterForm.date_from" class="border rounded px-3 py-2" />
                            </div>

                            <div class="flex flex-col w-40">
                                <label class="text-sm font-medium">To</label>
                                <input type="date" v-model="filterForm.date_to" class="border rounded px-3 py-2" />
                            </div>

                            <button
                                class="px-4 py-2 h-10 bg-gray-200 rounded"
                                @click="applyFilters"
                            >
                                Apply
                            </button>
                            <button
                                class="px-4 py-2 h-10 bg-gray-200 rounded"
                                @click="resetFilters"
                            >
                                Reset
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Slip No</th>
                                    <th class="px-4 py-3">Project</th>
                                    <th class="px-4 py-3">Requester</th>
                                    <th class="px-4 py-3 text-right">Amount</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Voucher</th>
                                    <th class="px-4 py-3">Created Date</th>
                                    <th class="px-4 py-3">Paid Date</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="slip in slips.data" :key="slip.id">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ slip.slip_no ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ slipProject(slip) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ slipRequester(slip) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        {{ formatCurrency(slip.amount) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="slipStatus(slip) === 'approved'
                                                ? 'bg-blue-100 text-blue-800'
                                                : 'bg-red-100 text-red-800'"
                                        >
                                            <i
                                                v-if="slipStatus(slip) === 'cancelled'"
                                                class="mdi mdi-cancel text-xs mr-1"
                                            ></i>
                                            {{ capitalize(slipStatus(slip)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="slip.attachments_count > 0
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ slip.attachments_count > 0 ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ formatDateTime(slip.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-gray-700">
                                        {{ slipPaidDate(slip) }}
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <button
                                            class="text-gray-600 hover:text-gray-800"
                                            title="View"
                                            @click="openSlip(slip)"
                                        >
                                            <i class="mdi mdi-file-eye-outline text-lg"></i>
                                        </button>
                                        <button
                                            class="text-green-600 hover:text-green-800"
                                            title="Upload"
                                            :disabled="slipStatus(slip) === 'cancelled'"
                                            :class="slipStatus(slip) === 'cancelled'
                                                ? 'opacity-50 cursor-not-allowed'
                                                : ''"
                                            @click="openUpload(slip)"
                                        >
                                            <i class="mdi mdi-upload text-lg"></i>
                                        </button>
                                        <button
                                            v-if="slipStatus(slip) !== 'cancelled'"
                                            class="text-red-600 hover:text-red-800"
                                            title="Cancel Slip"
                                            :disabled="!slip.can_cancel"
                                            :class="!slip.can_cancel ? 'opacity-50 cursor-not-allowed' : ''"
                                            @click="openCancel(slip)"
                                        >
                                            <i class="mdi mdi-cancel text-lg"></i>
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="!slips.data.length">
                                    <td
                                        colspan="9"
                                        class="px-4 py-6 text-center text-gray-400"
                                    >
                                        No payment slips found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="slips.links?.length" class="mt-4 flex gap-1">
                        <a
                            v-for="link in slips.links"
                            :key="link.label"
                            :href="link.url ?? ''"
                            v-html="link.label"
                            class="px-3 py-1 border rounded text-sm"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <PaymentSlipModal
        :show="showSlip"
        :slip="selectedSlip"
        @close="closeSlip"
    />

    <ApPaymentSlipModal
        :show="showApSlip"
        :invoice="selectedSlip?.source"
        :slip="selectedSlip"
        @close="closeApSlip"
    />

    <ClaimPaymentSlipModal
        :show="showClaimSlip"
        :slip="selectedSlip"
        @close="closeClaimSlip"
    />

    <Modal :show="showUpload" max-width="lg" @close="closeUpload">
        <div class="space-y-5">
            <div>
                <div class="text-lg font-semibold">Upload Payment Slip</div>
                <p class="text-sm text-gray-500">
                    Slip No: {{ selectedSlip?.slip_no ?? '-' }}
                </p>
            </div>

            <div
                class="border-2 border-dashed rounded-lg p-6 text-center bg-gray-50 hover:border-indigo-300 transition"
            >
                <input
                    id="paymentSlipUpload"
                    type="file"
                    multiple
                    class="hidden"
                    @change="handleFiles"
                />
                <label for="paymentSlipUpload" class="cursor-pointer block">
                    <i class="mdi mdi-upload text-3xl text-gray-400"></i>
                    <div class="mt-2 text-sm text-gray-600">
                        Click to upload or drag files here
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        PDF, JPG, PNG (max 10MB each)
                    </div>
                </label>
            </div>

            <div
                v-if="uploadForm.errors.attachments"
                class="text-xs text-red-600"
            >
                {{ uploadForm.errors.attachments }}
            </div>

            <div v-if="uploadForm.attachments.length" class="space-y-2">
                <div class="text-sm font-medium text-gray-700">Selected files</div>
                <ul class="space-y-2 text-sm">
                    <li
                        v-for="(file, index) in uploadForm.attachments"
                        :key="index"
                        class="flex items-center justify-between bg-white border rounded-md px-3 py-2"
                    >
                        <span class="truncate max-w-[240px]">
                            {{ file.name }}
                        </span>
                        <span class="text-xs text-gray-400">
                            {{ (file.size / 1024 / 1024).toFixed(2) }} MB
                        </span>
                    </li>
                </ul>
            </div>

            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="closeUpload">
                    Cancel
                </button>
                <button
                    class="btn-primary"
                    :disabled="uploadForm.processing || !uploadForm.attachments.length"
                    @click="submitUpload"
                >
                    Upload
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showCancel" max-width="lg" @close="closeCancel">
        <div class="space-y-4">
            <div class="text-lg font-semibold">Cancel Payment Slip</div>
            <p class="text-sm text-gray-500">
                Slip No: {{ selectedSlip?.slip_no ?? '-' }}
            </p>

            <div>
                <label class="text-sm font-medium">Reason</label>
                <textarea
                    v-model="cancelForm.reason"
                    rows="3"
                    class="mt-2 w-full border rounded px-3 py-2 text-sm"
                    placeholder="Reason for cancellation"
                ></textarea>
                <div
                    v-if="cancelForm.errors.reason"
                    class="text-xs text-red-600 mt-1"
                >
                    {{ cancelForm.errors.reason }}
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button class="btn-secondary" @click="closeCancel">
                    Close
                </button>
                <button
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
                    :disabled="cancelForm.processing || !cancelForm.reason"
                    @click="submitCancel"
                >
                    Cancel Slip
                </button>
            </div>
        </div>
    </Modal>
</template>
