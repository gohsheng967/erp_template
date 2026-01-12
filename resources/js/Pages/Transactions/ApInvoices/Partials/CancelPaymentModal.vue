<script setup>
import { ref, inject, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

/* =========================
   PROPS / EMITS
========================= */
const props = defineProps({
    show: Boolean,
    payment: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['close', 'success'])

/* =========================
   DEPENDENCIES
========================= */
const toast = inject('toast', null)

/* =========================
   FORM
========================= */
const form = useForm({
    reason: '',
})

/* =========================
   WATCH
========================= */
watch(
    () => props.show,
    (val) => {
        if (val) {
            form.reset()
            form.clearErrors()
        }
    }
)

/* =========================
   METHODS
========================= */
function close() {
    emit('close')
}

function submit() {
    if (form.processing) return

    form.post(
        route('ap-invoices.payments.cancel', props.payment.uuid),
        {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Payment cancelled successfully', 'success')
                emit('success')
                close()
            },
            onError: () => {
                toast?.value?.show('Failed to cancel payment', 'error')
            },
        }
    )
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">

        <!-- BACKDROP -->
        <div
            class="absolute inset-0 bg-black/40"
            @click="close"
        ></div>

        <!-- MODAL -->
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md z-10">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    Cancel Payment
                </h3>
                <p class="text-sm text-gray-500">
                    This will reverse the invoice balance.
                </p>
            </div>

            <!-- BODY -->
            <div class="p-6 space-y-4">

                <!-- WARNING -->
                <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                    This action cannot be undone.
                </div>

                <!-- PAYMENT INFO -->
                <div class="text-sm space-y-1">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Amount</span>
                        <span class="font-medium">
                            {{ payment.amount }}
                        </span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Date</span>
                        <span>
                            {{ payment.payment_date }}
                        </span>
                    </div>
                </div>

                <!-- CANCEL REASON -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Cancel Reason <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        v-model="form.reason"
                        rows="3"
                        placeholder="Reason for cancelling this payment"
                        class="mt-1 w-full rounded-md border-gray-300 focus:ring-red-500 focus:border-red-500"
                    ></textarea>

                    <p
                        v-if="form.errors.reason"
                        class="text-xs text-red-600 mt-1"
                    >
                        {{ form.errors.reason }}
                    </p>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end gap-3 bg-gray-50 rounded-b-xl">
                <button
                    type="button"
                    @click="close"
                    class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-100"
                >
                    Keep Payment
                </button>

                <button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                    class="px-5 py-2 rounded-md bg-red-600 text-white hover:bg-red-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Cancelling…' : 'Cancel Payment' }}
                </button>
            </div>

        </div>
    </div>
</template>
