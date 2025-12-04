<script setup>
const props = defineProps({
    show: Boolean,
    maxWidth: { type: String, default: "md" }
})

const emit = defineEmits(["close"])
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="emit('close')"
    >
        <div
            class="bg-white rounded-lg shadow-xl p-6 w-full"
            :class="{
                'max-w-md': props.maxWidth === 'md',
                'max-w-lg': props.maxWidth === 'lg',
                'max-w-xl': props.maxWidth === 'xl',
            }"
        >
            <slot />
        </div>
    </div>
</template>
