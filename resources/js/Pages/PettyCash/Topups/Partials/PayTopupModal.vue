<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, inject, ref, watch } from 'vue'
import axios from 'axios'
import PaymentSlipModal from './PaymentSlipModal.vue'

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    topup: {
        type: Object,
        required: false,
    },
})

const emit = defineEmits(['close'])
const toast = inject('toast', null)
const page = usePage()
const companyBankAccounts = computed(() => {
    const accounts = page.props.companyBankAccounts ?? []
    return accounts.filter((account) => account.status === 'active')
})

/* =========================
   FORM
========================= */
const form = useForm({
    attachments: [],
    payment_ref_no: '',
})

const selectedCompanyBankAccountId = ref('')
const slipTopup = ref(null)
const showSlipModal = ref(false)
const slipDetails = ref({
    less_retention: '',
    less_recoupment: '',
    less_material_ob: '',
    less_paid_previously: '',
    payment_slip_remark: '',
})

const hasSlip = computed(() => {
    return Boolean(slipTopup.value?.slip_no || props.topup?.payment_slip?.slip_no)
})

watch(
    () => props.topup,
    (value) => {
        selectedCompanyBankAccountId.value = value?.company_bank_account_id ?? ''
        slipTopup.value = value?.payment_slip ?? null
        const slip = value?.payment_slip ?? {}
        slipDetails.value = {
            less_retention: slip.less_retention ?? '',
            less_recoupment: slip.less_recoupment ?? '',
            less_material_ob: slip.less_material_ob ?? '',
            less_paid_previously: slip.less_paid_previously ?? '',
            payment_slip_remark: slip.payment_slip_remark ?? '',
        }
    },
    { immediate: true }
)

/* =========================
   METHODS
========================= */
function handleFiles(event) {
    form.attachments = Array.from(event.target.files)
}

function submit() {
    if (!props.topup) return

    form.post(
        route('petty-cash.topups.pay', props.topup.id),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset()
                emit('close')
            },
        }
    )
}

async function generateSlip() {
    if (!props.topup) return

    if (!selectedCompanyBankAccountId.value) {
        toast?.value?.show('Please select a company bank account', 'error')
        return
    }

    try {
        const res = await axios.post(
            route('petty-cash.topups.payment-slip', props.topup.id),
            {
                company_bank_account_id: selectedCompanyBankAccountId.value,
                less_retention: slipDetails.value.less_retention || null,
                less_recoupment: slipDetails.value.less_recoupment || null,
                less_material_ob: slipDetails.value.less_material_ob || null,
                less_paid_previously: slipDetails.value.less_paid_previously || null,
                payment_slip_remark: slipDetails.value.payment_slip_remark || null,
            }
        )

        slipTopup.value = res.data.slip
        showSlipModal.value = true
    } catch (error) {
        console.error(error)
        toast?.value?.show('Failed to generate payment slip', 'error')
    }
}

function closeSlip() {
    showSlipModal.value = false
}
</script>

<template>
    <div
        v-if="show && topup"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <!-- BACKDROP -->
        <div
            class="absolute inset-0 bg-black/40"
            @click="emit('close')"
        ></div>

        <!-- MODAL -->
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl z-10">

            <!-- ================= HEADER ================= -->
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                        <i class="mdi mdi-cash-check text-green-600 text-xl"></i>
                    </div>

                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">
                            Mark Top-Up as Paid
                        </h3>
                        <p class="text-xs text-gray-500">
                            Finance action • Balance will be updated
                        </p>
                    </div>
                </div>

                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="emit('close')"
                >
                    ✕
                </button>
            </div>

            <!-- ================= BODY ================= -->
            <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">

                <!-- SUMMARY -->
                <div class="bg-gray-50 border rounded-lg p-4 text-sm space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Amount</span>
                        <span class="font-semibold text-green-600">
                            {{ Number(topup.amount).toFixed(2) }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Wallet</span>
                        <span class="font-medium">
                            {{ topup.wallet?.context_type === 'office'
                                ? 'Office Wallet'
                                : 'Project Wallet'
                            }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Top-Up No</span>
                        <span class="font-mono text-xs">
                            {{ topup.topup_no ?? '-' }}
                        </span>
                    </div>
                </div>

                <!-- WARNING -->
                <div class="flex items-start gap-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm">
                    <i class="mdi mdi-alert-circle-outline text-yellow-600 text-lg mt-0.5"></i>
                    <p class="text-yellow-700">
                        This action is <strong>irreversible</strong>.
                        Once confirmed, the wallet balance will be increased.
                    </p>
                </div>

                <!-- COMPANY BANK ACCOUNT -->
                <div>
                    <label class="text-sm font-medium">
                        Company Bank Account
                    </label>

                    <select
                        v-model="selectedCompanyBankAccountId"
                        class="input w-full mt-1"
                    >
                        <option value="">Select Company Bank Account</option>
                        <option
                            v-for="account in companyBankAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.bank_name }} - {{ account.account_no }}
                        </option>
                    </select>

                    <p v-if="!companyBankAccounts.length" class="text-xs text-gray-400 mt-1">
                        No active company bank accounts found. Add one in Company Profile.
                    </p>
                </div>

                <!-- LESS SECTION -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium">Less - Retention</label>
                        <input
                            v-model="slipDetails.less_retention"
                            type="number"
                            step="0.01"
                            class="input w-full mt-1"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Recoupment Advance Payment</label>
                        <input
                            v-model="slipDetails.less_recoupment"
                            type="number"
                            step="0.01"
                            class="input w-full mt-1"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Payment Material Purchased OB</label>
                        <input
                            v-model="slipDetails.less_material_ob"
                            type="number"
                            step="0.01"
                            class="input w-full mt-1"
                            placeholder="0.00"
                        />
                    </div>
                    <div>
                        <label class="text-sm font-medium">Less - Amount Paid Previously</label>
                        <input
                            v-model="slipDetails.less_paid_previously"
                            type="number"
                            step="0.01"
                            class="input w-full mt-1"
                            placeholder="0.00"
                        />
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium">
                        Payment Slip Remark (Optional)
                    </label>
                    <textarea
                        v-model="slipDetails.payment_slip_remark"
                        rows="2"
                        class="input w-full mt-1"
                        placeholder="Optional"
                    />
                </div>

                <!-- PAYMENT SLIP -->
                <div class="flex items-center justify-between gap-3 bg-gray-50 border rounded-lg p-3">
                    <div>
                        <div class="text-xs text-gray-500">Payment Slip</div>
                        <div class="text-sm font-semibold">
                            {{ slipTopup?.slip_no ?? topup?.payment_slip?.slip_no ?? '-' }}
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn-secondary"
                        :disabled="!selectedCompanyBankAccountId"
                        @click="generateSlip"
                    >
                        Generate Payment Slip
                    </button>
                </div>

                <!-- PAYMENT REF -->
                <div>
                    <label class="text-sm font-medium">
                        Payment Reference No
                    </label>

                    <input
                        v-model="form.payment_ref_no"
                        type="text"
                        class="input w-full mt-1"
                        placeholder="e.g. Maybank-FT-240903-001"
                    />

                    <p class="text-xs text-gray-400 mt-1">
                        Required for audit & reconciliation
                    </p>
                </div>

                <!-- ATTACHMENTS -->
                <div>
                    <label class="text-sm font-medium">
                        Payment Voucher / Slip
                    </label>

                    <div
                        class="mt-2 border-2 border-dashed rounded-lg p-4 text-center cursor-pointer hover:border-green-400 transition"
                    >
                        <input
                            type="file"
                            multiple
                            class="hidden"
                            id="voucherUpload"
                            @change="handleFiles"
                        />
                        <label for="voucherUpload" class="cursor-pointer">
                            <i class="mdi mdi-upload text-2xl text-gray-400"></i>
                            <p class="text-sm text-gray-600 mt-1">
                                Click to upload payment slip(s)
                            </p>
                            <p class="text-xs text-gray-400">
                                At least 1 file • Max 10MB each
                            </p>
                        </label>
                    </div>

                    <!-- FILE LIST -->
                    <ul
                        v-if="form.attachments.length"
                        class="mt-3 text-xs text-gray-700 space-y-1"
                    >
                        <li
                            v-for="(file, i) in form.attachments"
                            :key="i"
                            class="flex justify-between bg-gray-50 rounded px-3 py-1"
                        >
                            <span class="truncate max-w-[220px]">
                                {{ file.name }}
                            </span>
                            <span class="text-gray-400">
                                {{ (file.size / 1024 / 1024).toFixed(2) }} MB
                            </span>
                        </li>
                    </ul>
                </div>

            </div>

            <!-- ================= FOOTER ================= -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="btn-secondary"
                    @click="emit('close')"
                    :disabled="form.processing"
                >
                    Cancel
                </button>

                <button
                    class="btn-primary flex items-center gap-2"
                    :disabled="
                        form.processing ||
                        !hasSlip ||
                        !form.payment_ref_no ||
                        !form.attachments.length
                    "
                    @click="submit"
                >

                    <i class="mdi mdi-check"></i>
                    {{ form.processing ? 'Processing…' : 'Confirm Payment' }}
                </button>
            </div>

        </div>
    </div>

    <PaymentSlipModal
        :show="showSlipModal"
        :slip="slipTopup ?? topup?.payment_slip"
        @close="closeSlip"
    />
</template>
