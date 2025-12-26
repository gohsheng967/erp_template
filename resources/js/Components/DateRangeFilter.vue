<script setup>
defineProps({
    from: String,
    to: String,
})

defineEmits(['update:from', 'update:to', 'filter'])
</script>

<template>
    <div class="flex gap-3 items-end">
        <div>
            <label class="text-sm text-gray-600">From</label>
            <input
                type="date"
                :value="from"
                @input="$emit('update:from', $event.target.value)"
                class="border rounded px-3 py-2"
            />
        </div>

        <div>
            <label class="text-sm text-gray-600">To</label>
            <input
                type="date"
                :value="to"
                @input="$emit('update:to', $event.target.value)"
                class="border rounded px-3 py-2"
            />
        </div>

        <button
            class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
            @click="$emit('filter')"
        >
            Filter
        </button>
    </div>
</template>
