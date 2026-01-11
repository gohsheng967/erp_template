<script setup>
import { ref, computed, inject } from 'vue'
import axios from 'axios'

const toast = inject('toast', null)

const emit = defineEmits(['close', 'saved'])

const props = defineProps({
    purchaseOrder: Object,
    warehouses: Array, // [{ id, title, address }]
})

/* ======================
   FORM STATE
====================== */
const submitting = ref(false)

const form = ref({
    delivery_date: new Date().toISOString().slice(0, 10),
    title: '',
    description: '',
    status: 'transit',      // ✅ default
    delivery_type: 'partial',
    warehouse_id: '',
    items: [],
    attachments: [],
})

/* ======================
   INIT PO ITEMS
====================== */
props.purchaseOrder.items.forEach(item => {
    form.value.items.push({
        purchase_order_item_id: item.id,
        item_name: item.item_name,
        ordered_qty: Number(item.quantity),
        delivered_qty: Number(item.delivered_quantity ?? 0),
        quantity: 0,
        destination: '',
        remark: '',
    })
})

/* ======================
   FILE UPLOAD HELPERS
====================== */
function handleFiles(e) {
    const files = Array.from(e.target.files)
    files.forEach(file => form.value.attachments.push(file))
    e.target.value = null
}

function removeFile(index) {
    form.value.attachments.splice(index, 1)
}

function fileIcon(file) {
    if (file.type.includes('pdf')) return 'mdi-file-pdf-box'
    if (file.type.includes('image')) return 'mdi-file-image'
    if (file.type.includes('word')) return 'mdi-file-word-box'
    if (file.type.includes('excel')) return 'mdi-file-excel-box'
    return 'mdi-file-outline'
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1024 / 1024).toFixed(1) + ' MB'
}

/* ======================
   VALIDATION
====================== */
const canSubmit = computed(() => {
    if (!form.value.title) return false

    if (form.value.status === 'warehouse') {
        return (
            form.value.warehouse_id &&
            form.value.items.some(i => Number(i.quantity) > 0)
        )
    }

    return true
})

/* ======================
   SUBMIT
====================== */
async function submit() {
    if (!canSubmit.value || submitting.value) return

    submitting.value = true

    try {
        const fd = new FormData()

        fd.append('delivery_date', form.value.delivery_date)
        fd.append('title', form.value.title)
        fd.append('description', form.value.description)
        fd.append('status', form.value.status)
        fd.append('delivery_type', form.value.delivery_type)

        if (form.value.status === 'warehouse') {
            fd.append('warehouse_id', form.value.warehouse_id)
        }

        form.value.items
            .filter(i => Number(i.quantity) > 0)
            .forEach((i, idx) => {
                fd.append(`items[${idx}][purchase_order_item_id]`, i.purchase_order_item_id)
                fd.append(`items[${idx}][quantity]`, i.quantity)
                fd.append(`items[${idx}][destination]`, i.destination)
                fd.append(`items[${idx}][remark]`, i.remark)
            })

        form.value.attachments.forEach((file, i) => {
            fd.append(`attachments[${i}]`, file)
        })

        await axios.post(
            route('purchase-orders.deliveries.store', props.purchaseOrder.uuid),
            fd
        )

        toast?.value.show('Delivery added successfully', 'success')
        emit('saved')

    } catch (e) {
        toast?.value.show(e, 'error')
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center">
        <div
            class="bg-white rounded-xl w-full max-w-2xl p-5
                   space-y-4 max-h-[90vh] overflow-y-auto"
        >

            <!-- HEADER -->
            <div class="flex items-center justify-between border-b pb-2">
                <h2 class="text-base font-semibold">
                    Add Delivery
                </h2>
                <button
                    @click="emit('close')"
                    class="text-gray-400 hover:text-gray-600"
                >
                    <i class="mdi mdi-close"></i>
                </button>
            </div>

            <!-- BASIC INFO -->
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs text-gray-500">Delivery Date</label>
                    <input
                        type="date"
                        v-model="form.delivery_date"
                        class="w-full border rounded px-2 py-1 text-sm"
                    />
                </div>

                <div>
                    <label class="text-xs text-gray-500">Status</label>
                    <select
                        v-model="form.status"
                        class="w-full border rounded px-2 py-1 text-sm"
                    >
                        <option value="transit">Transit</option>
                        <option value="warehouse">Warehouse / Office</option>
                        <option value="returned">Returned</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="text-xs text-gray-500">Title</label>
                <input
                    v-model="form.title"
                    class="w-full border rounded px-2 py-1 text-sm"
                    placeholder="e.g. First batch delivery"
                />
            </div>

            <div>
                <label class="text-xs text-gray-500">Description</label>
                <textarea
                    v-model="form.description"
                    rows="2"
                    class="w-full border rounded px-2 py-1 text-sm"
                ></textarea>
            </div>

            <!-- WAREHOUSE -->
            <div v-if="form.status === 'warehouse'">
                <label class="text-xs text-gray-500">Warehouse / Office</label>
                <select
                    v-model="form.warehouse_id"
                    class="w-full border rounded px-2 py-1 text-sm"
                >
                    <option disabled value="">Select warehouse / office</option>
                    <option
                        v-for="w in warehouses"
                        :key="w.id"
                        :value="w.id"
                    >
                        {{ w.title }} — {{ w.address }}
                    </option>
                </select>
            </div>

            <!-- ITEMS -->
            <div v-if="form.status === 'warehouse'">
                <p class="text-xs font-semibold text-gray-600 mb-1">
                    Delivered Items
                </p>

                <table class="w-full text-sm">
                    <thead class="text-gray-400 text-xs">
                        <tr>
                            <th class="text-left">Item</th>
                            <th class="text-center">Remaining</th>
                            <th class="text-center">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in form.items"
                            :key="item.purchase_order_item_id"
                            class="border-t"
                        >
                            <td class="py-1">{{ item.item_name }}</td>
                            <td class="text-center">
                                {{ item.ordered_qty - item.delivered_qty }}
                            </td>
                            <td class="text-center">
                                <input
                                    type="number"
                                    min="0"
                                    :max="item.ordered_qty - item.delivered_qty"
                                    v-model.number="item.quantity"
                                    class="w-16 border rounded px-1 py-0.5 text-sm"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ATTACHMENTS -->
            <div>
                <label class="text-xs text-gray-500 mb-1 block">
                    Attachments
                </label>

                <!-- DROP ZONE -->
                <label
                    class="flex flex-col items-center justify-center
                           border-2 border-dashed rounded-lg
                           px-4 py-5 cursor-pointer
                           text-sm text-gray-500
                           hover:border-indigo-400 hover:text-indigo-600
                           transition"
                >
                    <i class="mdi mdi-cloud-upload-outline text-3xl mb-2"></i>
                    <span class="font-medium">
                        Click to upload or drag & drop
                    </span>
                    <span class="text-xs text-gray-400 mt-1">
                        PDF, images, documents
                    </span>

                    <input
                        type="file"
                        multiple
                        class="hidden"
                        @change="handleFiles"
                    />
                </label>

                <!-- FILE LIST -->
                <div
                    v-if="form.attachments.length"
                    class="mt-3 space-y-2"
                >
                    <div
                        v-for="(file, index) in form.attachments"
                        :key="index"
                        class="flex items-center justify-between
                               bg-gray-50 border rounded-md px-3 py-2"
                    >
                        <div class="flex items-center gap-3">
                            <i
                                class="mdi text-xl"
                                :class="fileIcon(file)"
                            ></i>

                            <div class="text-sm">
                                <p class="font-medium text-gray-700">
                                    {{ file.name }}
                                </p>
                                <p class="text-xs text-gray-400">
                                    {{ formatSize(file.size) }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="text-gray-400 hover:text-red-500"
                            @click="removeFile(index)"
                        >
                            <i class="mdi mdi-close-circle-outline text-lg"></i>
                        </button>
                    </div>
                </div>

                <p class="text-xs text-gray-400 mt-2">
                    Delivery order, photos, return proof
                </p>
            </div>

            <!-- ACTIONS -->
            <div class="flex justify-end gap-3 pt-3 border-t">
                <button
                    class="px-3 py-1 text-sm text-gray-600"
                    @click="emit('close')"
                >
                    Cancel
                </button>

                <button
                    class="px-4 py-1.5 rounded bg-indigo-600 text-white text-sm
                           disabled:opacity-50"
                    :disabled="!canSubmit || submitting"
                    @click="submit"
                >
                    Save Delivery
                </button>
            </div>

        </div>
    </div>
</template>
