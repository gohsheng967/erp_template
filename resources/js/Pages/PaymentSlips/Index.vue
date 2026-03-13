<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { useFormat } from '@/Composables/useFormat'
import StandardFilterBar from '@/Components/Filters/StandardFilterBar.vue'
import PaymentSlipModal from '@/Pages/PettyCash/Topups/Partials/PaymentSlipModal.vue'
import ApPaymentSlipModal from '@/Pages/Transactions/ApInvoices/Partials/ApPaymentSlipModal.vue'
import ClaimPaymentSlipModal from '@/Pages/Transactions/Claims/Partials/ClaimPaymentSlipModal.vue'
import Modal from '@/Components/Modal.vue'

const page = usePage()
const { formatCurrency, formatDateTime } = useFormat()

const projects = computed(() => page.props.projects ?? [])
const companyBankAccounts = computed(() => page.props.companyBankAccounts ?? [])
const filters = computed(() => page.props.filters ?? {})
const counts = computed(() => page.props.counts ?? { pending: 0, processing: 0, paid: 0 })
const slipsByTab = computed(() => page.props.slips ?? {})
const pendingRows = computed(() => page.props.pending ?? [])

const tabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'processing', label: 'Processing' },
    { key: 'paid', label: 'Paid' },
]

const activeTab = ref(page.props.activeTab ?? 'pending')

const filterForm = useForm({
    tab: activeTab.value,
    search: filters.value.search ?? '',
    project_id: filters.value.project_id ?? '',
    voucher: filters.value.voucher ?? '',
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
    filterForm.voucher = ''
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
                description="Filter payment workflow by project, date, voucher, or keyword."
                @apply="applyFilters"
                @reset="resetFilters"
            >
                <div class="flex flex-col w-full md:w-1/3">
                    <label class="text-sm font-medium">Search</label>
                    <input
                        v-model="filterForm.search"
                        class="border rounded px-3 py-2"
                        placeholder="Slip No / Claim No / Top-up No / Invoice / Requester"
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

                <div v-if="activeTab !== 'pending'" class="flex flex-col w-40">
                    <label class="text-sm font-medium">Voucher</label>
                    <select v-model="filterForm.voucher" class="border rounded px-3 py-2">
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
                            <th class="px-4 py-3">Ref</th>
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
                                {{ activeTab === 'pending' ? (row.reference_no || '-') : (row.slip_no || '-') }}
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
                                    <button class="text-gray-600 hover:text-gray-800" title="View" @click="openSlip(row)">
                                        <i class="mdi mdi-file-eye-outline text-lg"></i>
                                    </button>
                                    <button class="text-green-600 hover:text-green-800" title="Upload Voucher" @click="openUpload(row)">
                                        <i class="mdi mdi-upload text-lg"></i>
                                    </button>
                                    <button class="text-blue-600 hover:text-blue-800" title="Mark Paid" @click="openMarkPaid(row)">
                                        <i class="mdi mdi-check-circle-outline text-lg"></i>
                                    </button>
                                    <button
                                        class="text-red-600 hover:text-red-800"
                                        title="Cancel Slip"
                                        :disabled="!row.can_cancel"
                                        :class="!row.can_cancel ? 'opacity-50 cursor-not-allowed' : ''"
                                        @click="openCancel(row)"
                                    >
                                        <i class="mdi mdi-cancel text-lg"></i>
                                    </button>
                                </template>

                                <template v-else>
                                    <button class="text-gray-600 hover:text-gray-800" title="View" @click="openSlip(row)">
                                        <i class="mdi mdi-file-eye-outline text-lg"></i>
                                    </button>
                                </template>
                            </td>
                        </tr>

                        <tr v-if="!currentRows.length">
                            <td
                                :colspan="activeTab === 'pending' ? 7 : 9"
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
            <input type="file" multiple @change="handleUploadFiles" />
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
                <input class="mt-1" type="file" multiple @change="handleMarkPaidFiles" />
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
</template>
