<script setup>
import { computed, inject, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    stock: Object,
    projects: {
        type: Array,
        default: () => [],
    },
    stockCategories: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])
const toast = inject('toast', null)

function makeRow() {
    return {
        quantity: '',
        serial_number: '',
        stock_category: '',
        issue_destination_type: 'office',
        project_id: '',
        site_id: '',
        purpose: '',
        remark: '',
    }
}

const form = useForm({
    warehouse_id: null,
    purchase_order_item_id: null,
    items: [makeRow()],
})

function rowSites(row) {
    const projectId = Number(row.project_id)

    if (!projectId) {
        return []
    }

    const project = props.projects.find((item) => Number(item.id) === projectId)

    return project?.sites ?? []
}

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

const canSubmit = computed(() => {
    if (!Array.isArray(form.items) || form.items.length === 0) {
        return false
    }

    return form.items.every((row) => {
        if (!row.quantity || Number(row.quantity) <= 0) return false
        if (!String(row.serial_number || '').trim()) return false
        if (!String(row.stock_category || '').trim()) return false
        if (!String(row.purpose || '').trim()) return false

        if (row.issue_destination_type === 'project') {
            return Boolean(row.project_id) && Boolean(row.site_id)
        }

        return true
    })
})

function submit() {
    if (!canSubmit.value) {
        return
    }

    form.post(route('inventory.stocks.issue'), {
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Stock issued', 'success')
            router.reload({ only: ['stocks'] })
            emit('close')
        },
        onError: () => {
            toast?.value?.show('Failed to issue stock', 'error')
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
                    <h3 class="text-lg font-semibold text-gray-900">Issue Stock</h3>
                    <p class="text-sm text-gray-500">Add one or more issue rows. Each row has its own serial, destination, and purpose.</p>
                </div>

                <button
                    type="button"
                    class="rounded border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                    @click="addRow"
                >
                    + Add Row
                </button>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(row, index) in form.items"
                    :key="index"
                    class="rounded-xl border border-gray-200 p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Issue Row {{ index + 1 }}</p>

                        <button
                            type="button"
                            class="rounded border border-red-200 px-2 py-0.5 text-xs text-red-600 hover:bg-red-50"
                            @click="removeRow(index)"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            <label class="block text-sm font-medium text-gray-700">Stock Category</label>
                            <select
                                v-model="row.stock_category"
                                class="mt-1 w-full rounded border-gray-300"
                            >
                                <option disabled value="">Select stock category</option>
                                <option
                                    v-for="category in stockCategories"
                                    :key="category"
                                    :value="category"
                                >
                                    {{ category }}
                                </option>
                            </select>
                            <p v-if="rowError(index, 'stock_category')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'stock_category') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Issue To</label>
                            <select
                                v-model="row.issue_destination_type"
                                class="mt-1 w-full rounded border-gray-300"
                                @change="row.issue_destination_type === 'office' ? (row.project_id = '', row.site_id = '') : null"
                            >
                                <option value="office">Office</option>
                                <option value="project">Project</option>
                            </select>
                            <p v-if="rowError(index, 'issue_destination_type')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'issue_destination_type') }}</p>
                        </div>

                        <div v-if="row.issue_destination_type === 'project'">
                            <label class="block text-sm font-medium text-gray-700">Project</label>
                            <select
                                v-model="row.project_id"
                                class="mt-1 w-full rounded border-gray-300"
                                @change="row.site_id = ''"
                            >
                                <option disabled value="">Select project</option>
                                <option
                                    v-for="project in projects"
                                    :key="project.id"
                                    :value="project.id"
                                >
                                    {{ project.code ? project.code + ' - ' : '' }}{{ project.name }}
                                </option>
                            </select>
                            <p v-if="rowError(index, 'project_id')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'project_id') }}</p>
                        </div>

                        <div v-if="row.issue_destination_type === 'project'">
                            <label class="block text-sm font-medium text-gray-700">Site</label>
                            <select
                                v-model="row.site_id"
                                :disabled="!row.project_id"
                                class="mt-1 w-full rounded border-gray-300 disabled:bg-gray-100"
                            >
                                <option disabled value="">Select site</option>
                                <option
                                    v-for="site in rowSites(row)"
                                    :key="site.id"
                                    :value="site.id"
                                >
                                    {{ site.site_name }}
                                </option>
                            </select>
                            <p v-if="rowError(index, 'site_id')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'site_id') }}</p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Purpose</label>
                            <textarea
                                v-model="row.purpose"
                                rows="2"
                                class="mt-1 w-full rounded border-gray-300"
                                placeholder="State why this stock is being issued"
                            ></textarea>
                            <p v-if="rowError(index, 'purpose')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'purpose') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Remark (Optional)</label>
                            <textarea
                                v-model="row.remark"
                                rows="2"
                                class="mt-1 w-full rounded border-gray-300"
                            ></textarea>
                            <p v-if="rowError(index, 'remark')" class="mt-1 text-xs text-red-600">{{ rowError(index, 'remark') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button @click="emit('close')" class="rounded border px-3 py-1.5 text-sm">Cancel</button>
                <button
                    @click="submit"
                    :disabled="form.processing || !canSubmit"
                    class="rounded bg-red-600 px-3 py-1.5 text-sm text-white disabled:opacity-50"
                >
                    Issue
                </button>
            </div>
        </div>
    </div>
</template>
