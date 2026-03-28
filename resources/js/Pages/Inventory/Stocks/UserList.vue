<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    pending_rows: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
})

const selectedRow = ref(null)
const showModal = ref(false)
const form = useForm({
    action: '',
    transfer_to_user_id: '',
    remark: '',
})

const actionLabel = computed(() => {
    if (form.action === 'transfer') return 'Transfer'
    if (form.action === 'used') return 'Mark as Used'
    if (form.action === 'disposal') return 'Mark as Disposal'
    return 'Update'
})

function openAction(row, action) {
    selectedRow.value = row
    form.reset()
    form.clearErrors()
    form.action = action
    showModal.value = true
}

function submit() {
    if (!selectedRow.value) return

    form.post(route('inventory.stocks.usage', selectedRow.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            selectedRow.value = null
        },
    })
}

function approveRow(row) {
    form.post(route('inventory.stocks.approve-user', row.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded border border-gray-300 px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="router.visit(route('inventory.stocks.index'))"
                    >
                        <i class="mdi mdi-arrow-left"></i>
                        Back
                    </button>
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">My Item List</h2>
                        <p class="text-sm text-gray-500">Items currently assigned to you.</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="mb-4 overflow-x-auto rounded-xl border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-2">
                <h3 class="text-sm font-semibold text-amber-800">Pending My Approval</h3>
            </div>
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Warehouse</th>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-left">SN</th>
                        <th class="px-3 py-2 text-left">Issuer</th>
                        <th class="px-3 py-2 text-left">Purpose</th>
                        <th class="px-3 py-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in pending_rows" :key="`pending-${row.id}`">
                        <td class="px-3 py-2">{{ new Date(row.created_at).toLocaleString() }}</td>
                        <td class="px-3 py-2">{{ row.warehouse?.title || '-' }}</td>
                        <td class="px-3 py-2">{{ row.purchase_order_item?.item_name || '-' }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ row.quantity }}</td>
                        <td class="px-3 py-2">{{ row.serial_number || '-' }}</td>
                        <td class="px-3 py-2">{{ row.issuer?.name || '-' }}</td>
                        <td class="px-3 py-2">{{ row.purpose || '-' }}</td>
                        <td class="px-3 py-2 text-center">
                            <button
                                type="button"
                                class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                :disabled="form.processing"
                                @click="approveRow(row)"
                            >
                                Approve Receive
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!pending_rows.length">
                        <td colspan="8" class="px-3 py-6 text-center text-gray-500">No pending approvals.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Warehouse</th>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-left">SN</th>
                        <th class="px-3 py-2 text-left">Issuer</th>
                        <th class="px-3 py-2 text-left">Purpose</th>
                        <th class="px-3 py-2 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="row in rows" :key="row.id">
                        <td class="px-3 py-2">{{ new Date(row.created_at).toLocaleString() }}</td>
                        <td class="px-3 py-2">{{ row.warehouse?.title || '-' }}</td>
                        <td class="px-3 py-2">{{ row.purchase_order_item?.item_name || '-' }}</td>
                        <td class="px-3 py-2 text-right font-medium">{{ row.quantity }}</td>
                        <td class="px-3 py-2">{{ row.serial_number || '-' }}</td>
                        <td class="px-3 py-2">{{ row.issuer?.name || '-' }}</td>
                        <td class="px-3 py-2">{{ row.purpose || '-' }}</td>
                        <td class="px-3 py-2">
                            <div class="flex justify-center gap-1">
                                <button
                                    type="button"
                                    class="rounded border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                                    @click="openAction(row, 'transfer')"
                                >
                                    Transfer
                                </button>
                                <button
                                    type="button"
                                    class="rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                    @click="openAction(row, 'used')"
                                >
                                    Used
                                </button>
                                <button
                                    type="button"
                                    class="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                    @click="openAction(row, 'disposal')"
                                >
                                    Disposal
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="8" class="px-3 py-8 text-center text-gray-500">No active assigned items.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
            <div class="absolute inset-0 bg-black/40" @click="showModal = false"></div>
            <div class="relative z-10 w-full max-w-lg rounded-xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-semibold text-gray-900">{{ actionLabel }}</h3>
                <p class="mt-1 text-xs text-gray-500">Remark is required.</p>

                <div v-if="form.action === 'transfer'" class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Transfer To User</label>
                    <select v-model="form.transfer_to_user_id" class="mt-1 w-full rounded border-gray-300">
                        <option disabled value="">Select user</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <p v-if="form.errors.transfer_to_user_id" class="mt-1 text-xs text-red-600">{{ form.errors.transfer_to_user_id }}</p>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Remark</label>
                    <textarea
                        v-model="form.remark"
                        rows="3"
                        class="mt-1 w-full rounded border-gray-300"
                        placeholder="Enter remark"
                    ></textarea>
                    <p v-if="form.errors.remark" class="mt-1 text-xs text-red-600">{{ form.errors.remark }}</p>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded border px-3 py-1.5 text-sm" @click="showModal = false">Cancel</button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-1.5 text-sm text-white disabled:opacity-50"
                        :disabled="form.processing"
                        @click="submit"
                    >
                        Submit
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
