<script setup>
import { computed, watch, inject } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    stock: Object,
    warehouses: Array,
})

const emit = defineEmits(['close'])
const toast = inject('toast', null)

function makeRow() {
    return {
        quantity: '',
        serial_number: '',
        remark: '',
    }
}

const form = useForm({
    from_warehouse_id: null,
    to_warehouse_id: '',
    purchase_order_item_id: null,
    remark: '',
    items: [makeRow()],
})

function addRow() {
    form.items.push(makeRow())
}

function removeRow(index) {
    if (form.items.length <= 1) {
        form.items[0] = makeRow()
        return
    }

    form.items.splice(index, 1)
}

function rowError(index, field) {
    return form.errors[`items.${index}.${field}`]
}

watch(
    () => props.stock,
    (stock) => {
        form.reset()
        form.clearErrors()
        form.items = [makeRow()]

        if (stock) {
            form.from_warehouse_id = stock.warehouse_id
            form.purchase_order_item_id = stock.purchase_order_item_id
        }
    },
    { immediate: true }
)

const canSubmit = computed(() => {
    if (!form.to_warehouse_id) return false
    if (!Array.isArray(form.items) || form.items.length === 0) return false

    return form.items.every((row) => {
        if (!row.quantity || Number(row.quantity) <= 0) return false
        if (!String(row.serial_number || '').trim()) return false
        return true
    })
})

function submit() {
    if (!canSubmit.value) return

    form.post(route('inventory.stocks.transfer'), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock transferred', 'success')

            router.reload({
                only: ['stocks'],
            })
            emit('close')
        },
        onError: () => {
            toast?.value?.show('Transfer failed', 'error')
        },
    })
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="z-10 max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Transfer Stock</h3>
                    <p class="text-sm text-gray-500">Add one or more transfer rows. Each row requires quantity and serial number.</p>
                </div>

                <button
                    type="button"
                    class="rounded border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                    @click="addRow"
                >
                    + Add Row
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">To Warehouse</label>
                    <select
                        v-model="form.to_warehouse_id"
                        class="mt-1 w-full rounded border-gray-300"
                    >
                        <option value="">Select warehouse</option>
                        <option
                            v-for="w in warehouses"
                            :key="w.id"
                            :value="w.id"
                            :disabled="w.id === form.from_warehouse_id"
                        >
                            {{ w.title }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">General Remark (Optional)</label>
                    <input
                        v-model="form.remark"
                        type="text"
                        class="mt-1 w-full rounded border-gray-300"
                        placeholder="Applied when row remark is empty"
                    />
                </div>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(row, index) in form.items"
                    :key="index"
                    class="rounded-xl border border-gray-200 p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Transfer Row {{ index + 1 }}</p>

                        <button
                            type="button"
                            class="rounded border border-red-200 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                            @click="removeRow(index)"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity</label>
                            <input
                                v-model="row.quantity"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="mt-1 w-full rounded border-gray-300"
                            />
                            <p v-if="rowError(index, 'quantity')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'quantity') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Serial Number</label>
                            <input
                                v-model="row.serial_number"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                                placeholder="Required"
                            />
                            <p v-if="rowError(index, 'serial_number')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'serial_number') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Row Remark (Optional)</label>
                            <input
                                v-model="row.remark"
                                type="text"
                                class="mt-1 w-full rounded border-gray-300"
                                placeholder="Optional"
                            />
                            <p v-if="rowError(index, 'remark')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'remark') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button @click="emit('close')" class="rounded border px-3 py-1.5 text-sm">
                    Cancel
                </button>

                <button
                    @click="submit"
                    :disabled="form.processing || !canSubmit"
                    class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50"
                >
                    Transfer
                </button>
            </div>
        </div>
    </div>
</template>
