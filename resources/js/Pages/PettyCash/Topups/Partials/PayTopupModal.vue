<script setup>
import { useForm } from '@inertiajs/vue3'

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

/* =========================
   FORM
========================= */
const form = useForm({
    attachments: [],
    payment_ref_no: '',
})

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
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg z-10">

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
            <div class="p-6 space-y-5">

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
</template>
