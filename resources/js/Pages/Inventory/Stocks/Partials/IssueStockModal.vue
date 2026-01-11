<script setup>
import { watch, inject } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    stock: Object, // selected stock row
})

const emit = defineEmits(['close'])

const toast = inject('toast', null)

const form = useForm({
    warehouse_id: null,
    purchase_order_item_id: null,
    quantity: '',
    remark: '',
})

watch(
    () => props.stock,
    (stock) => {
        form.reset()
        form.clearErrors()

        if (stock) {
            form.warehouse_id = stock.warehouse_id
            form.purchase_order_item_id = stock.purchase_order_item_id
        }
    },
    { immediate: true }
)

function submit() {
    form.post(route('inventory.stocks.issue'), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock issued', 'success')

            // 🔄 reload inventory stock page
            router.reload({
                only: ['stocks'],
            })

            emit('close')
        },
        onError: () => {
            toast?.value?.show('Failed to issue stock', 'error')
        },
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="bg-white rounded-lg shadow-lg w-full max-w-md z-10 p-6">
            <h3 class="text-lg font-semibold mb-4">
                Issue Stock
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium">
                        Quantity
                    </label>
                    <input
                        v-model="form.quantity"
                        type="number"
                        step="0.01"
                        class="mt-1 w-full rounded border-gray-300"
                    />
                    <p v-if="form.errors.quantity" class="text-sm text-red-600">
                        {{ form.errors.quantity }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium">
                        Remark
                    </label>
                    <textarea
                        v-model="form.remark"
                        rows="2"
                        class="mt-1 w-full rounded border-gray-300"
                    ></textarea>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 border rounded"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    :disabled="form.processing || !form.quantity"
                    class="px-4 py-2 bg-red-600 text-white rounded"
                >
                    Issue
                </button>
            </div>
        </div>
    </div>
</template>
