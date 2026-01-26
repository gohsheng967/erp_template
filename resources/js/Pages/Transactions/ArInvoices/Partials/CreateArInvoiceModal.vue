<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    projects: Array,
    customers: Array,
    defaultCustomerId: [String, Number, null],
})

const emit = defineEmits(['close'])

const form = useForm({
    project_id: '',
    customer_id: '',
    title: '',
    total_amount: '',
    payment_term_days: '',
})

watch(
    () => props.show,
    (isOpen) => {
        if (!isOpen) return
        if (props.defaultCustomerId) {
            form.customer_id = props.defaultCustomerId
        }
    }
)

function submit() {
    form.post(route('ar-invoices.store'), {
        onSuccess: () => {
            emit('close')
            form.reset()
        }
    })
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="bg-white rounded-lg shadow-lg w-full max-w-md z-10 p-6">
            <h3 class="text-lg font-semibold mb-4">
                Create AR Invoice (Draft)
            </h3>

            <p class="text-sm text-gray-500 mb-4">
                You can add invoice items after creating the invoice.
            </p>

            <div class="space-y-4">

                <!-- CUSTOMER -->
                <div>
                    <label class="text-sm font-medium">Customer</label>
                    <select
                        v-model="form.customer_id"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option value="">Select Customer</option>
                        <option
                            v-for="c in customers"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                    <div
                        v-if="form.errors.customer_id"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.customer_id }}
                    </div>
                </div>

                <!-- PROJECT -->
                <div>
                    <label class="text-sm font-medium">Project</label>
                    <select
                        v-model="form.project_id"
                        class="w-full border rounded px-3 py-2"
                    >
                        <option value="">Others / No Project</option>
                        <option
                            v-for="p in projects"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <!-- TITLE -->
                <div>
                    <label class="text-sm font-medium">Title</label>
                    <input
                        type="text"
                        v-model="form.title"
                        class="w-full border rounded px-3 py-2"
                        placeholder="UAT Invoice"
                    />
                    <div
                        v-if="form.errors.title"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.title }}
                    </div>
                </div>

                <!-- TOTAL AMOUNT -->
                <div>
                    <label class="text-sm font-medium">Total Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        v-model="form.total_amount"
                        class="w-full border rounded px-3 py-2"
                        placeholder="0.00"
                    />
                    <div
                        v-if="form.errors.total_amount"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.total_amount }}
                    </div>
                </div>

                <!-- PAYMENT TERM -->
                <div>
                    <label class="text-sm font-medium">Payment Term (Days)</label>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        v-model="form.payment_term_days"
                        class="w-full border rounded px-3 py-2"
                        placeholder="30"
                    />
                    <div
                        v-if="form.errors.payment_term_days"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.payment_term_days }}
                    </div>
                </div>

            </div>

            <!-- ACTIONS -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 bg-gray-200 rounded"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    class="px-4 py-2 bg-indigo-600 text-white rounded"
                    :disabled="form.processing"
                >
                    Create Draft
                </button>
            </div>
        </div>
    </div>
</template>
