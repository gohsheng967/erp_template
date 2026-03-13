<script setup>
import { computed, ref, inject } from 'vue'
import axios from 'axios'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'
import PaymentSlipModal from '@/Pages/PettyCash/Topups/Partials/PaymentSlipModal.vue'
import PaymentSlipA4 from '@/Pages/PettyCash/Topups/Partials/PaymentSlipA4.vue'
import ApPaymentSlipModal from '@/Pages/Transactions/ApInvoices/Partials/ApPaymentSlipModal.vue'
import ApPaymentSlipA4 from '@/Pages/Transactions/ApInvoices/Partials/ApPaymentSlipA4.vue'
import ClaimPaymentSlipModal from '@/Pages/Transactions/Claims/Partials/ClaimPaymentSlipModal.vue'
import ClaimPaymentSlipA4 from '@/Pages/Transactions/Claims/Partials/ClaimPaymentSlipA4.vue'
import Modal from '@/Components/Modal.vue'

const page = usePage()
const { formatCurrency, formatDateTime } = useFormat()
const toast = inject('toast', null)

const projects = computed(() => page.props.projects ?? [])
const companyBankAccounts = computed(() => page.props.companyBankAccounts ?? [])
const filters = computed(() => page.props.filters ?? {})
const counts = computed(() => page.props.counts ?? { pending: 0, processing: 0, paid: 0 })
const slipsByTab = computed(() => page.props.slips ?? {})
const pendingRows = computed(() => page.props.pending ?? [])

const tabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'CEO / GM Approval' },
    { key: 'payment_arrangement', label: 'Payment Arrangement' },
    { key: 'paid', label: 'Paid' },
]

const activeTab = ref(page.props.activeTab ?? 'pending')

const filterForm = useForm({
    tab: activeTab.value,
    search: filters.value.search ?? '',
    project_id: filters.value.project_id ?? '',
    module: filters.value.module ?? '',
    requester: filters.value.requester ?? '',
    voucher: filters.value.voucher ?? '',
    amount_min: filters.value.amount_min ?? '',
    amount_max: filters.value.amount_max ?? '',
    date_from: filters.value.date_from ?? '',
    date_to: filters.value.date_to ?? '',
})

const selectedSlip = ref(null)
const showSlip = ref(false)
const showApSlip = ref(false)
const showClaimSlip = ref(false)

const showUpload = ref(false)
const uploadForm = useForm({ attachments: [] })

const showCancel = ref(false)
const cancelForm = useForm({ reason: '' })
const rejectForm = useForm({ reason: '' })
const showApprovalModal = ref(false)
const showArrangementModal = ref(false)
const arrangementSubmitting = ref(false)
const arrangementForm = ref({
    payment_ref: '',
    attachments: [],
    cancel_reason: '',
})

const showGenerate = ref(false)
const generating = ref(false)
const selectedPending = ref(null)
const generateForm = ref({
    company_bank_account_id: '',
    amount: '',
    payment_date: '',
    less_retention: '',
    less_retention_label: 'Less - Retention',
    less_recoupment: '',
    less_recoupment_label: 'Less - Recoupment Advance Payment',
    less_material_ob: '',
    less_material_ob_label: 'Less - Payment Material Purchased OB',
    less_paid_previously: '',
    less_paid_previously_label: 'Less - Amount Paid Previously',
    amount_due_label: 'Amount Due For Payment No.1',
    payment_slip_remark: '',
    remark_label: 'Remarks',
})

const showMarkPaid = ref(false)
const markPaidSubmitting = ref(false)
const markPaidForm = ref({
    payment_ref: '',
    attachments: [],
})

const currentRows = computed(() => {
    if (activeTab.value === 'pending') {
        return pendingRows.value
    }

    return slipsByTab.value?.[activeTab.value]?.data ?? []
})

const currentLinks = computed(() => {
    if (activeTab.value === 'pending') return []
    return slipsByTab.value?.[activeTab.value]?.links ?? []
})

function applyFilters() {
    filterForm.tab = activeTab.value
    router.get(route('payment-slips.index'), filterForm.data(), {
        preserveScroll: true,
        replace: true,
    })
}

function resetFilters() {
    filterForm.search = ''
    filterForm.project_id = ''
    filterForm.module = ''
    filterForm.requester = ''
    filterForm.voucher = ''
    filterForm.amount_min = ''
    filterForm.amount_max = ''
    filterForm.date_from = ''
    filterForm.date_to = ''
    applyFilters()
}

function switchTab(tabKey) {
    activeTab.value = tabKey
    filterForm.tab = tabKey
    applyFilters()
}

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
    showApSlip.value = false
    showClaimSlip.value = false
    selectedSlip.value = null
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

function slipPaidDate(slip) {
    if (!slip?.paid_date) return '-'
    return formatDateTime(slip.paid_date)
}

function rowRefNo(row) {
    if (activeTab.value === 'pending') {
        return row.reference_no || '-'
    }

    if (row.source_type?.includes('ApInvoice')) {
        return row.source?.purchase_order?.code ?? '-'
    }
    if (row.source_type?.includes('Claim')) {
        return row.source?.claim_no ?? '-'
    }
    if (row.source_type?.includes('PettyCashTopup')) {
        return row.source?.topup_no ?? '-'
    }

    return '-'
}

function rowExternalDocRefNo(row) {
    if (activeTab.value === 'pending') {
        return row.external_doc_ref_no || '-'
    }

    if (row.source_type?.includes('ApInvoice')) {
        return row.source?.invoice_number ?? '-'
    }

    return '-'
}

function openUpload(slip) {
    selectedSlip.value = slip
    uploadForm.attachments = []
    showUpload.value = true
}

function closeUpload() {
    showUpload.value = false
}

function handleUploadFiles(event) {
    uploadForm.attachments = Array.from(event.target.files ?? [])
}

function formatFileSize(bytes) {
    const size = Number(bytes ?? 0)
    if (size <= 0) return '0 B'
    if (size < 1024) return `${size} B`
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`
    return `${(size / (1024 * 1024)).toFixed(2)} MB`
}

function removeUploadFile(index) {
    uploadForm.attachments = uploadForm.attachments.filter((_, i) => i !== index)
}

function submitUpload() {
    if (!selectedSlip.value) return

    uploadForm.post(route('payment-slips.upload', selectedSlip.value.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeUpload()
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function openCancel(slip) {
    selectedSlip.value = slip
    cancelForm.reason = ''
    showCancel.value = true
}

function closeCancel() {
    showCancel.value = false
}

function submitCancel() {
    if (!selectedSlip.value) return

    cancelForm.post(route('payment-slips.cancel', selectedSlip.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeCancel()
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function revertToArrangement(slip) {
    router.post(route('payment-slips.revert', slip.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function openArrangementModal(slip) {
    selectedSlip.value = slip
    arrangementForm.value = {
        payment_ref: '',
        attachments: [],
        cancel_reason: '',
    }
    showArrangementModal.value = true
}

function closeArrangementModal() {
    showArrangementModal.value = false
    selectedSlip.value = null
}

function handleArrangementFiles(event) {
    arrangementForm.value.attachments = Array.from(event.target.files ?? [])
}

function removeArrangementFile(index) {
    arrangementForm.value.attachments = arrangementForm.value.attachments.filter((_, i) => i !== index)
}

async function arrangementMarkPaid() {
    if (!selectedSlip.value) return
    if (!arrangementForm.value.payment_ref || !arrangementForm.value.attachments.length) return

    arrangementSubmitting.value = true

    try {
        const slip = selectedSlip.value
        const fd = new FormData()

        if (slip.source_type?.includes('Claim')) {
            fd.append('payment_ref', arrangementForm.value.payment_ref)
            fd.append('payment_slip_id', String(slip.id))
            for (const file of arrangementForm.value.attachments) {
                fd.append('attachments[]', file)
            }
            await axios.post(route('claims.paid', slip.source.uuid), fd)
        } else if (slip.source_type?.includes('PettyCashTopup')) {
            fd.append('payment_ref_no', arrangementForm.value.payment_ref)
            for (const file of arrangementForm.value.attachments) {
                fd.append('attachments[]', file)
            }
            await axios.post(route('petty-cash.topups.pay', slip.source.id), fd)
        } else {
            fd.append('amount', String(slip.amount ?? 0))
            fd.append('payment_date', String(slip.payment_date ?? new Date().toISOString().slice(0, 10)).slice(0, 10))
            fd.append('payment_slip_id', String(slip.id))
            fd.append('company_bank_account_id', String(slip.company_bank_account_id ?? ''))
            fd.append('reference', arrangementForm.value.payment_ref)
            for (const file of arrangementForm.value.attachments) {
                fd.append('proofs[]', file)
            }
            await axios.post(route('ap-invoices.payments.store', slip.source.uuid), fd)
        }

        closeArrangementModal()
        toast?.value?.show('Payment proof uploaded and marked as paid.', 'success')
        activeTab.value = 'paid'
        filterForm.tab = 'paid'
        router.get(route('payment-slips.index'), filterForm.data(), {
            preserveScroll: true,
            replace: true,
        })
    } catch (error) {
        console.error('Failed to mark paid', error)
        const message =
            error?.response?.data?.message ||
            error?.response?.data?.errors?.payment_ref?.[0] ||
            error?.response?.data?.errors?.payment_ref_no?.[0] ||
            error?.response?.data?.errors?.attachments?.[0] ||
            error?.response?.data?.errors?.proofs?.[0] ||
            'Failed to mark paid.'
        toast?.value?.show(message, 'error')
    } finally {
        arrangementSubmitting.value = false
    }
}

function arrangementCancelSlip() {
    if (!selectedSlip.value || !arrangementForm.value.cancel_reason) return

    const form = useForm({ reason: arrangementForm.value.cancel_reason })

    form.post(route('payment-slips.cancel', selectedSlip.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeArrangementModal()
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function openApprovalModal(slip) {
    selectedSlip.value = slip
    rejectForm.reason = ''
    showApprovalModal.value = true
}

function closeApprovalModal() {
    showApprovalModal.value = false
    selectedSlip.value = null
    rejectForm.reason = ''
}

function printApprovalSlip() {
    window.print()
}

function submitApproval() {
    if (!selectedSlip.value) return

    router.post(route('payment-slips.approve', selectedSlip.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            closeApprovalModal()
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function submitRejection() {
    if (!selectedSlip.value) return

    rejectForm.post(route('payment-slips.reject', selectedSlip.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeApprovalModal()
            router.reload({ only: ['pending', 'slips', 'counts'] })
        },
    })
}

function openGenerate(row) {
    selectedPending.value = row
    generateForm.value = {
        company_bank_account_id: '',
        amount: row.module === 'ap_invoice' ? String(row.amount ?? '') : '',
        payment_date: row.module === 'ap_invoice' ? (row.date ?? new Date().toISOString().slice(0, 10)) : '',
        less_retention: '',
        less_retention_label: 'Less - Retention',
        less_recoupment: '',
        less_recoupment_label: 'Less - Recoupment Advance Payment',
        less_material_ob: '',
        less_material_ob_label: 'Less - Payment Material Purchased OB',
        less_paid_previously: '',
        less_paid_previously_label: 'Less - Amount Paid Previously',
        amount_due_label: 'Amount Due For Payment No.1',
        payment_slip_remark: '',
        remark_label: 'Remarks',
    }
    showGenerate.value = true
}

function closeGenerate() {
    showGenerate.value = false
    selectedPending.value = null
}

async function submitGenerate() {
    if (!selectedPending.value) return

    generating.value = true

    try {
        const row = selectedPending.value
        let response

        if (row.module === 'claim') {
            response = await axios.post(route('claims.payment-slip', row.source_uuid), {
                company_bank_account_id: generateForm.value.company_bank_account_id,
                less_retention: generateForm.value.less_retention || null,
                less_retention_label: generateForm.value.less_retention_label || null,
                less_recoupment: generateForm.value.less_recoupment || null,
                less_recoupment_label: generateForm.value.less_recoupment_label || null,
                less_material_ob: generateForm.value.less_material_ob || null,
                less_material_ob_label: generateForm.value.less_material_ob_label || null,
                less_paid_previously: generateForm.value.less_paid_previously || null,
                less_paid_previously_label: generateForm.value.less_paid_previously_label || null,
                payment_slip_remark: generateForm.value.payment_slip_remark || null,
                remark_label: generateForm.value.remark_label || null,
            })
        } else if (row.module === 'topup') {
            response = await axios.post(route('petty-cash.topups.payment-slip', row.source_id), {
                company_bank_account_id: generateForm.value.company_bank_account_id,
                less_retention: generateForm.value.less_retention || null,
                less_recoupment: generateForm.value.less_recoupment || null,
                less_material_ob: generateForm.value.less_material_ob || null,
                less_paid_previously: generateForm.value.less_paid_previously || null,
                amount_due_label: generateForm.value.amount_due_label || null,
                payment_slip_remark: generateForm.value.payment_slip_remark || null,
            })
        } else {
            response = await axios.post(route('ap-invoices.payments.slip', row.source_uuid), {
                amount: Number(generateForm.value.amount || 0),
                payment_date: generateForm.value.payment_date,
                company_bank_account_id: generateForm.value.company_bank_account_id,
                less_retention: generateForm.value.less_retention || null,
                less_recoupment: generateForm.value.less_recoupment || null,
                less_material_ob: generateForm.value.less_material_ob || null,
                less_paid_previously: generateForm.value.less_paid_previously || null,
                payment_slip_remark: generateForm.value.payment_slip_remark || null,
            })
        }

        const slip = response?.data?.slip
        closeGenerate()

        if (slip) {
            selectedSlip.value = slip
            if (row.module === 'ap_invoice') {
                showApSlip.value = true
            } else if (row.module === 'claim') {
                showClaimSlip.value = true
            } else {
                showSlip.value = true
            }
        }

        router.reload({ only: ['pending', 'slips', 'counts'] })
    } catch (error) {
        console.error('Failed to generate payment slip', error)
    } finally {
        generating.value = false
    }
}

function openMarkPaid(slip) {
    selectedSlip.value = slip
    markPaidForm.value = {
        payment_ref: '',
        attachments: [],
    }
    showMarkPaid.value = true
}

function closeMarkPaid() {
    showMarkPaid.value = false
}

function handleMarkPaidFiles(event) {
    markPaidForm.value.attachments = Array.from(event.target.files ?? [])
}

function removeMarkPaidFile(index) {
    markPaidForm.value.attachments = markPaidForm.value.attachments.filter((_, i) => i !== index)
}

async function submitMarkPaid() {
    if (!selectedSlip.value) return

    markPaidSubmitting.value = true

    try {
        const slip = selectedSlip.value
        const fd = new FormData()

        if (slip.source_type?.includes('Claim')) {
            fd.append('payment_ref', markPaidForm.value.payment_ref)
            fd.append('payment_slip_id', String(slip.id))
            for (const file of markPaidForm.value.attachments) {
                fd.append('attachments[]', file)
            }
            await axios.post(route('claims.paid', slip.source.uuid), fd)
        } else if (slip.source_type?.includes('PettyCashTopup')) {
            fd.append('payment_ref_no', markPaidForm.value.payment_ref)
            for (const file of markPaidForm.value.attachments) {
                fd.append('attachments[]', file)
            }
            await axios.post(route('petty-cash.topups.pay', slip.source.id), fd)
        } else {
            fd.append('amount', String(slip.amount ?? 0))
            fd.append('payment_date', String(slip.payment_date ?? new Date().toISOString().slice(0, 10)).slice(0, 10))
            fd.append('payment_slip_id', String(slip.id))
            fd.append('company_bank_account_id', String(slip.company_bank_account_id ?? ''))
            fd.append('reference', markPaidForm.value.payment_ref)
            for (const file of markPaidForm.value.attachments) {
                fd.append('proofs[]', file)
            }
            await axios.post(route('ap-invoices.payments.store', slip.source.uuid), fd)
        }

        closeMarkPaid()
        router.reload({ only: ['pending', 'slips', 'counts'] })
    } catch (error) {
        console.error('Failed to mark paid', error)
    } finally {
        markPaidSubmitting.value = false
    }
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Payment Slips</h2>
        </template>

        <div class="p-6 space-y-6">
            <StandardFilterBar
                title="Filters"
                description="Filter by keyword, project, module, requester, amount range, voucher, and date."
                @apply="applyFilters"
                @reset="resetFilters"
            >
                <div class="flex flex-col w-full md:w-1/3">
                    <label class="text-sm font-medium">Search</label>
                    <input
                        v-model="filterForm.search"
                        class="border rounded px-3 py-2"
                        placeholder="Slip No / PO No / Invoice No / Claim No / Top-up No / Requester"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <div class="flex flex-col w-56">
                    <label class="text-sm font-medium">Project</label>
                    <select v-model="filterForm.project_id" class="border rounded px-3 py-2">
                        <option value="">All</option>
                        <option value="office">Office</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col w-44">
                    <label class="text-sm font-medium">Module</label>
                    <select v-model="filterForm.module" class="border rounded px-3 py-2">
                        <option value="">All</option>
                        <option value="claim">Claim</option>
                        <option value="topup">Top-up</option>
                        <option value="ap_invoice">AP Invoice</option>
                    </select>
                </div>

                <div class="flex flex-col w-56">
                    <label class="text-sm font-medium">Requester</label>
                    <input
                        v-model="filterForm.requester"
                        class="border rounded px-3 py-2"
                        placeholder="Requester / Supplier name"
                        @keyup.enter="applyFilters"
                    />
                </div>

                <div v-if="activeTab !== 'pending'" class="flex flex-col w-40">
                    <label class="text-sm font-medium">Voucher</label>
                    <select v-model="filterForm.voucher" class="border rounded px-3 py-2">
                        <option value="">All</option>
                        <option value="yes">Uploaded</option>
                        <option value="no">Not Uploaded</option>
                    </select>
                </div>

                <div class="flex flex-col w-36">
                    <label class="text-sm font-medium">Min Amount</label>
                    <input v-model="filterForm.amount_min" type="number" min="0" step="0.01" class="border rounded px-3 py-2" />
                </div>

                <div class="flex flex-col w-36">
                    <label class="text-sm font-medium">Max Amount</label>
                    <input v-model="filterForm.amount_max" type="number" min="0" step="0.01" class="border rounded px-3 py-2" />
                </div>

                <div class="flex flex-col w-40">
                    <label class="text-sm font-medium">From</label>
                    <input type="date" v-model="filterForm.date_from" class="border rounded px-3 py-2" />
                </div>

                <div class="flex flex-col w-40">
                    <label class="text-sm font-medium">To</label>
                    <input type="date" v-model="filterForm.date_to" class="border rounded px-3 py-2" />
                </div>
            </StandardFilterBar>

            <div class="bg-white rounded shadow border px-4">
                <nav class="flex gap-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        @click="switchTab(tab.key)"
                        class="py-3 font-medium flex items-center gap-2"
                        :class="{
                            'border-b-2 border-indigo-600 text-indigo-600': activeTab === tab.key,
                            'text-gray-500': activeTab !== tab.key
                        }"
                    >
                        {{ tab.label }}
                        <span class="px-2 py-0.5 text-xs rounded-full bg-gray-200">
                            {{ counts?.[tab.key] ?? 0 }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="overflow-x-auto bg-white rounded-lg border">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Ref No</th>
                            <th class="px-4 py-3">External Doc Ref No</th>
                            <th class="px-4 py-3">Module</th>
                            <th class="px-4 py-3">Project</th>
                            <th class="px-4 py-3">Requester</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3">Date</th>
                            <th v-if="activeTab !== 'pending'" class="px-4 py-3">Voucher</th>
                            <th v-if="activeTab !== 'pending'" class="px-4 py-3">Slip No</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="row in currentRows" :key="`${activeTab}-${row.id ?? row.source_id}`">
                            <td class="px-4 py-3 font-medium text-gray-900">
                                {{ rowRefNo(row) }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ rowExternalDocRefNo(row) }}
                            </td>
                            <td class="px-4 py-3 text-gray-700 capitalize">
                                <template v-if="activeTab === 'pending'">
                                    {{ row.module.replace('_', ' ') }}
                                </template>
                                <template v-else>
                                    {{ row.source_type?.includes('Claim') ? 'Claim' : (row.source_type?.includes('ApInvoice') ? 'AP Invoice' : 'Topup') }}
                                </template>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ activeTab === 'pending' ? row.project : slipProject(row) }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ activeTab === 'pending' ? row.requester : slipRequester(row) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ formatCurrency(activeTab === 'pending' ? row.amount : row.amount) }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ formatDateTime(activeTab === 'pending' ? row.date : row.payment_date || row.created_at) }}
                            </td>
                            <td v-if="activeTab !== 'pending'" class="px-4 py-3">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="row.attachments_count > 0 ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                                >
                                    {{ row.attachments_count > 0 ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td v-if="activeTab !== 'pending'" class="px-4 py-3 text-gray-700">
                                {{ row.slip_no || '-' }}
                            </td>
                            <td class="px-4 py-3 text-right space-x-2">
                                <template v-if="activeTab === 'pending'">
                                    <button
                                        class="px-3 py-1.5 rounded bg-indigo-600 text-white text-xs hover:bg-indigo-700"
                                        @click="openGenerate(row)"
                                    >
                                        Adjust & Generate Slip
                                    </button>
                                </template>

                                <template v-else-if="activeTab === 'processing'">
                                    <button class="text-gray-600 hover:text-gray-800" title="Review & Approve" @click="openApprovalModal(row)">
                                        <i class="mdi mdi-file-eye-outline text-lg"></i>
                                    </button>
                                </template>

                                <template v-else-if="activeTab === 'payment_arrangement'">
                                    <button class="text-gray-600 hover:text-gray-800" title="Payment Arrangement" @click="openArrangementModal(row)">
                                        <i class="mdi mdi-file-eye-outline text-lg"></i>
                                    </button>
                                </template>

                                <template v-else>
                                    <button class="text-gray-600 hover:text-gray-800" title="View" @click="openSlip(row)">
                                        <i class="mdi mdi-file-eye-outline text-lg"></i>
                                    </button>
                                    <button class="text-amber-600 hover:text-amber-800" title="Revert to Payment Arrangement" @click="revertToArrangement(row)">
                                        <i class="mdi mdi-restore text-lg"></i>
                                    </button>
                                </template>
                            </td>
                        </tr>

                        <tr v-if="!currentRows.length">
                            <td
                                :colspan="activeTab === 'pending' ? 8 : 10"
                                class="px-4 py-6 text-center text-gray-400"
                            >
                                No records found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="currentLinks?.length" class="flex gap-1">
                <a
                    v-for="link in currentLinks"
                    :key="link.label"
                    :href="link.url ?? ''"
                    v-html="link.label"
                    class="px-3 py-1 border rounded text-sm"
                />
            </div>
        </div>
    </AuthenticatedLayout>

    <PaymentSlipModal :show="showSlip" :slip="selectedSlip" @close="closeSlip" />

    <ApPaymentSlipModal
        :show="showApSlip"
        :invoice="selectedSlip?.source"
        :slip="selectedSlip"
        @close="closeSlip"
    />

    <ClaimPaymentSlipModal
        :show="showClaimSlip"
        :slip="selectedSlip"
        @close="closeSlip"
    />

    <Modal :show="showGenerate" max-width="lg" @close="closeGenerate">
        <div class="space-y-4">
            <div>
                <div class="text-lg font-semibold">Generate Payment Slip</div>
                <p class="text-sm text-gray-500">Module: {{ selectedPending?.module?.replace('_', ' ') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="text-sm font-medium">Company Bank Account</label>
                    <select v-model="generateForm.company_bank_account_id" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                        <option value="">Select account</option>
                        <option v-for="acc in companyBankAccounts" :key="acc.id" :value="acc.id">
                            {{ acc.bank_name }} - {{ acc.account_no }}
                        </option>
                    </select>
                </div>

                <div v-if="selectedPending?.module === 'ap_invoice'">
                    <label class="text-sm font-medium">Amount</label>
                    <input v-model="generateForm.amount" type="number" step="0.01" min="0" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div v-if="selectedPending?.module === 'ap_invoice'">
                    <label class="text-sm font-medium">Payment Date</label>
                    <input v-model="generateForm.payment_date" type="date" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="text-sm font-medium">Retention Label</label>
                    <input v-model="generateForm.less_retention_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm font-medium">Retention Amount</label>
                    <input v-model="generateForm.less_retention" type="number" step="0.01" min="0" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="text-sm font-medium">Recoupment Label</label>
                    <input v-model="generateForm.less_recoupment_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm font-medium">Recoupment Amount</label>
                    <input v-model="generateForm.less_recoupment" type="number" step="0.01" min="0" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="text-sm font-medium">Material OB Label</label>
                    <input v-model="generateForm.less_material_ob_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm font-medium">Material OB Amount</label>
                    <input v-model="generateForm.less_material_ob" type="number" step="0.01" min="0" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div>
                    <label class="text-sm font-medium">Paid Previously Label</label>
                    <input v-model="generateForm.less_paid_previously_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="text-sm font-medium">Paid Previously Amount</label>
                    <input v-model="generateForm.less_paid_previously" type="number" step="0.01" min="0" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>

                <div v-if="selectedPending?.module === 'ap_invoice'" class="md:col-span-2">
                    <label class="text-sm font-medium">Amount Due Label</label>
                    <input v-model="generateForm.amount_due_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
                </div>
            </div>

            <div>
                <label class="text-sm font-medium">Remark Label</label>
                <input v-model="generateForm.remark_label" type="text" class="mt-1 w-full border rounded px-3 py-2 text-sm" />
            </div>

            <div>
                <label class="text-sm font-medium">Remark</label>
                <textarea v-model="generateForm.payment_slip_remark" rows="2" class="mt-1 w-full border rounded px-3 py-2 text-sm"></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button class="btn-secondary" @click="closeGenerate">Close</button>
                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="generating || !generateForm.company_bank_account_id"
                    @click="submitGenerate"
                >
                    Generate Slip
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showUpload" max-width="lg" @close="closeUpload">
        <div class="space-y-4">
            <div class="text-lg font-semibold">Upload Voucher</div>
            <div class="space-y-3">
                <label
                    for="upload-voucher-files"
                    class="block cursor-pointer rounded-lg border-2 border-dashed border-blue-300 bg-blue-50/50 p-5 text-center hover:bg-blue-50 transition"
                >
                    <div class="text-sm font-medium text-blue-700">Click to upload voucher files</div>
                    <div class="mt-1 text-xs text-gray-500">PDF, JPG, PNG. Multiple files supported.</div>
                </label>
                <input id="upload-voucher-files" type="file" multiple class="hidden" @change="handleUploadFiles" />

                <div v-if="uploadForm.attachments.length" class="rounded-lg border border-gray-200 bg-white p-3 space-y-2">
                    <div class="text-xs font-medium text-gray-500">Selected files ({{ uploadForm.attachments.length }})</div>
                    <div class="max-h-40 overflow-auto space-y-2">
                        <div
                            v-for="(file, index) in uploadForm.attachments"
                            :key="`${file.name}-${index}`"
                            class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 text-sm"
                        >
                            <div class="truncate pr-2">
                                <span class="font-medium text-gray-700">{{ file.name }}</span>
                                <span class="ml-2 text-xs text-gray-500">({{ formatFileSize(file.size) }})</span>
                            </div>
                            <button type="button" class="text-red-600 hover:text-red-700 text-xs" @click="removeUploadFile(index)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button class="btn-secondary" @click="closeUpload">Close</button>
                <button class="btn-primary" :disabled="uploadForm.processing || !uploadForm.attachments.length" @click="submitUpload">
                    Upload
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showMarkPaid" max-width="lg" @close="closeMarkPaid">
        <div class="space-y-4">
            <div>
                <div class="text-lg font-semibold">Mark as Paid</div>
                <p class="text-sm text-gray-500">Slip No: {{ selectedSlip?.slip_no ?? '-' }}</p>
            </div>

            <div>
                <label class="text-sm font-medium">Payment Reference</label>
                <input
                    v-model="markPaidForm.payment_ref"
                    class="mt-1 w-full border rounded px-3 py-2 text-sm"
                    placeholder="Payment reference"
                />
            </div>

            <div>
                <label class="text-sm font-medium">Voucher Attachment</label>
                <div class="mt-1 space-y-2">
                    <label
                        for="mark-paid-files"
                        class="block cursor-pointer rounded-lg border-2 border-dashed border-blue-300 bg-blue-50/50 p-4 text-center hover:bg-blue-50 transition"
                    >
                        <div class="text-sm font-medium text-blue-700">Upload payment proof</div>
                        <div class="mt-1 text-xs text-gray-500">Attach one or more files</div>
                    </label>
                    <input id="mark-paid-files" class="hidden" type="file" multiple @change="handleMarkPaidFiles" />

                    <div v-if="markPaidForm.attachments.length" class="rounded-md border border-gray-200 bg-white p-2 space-y-1 max-h-32 overflow-auto">
                        <div
                            v-for="(file, index) in markPaidForm.attachments"
                            :key="`${file.name}-${index}`"
                            class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 text-xs"
                        >
                            <span class="truncate pr-2">{{ file.name }}</span>
                            <button type="button" class="text-red-600 hover:text-red-700" @click="removeMarkPaidFile(index)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button class="btn-secondary" @click="closeMarkPaid">Close</button>
                <button
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50"
                    :disabled="markPaidSubmitting || !markPaidForm.payment_ref || !markPaidForm.attachments.length"
                    @click="submitMarkPaid"
                >
                    Confirm Paid
                </button>
            </div>
        </div>
    </Modal>

    <Modal :show="showCancel" max-width="lg" @close="closeCancel">
        <div class="space-y-4">
            <div class="text-lg font-semibold">Cancel Payment Slip</div>
            <p class="text-sm text-gray-500">Slip No: {{ selectedSlip?.slip_no ?? '-' }}</p>

            <div>
                <label class="text-sm font-medium">Reason</label>
                <textarea
                    v-model="cancelForm.reason"
                    rows="3"
                    class="mt-1 w-full border rounded px-3 py-2 text-sm"
                    placeholder="Reason for cancellation"
                ></textarea>
            </div>

            <div class="flex justify-end gap-2">
                <button class="btn-secondary" @click="closeCancel">Close</button>
                <button
                    class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                    :disabled="cancelForm.processing || !cancelForm.reason"
                    @click="submitCancel"
                >
                    Cancel Slip
                </button>
            </div>
        </div>
    </Modal>

    <div
        v-if="showApprovalModal && selectedSlip"
        class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print"
    >
        <div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">
            <div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
                <h2 class="font-semibold text-lg">
                    CEO / GM Approval - {{ selectedSlip.slip_no ?? '-' }}
                </h2>

                <div class="ml-auto flex items-center gap-3">
                    <button @click="printApprovalSlip">Print / Save PDF</button>
                    <button @click="closeApprovalModal" class="text-red-600">
                        <i class="mdi mdi-close text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="flex h-[calc(100%-56px)] gap-6 p-6">
                <div class="flex-1 overflow-auto">
                    <ApPaymentSlipA4
                        v-if="selectedSlip.source_type?.includes('ApInvoice')"
                        :invoice="selectedSlip.source"
                        :slip="selectedSlip"
                    />
                    <ClaimPaymentSlipA4
                        v-else-if="selectedSlip.source_type?.includes('Claim')"
                        :slip="selectedSlip"
                    />
                    <PaymentSlipA4
                        v-else
                        :slip="selectedSlip"
                    />
                </div>

                <div class="w-[360px] bg-white border rounded-lg p-4 space-y-6 overflow-auto">
                    <div>
                        <div class="text-xs text-gray-500">Status</div>
                        <div class="font-semibold">CEO / GM Approval</div>
                    </div>

                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Module</span>
                            <span class="font-medium">
                                {{ selectedSlip.source_type?.includes('Claim') ? 'Claim' : (selectedSlip.source_type?.includes('ApInvoice') ? 'AP Invoice' : 'Topup') }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Requester</span>
                            <span class="font-medium">{{ slipRequester(selectedSlip) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Amount</span>
                            <span class="font-medium">{{ formatCurrency(selectedSlip.amount ?? 0) }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="space-y-2">
                        <label class="text-xs text-gray-500">Rejection Reason</label>
                        <textarea
                            v-model="rejectForm.reason"
                            rows="3"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Enter reason for rejection"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <button
                            class="w-full py-2 bg-green-600 text-white rounded disabled:opacity-40"
                            :disabled="rejectForm.processing"
                            @click="submitApproval"
                        >
                            Approve
                        </button>

                        <button
                            class="w-full py-2 bg-red-600 text-white rounded disabled:opacity-40"
                            :disabled="rejectForm.processing || !rejectForm.reason"
                            @click="submitRejection"
                        >
                            Reject
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div
        v-if="showArrangementModal && selectedSlip"
        class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center no-print"
    >
        <div class="bg-gray-100 w-full h-full md:h-[90vh] md:w-[90vw] rounded shadow-xl overflow-hidden">
            <div class="sticky top-0 bg-white border-b px-6 py-3 flex items-center">
                <h2 class="font-semibold text-lg">
                    Payment Arrangement - {{ selectedSlip.slip_no ?? '-' }}
                </h2>

                <div class="ml-auto flex items-center gap-3">
                    <button @click="window.print()">Print / Save PDF</button>
                    <button @click="closeArrangementModal" class="text-red-600">
                        <i class="mdi mdi-close text-xl"></i>
                    </button>
                </div>
            </div>

            <div class="flex h-[calc(100%-56px)] gap-6 p-6">
                <div class="flex-1 overflow-auto">
                    <ApPaymentSlipA4
                        v-if="selectedSlip.source_type?.includes('ApInvoice')"
                        :invoice="selectedSlip.source"
                        :slip="selectedSlip"
                    />
                    <ClaimPaymentSlipA4
                        v-else-if="selectedSlip.source_type?.includes('Claim')"
                        :slip="selectedSlip"
                    />
                    <PaymentSlipA4
                        v-else
                        :slip="selectedSlip"
                    />
                </div>

                <div class="w-[360px] bg-white border rounded-lg p-4 space-y-6 overflow-auto">
                    <div>
                        <div class="text-xs text-gray-500">Status</div>
                        <div class="font-semibold">Payment Arrangement</div>
                    </div>

                    <div class="space-y-2 text-sm text-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Requester</span>
                            <span class="font-medium">{{ slipRequester(selectedSlip) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Amount</span>
                            <span class="font-medium">{{ formatCurrency(selectedSlip.amount ?? 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Voucher</span>
                            <span class="font-medium">{{ selectedSlip.attachments_count > 0 ? 'Uploaded' : 'Not uploaded' }}</span>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="space-y-2">
                        <label class="text-xs text-gray-500">Payment Reference</label>
                        <input
                            v-model="arrangementForm.payment_ref"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Payment reference"
                        />
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs text-gray-500">Payment Proof / Voucher</label>
                        <div class="space-y-2">
                            <label
                                for="arrangement-files"
                                class="block cursor-pointer rounded-lg border-2 border-dashed border-blue-300 bg-blue-50/50 p-4 text-center hover:bg-blue-50 transition"
                            >
                                <div class="text-sm font-medium text-blue-700">Upload proof files</div>
                                <div class="mt-1 text-xs text-gray-500">Click to choose multiple files</div>
                            </label>
                            <input id="arrangement-files" class="hidden" type="file" multiple @change="handleArrangementFiles" />

                            <div v-if="arrangementForm.attachments.length" class="rounded-md border border-gray-200 bg-white p-2 space-y-1 max-h-36 overflow-auto">
                                <div
                                    v-for="(file, index) in arrangementForm.attachments"
                                    :key="`${file.name}-${index}`"
                                    class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 text-xs"
                                >
                                    <div class="truncate pr-2">
                                        <span>{{ file.name }}</span>
                                        <span class="ml-1 text-gray-500">({{ formatFileSize(file.size) }})</span>
                                    </div>
                                    <button type="button" class="text-red-600 hover:text-red-700" @click="removeArrangementFile(index)">Remove</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs text-gray-500">Cancel Reason</label>
                        <textarea
                            v-model="arrangementForm.cancel_reason"
                            rows="3"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Reason for cancellation"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-3">
                        <button
                            class="w-full py-2 bg-blue-600 text-white rounded disabled:opacity-40"
                            :disabled="arrangementSubmitting || !arrangementForm.payment_ref || !arrangementForm.attachments.length"
                            @click="arrangementMarkPaid"
                        >
                            Upload Proof & Mark Paid
                        </button>

                        <button
                            class="w-full py-2 bg-red-600 text-white rounded disabled:opacity-40"
                            :disabled="arrangementSubmitting || !arrangementForm.cancel_reason"
                            @click="arrangementCancelSlip"
                        >
                            Cancel Slip
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
