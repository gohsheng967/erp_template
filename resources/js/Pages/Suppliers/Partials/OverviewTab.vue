<script setup>
import { ref, inject } from 'vue'
import { router } from '@inertiajs/vue3'
import { useFormat } from '@/Composables/useFormat'

const toast = inject('toast', null)
const { formatCurrency } = useFormat()
const props = defineProps({
    supplier: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
})

const editing = ref(false)
const note = ref(props.supplier.internal_note || '')
const saving = ref(false)

function startEdit() {
    note.value = props.supplier.internal_note || ''
    editing.value = true
}

function cancelEdit() {
    editing.value = false
}

function saveNote() {
    if (saving.value) return

    saving.value = true

    router.patch(
        route('suppliers.update-note', props.supplier.uuid),
        { internal_note: note.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = false
                toast?.value?.show('Internal note updated', 'success')
            },
            onError: () => {
                toast?.value?.show('Failed to update note', 'error')
            },
            onFinish: () => {
                saving.value = false
            },
        }
    )
}
</script>

<template>
    <div class="space-y-6">

        <!-- Stats Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="bg-gray-50 border rounded-xl p-5">
                <p class="text-sm text-gray-500">Quotations</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800">
                    {{ stats.quotations }}
                </p>
            </div>

            <div class="bg-gray-50 border rounded-xl p-5">
                <p class="text-sm text-gray-500">Purchase Orders</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800">
                    {{ stats.orders }}
                </p>
            </div>

            <div class="bg-gray-50 border rounded-xl p-5">
                <p class="text-sm text-gray-500">Invoices</p>
                <p class="mt-1 text-2xl font-semibold text-gray-800">
                    {{ stats.invoices }}
                </p>
            </div>

            <div class="bg-gray-50 border rounded-xl p-5">
                <p class="text-sm text-gray-500">Total Spend</p>
                <p class="mt-1 text-2xl font-semibold text-indigo-600">
                    {{ formatCurrency(stats.total_spend) }}
                </p>
            </div>

        </div>

        <!-- Internal Notes -->
        <div class="bg-white border rounded-xl p-6">
            <div class="flex justify-between items-center mb-3">
                <h3 class="font-semibold text-gray-800">
                    Internal Notes
                </h3>

                <button
                    v-if="!editing"
                    @click="startEdit"
                    class="text-sm text-indigo-600 hover:underline"
                >
                    Edit
                </button>
            </div>

            <p class="text-sm text-gray-500 mb-2">
                For internal use only (finance / management).
            </p>

            <!-- View mode -->
            <div
                v-if="!editing"
                class="min-h-[80px] rounded-lg border border-dashed
                       px-4 py-3 text-sm text-gray-700 bg-gray-50"
            >
                {{ supplier.internal_note || 'No notes added yet.' }}
            </div>

            <!-- Edit mode -->
            <div v-else class="space-y-3">
                <textarea
                    v-model="note"
                    rows="4"
                    class="w-full rounded-lg border-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Enter internal notes..."
                />

                <div class="flex gap-2">
                    <button
                        @click="saveNote"
                        class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm hover:bg-indigo-700"
                    >
                        Save
                    </button>

                    <button
                        @click="cancelEdit"
                        class="px-4 py-2 rounded-lg border text-sm"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>
