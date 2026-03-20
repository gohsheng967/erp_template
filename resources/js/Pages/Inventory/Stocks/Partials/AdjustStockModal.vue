<script setup>
import { computed, watch, inject } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    stock: Object,
})

const emit = defineEmits(['close'])
const toast = inject('toast', null)

function makeRow() {
    return {
        quantity: '1',
        serial_number: '',
        remark: '',
    }
}

const form = useForm({
    warehouse_id: null,
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
            form.warehouse_id = stock.warehouse_id
            form.purchase_order_item_id = stock.purchase_order_item_id
        }
    },
    { immediate: true }
)

const currentQty = computed(() => Number(props.stock?.quantity ?? 0))
const targetQty = computed(() =>
    (form.items ?? []).reduce((sum, row) => sum + Number(row.quantity || 0), 0)
)
const qtyDiff = computed(() => targetQty.value - currentQty.value)

const canSubmit = computed(() => {
    if (!String(form.remark || '').trim()) return false
    if (!Array.isArray(form.items) || form.items.length === 0) return false

    return form.items.every((row) => {
        if (!row.quantity || Number(row.quantity) <= 0) return false
        if (!String(row.serial_number || '').trim()) return false
        return true
    })
})

function submit() {
    if (!canSubmit.value) return

    form.post(route('inventory.stocks.adjust'), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock adjusted', 'success')

            router.reload({
                only: ['stocks'],
            })
            emit('close')
        },
        onError: () => {
            toast?.value?.show('Adjustment failed', 'error')
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
                    <h3 class="text-lg font-semibold text-gray-900">Adjust Stock</h3>
                    <p class="text-sm text-gray-500">List all actual serial-number rows. Final stock becomes the total quantity from rows.</p>
                </div>

                <button
                    type="button"
                    class="rounded border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                    @click="addRow"
                >
                    + Add Row
                </button>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded border border-gray-200 bg-gray-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Current Qty</p>
                    <p class="text-lg font-semibold text-gray-800">{{ currentQty }}</p>
                </div>
                <div class="rounded border border-indigo-200 bg-indigo-50 px-3 py-2">
                    <p class="text-xs uppercase tracking-wide text-indigo-600">Target Qty</p>
                    <p class="text-lg font-semibold text-indigo-800">{{ targetQty }}</p>
                </div>
                <div class="rounded border px-3 py-2" :class="qtyDiff >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'">
                    <p class="text-xs uppercase tracking-wide" :class="qtyDiff >= 0 ? 'text-emerald-700' : 'text-red-700'">Difference</p>
                    <p class="text-lg font-semibold" :class="qtyDiff >= 0 ? 'text-emerald-800' : 'text-red-800'">{{ qtyDiff > 0 ? '+' : '' }}{{ qtyDiff }}</p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700">Adjustment Reason (Required)</label>
                <textarea
                    v-model="form.remark"
                    rows="2"
                    class="mt-1 w-full rounded border-gray-300"
                    placeholder="Why are we adjusting stock count?"
                ></textarea>
                <p v-if="form.errors.remark" class="mt-1 text-xs text-red-600">{{ form.errors.remark }}</p>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(row, index) in form.items"
                    :key="index"
                    class="rounded-xl border border-gray-200 p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Actual Item Row {{ index + 1 }}</p>

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
                            />
                            <p v-if="rowError(index, 'remark')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'remark') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button
                    @click="emit('close')"
                    class="rounded border px-3 py-1.5 text-sm"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    :disabled="form.processing || !canSubmit"
                    class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50"
                >
                    Adjust
                </button>
            </div>
        </div>
    </div>
</template>
