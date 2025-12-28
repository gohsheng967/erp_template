<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    show: Boolean,
    task: Object,
    mode: {
        type: String,
        default: 'reviewer',
    },
})

const emit = defineEmits(['confirm', 'close'])

const status = ref('completed')
const remark = ref('')

watch(
    () => props.task,
    task => {
        if (task) {
            status.value = task.status || 'completed'
            remark.value = task.remark || ''
        }
    },
    { immediate: true }
)

function submit() {
    emit('confirm', {
        status: status.value,
        remark: remark.value,
    })
}
</script>

<template>
<div
    v-if="show"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
>
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">

        <!-- HEADER -->
        <div class="flex justify-between items-center px-6 py-4 border-b">
            <h3 class="text-lg font-semibold">
                {{ mode === 'assignee' ? 'Add Work Remark' : 'Review Task' }}
            </h3>
            <button @click="$emit('close')">✕</button>
        </div>

        <!-- BODY -->
        <div class="px-6 py-5 space-y-4">
            <!-- STATUS (REVIEWER ONLY) -->
            <div v-if="mode === 'reviewer'">
                <label class="text-sm font-medium">Status</label>
                <select v-model="status" class="border rounded px-3 py-2 w-full">
                    <option value="completed">Completed</option>
                    <option value="blocked">Blocked</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <!-- REMARK -->
            <div>
                <label class="text-sm font-medium">
                    {{ mode === 'assignee' ? 'Work Remark' : 'Review Remark' }}
                </label>
                <textarea
                    v-model="remark"
                    rows="3"
                    class="border rounded px-3 py-2 w-full"
                />
            </div>
        </div>

        <!-- FOOTER (SAVE BUTTON HERE) -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
            <button
                class="px-4 py-2 bg-gray-200 rounded"
                @click="$emit('close')"
            >
                Cancel
            </button>
            <button
                class="px-4 py-2 bg-indigo-600 text-white rounded"
                @click="submit"
            >
                Save
            </button>
        </div>

    </div>
</div>
</template>
