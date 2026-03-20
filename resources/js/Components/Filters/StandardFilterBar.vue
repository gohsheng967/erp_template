<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'Filters',
    },
    description: {
        type: String,
        default: '',
    },
    applyLabel: {
        type: String,
        default: 'Apply',
    },
    resetLabel: {
        type: String,
        default: 'Reset',
    },
})

const emit = defineEmits(['apply', 'reset'])
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
                <p v-if="description" class="text-xs text-slate-500 mt-0.5">{{ description }}</p>
            </div>
            <slot name="header-actions" />
        </div>

        <div class="p-4">
            <div class="flex flex-wrap items-end gap-4">
                <slot />
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="h-9 rounded-md border border-slate-300 px-3 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    @click="emit('reset')"
                >
                    {{ resetLabel }}
                </button>
                <button
                    type="button"
                    class="h-9 rounded-md bg-slate-900 px-3 text-xs font-medium text-white hover:bg-slate-800"
                    @click="emit('apply')"
                >
                    {{ applyLabel }}
                </button>
            </div>
        </div>
    </div>
</template>
