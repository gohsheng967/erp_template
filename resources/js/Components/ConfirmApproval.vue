<script setup>
const props = defineProps({
    title: {
        type: String,
        default: 'Confirm Approval',
    },
    message: {
        type: String,
        default: 'Are you sure you want to proceed?',
    },
    confirmText: {
        type: String,
        default: 'Approve',
    },
})

const emit = defineEmits(['confirm', 'close'])
</script>

<template>
    <!-- BACKDROP -->
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">

        <!-- MODAL -->
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">

            <!-- HEADER -->
            <div class="flex items-center justify-between px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ title }}
                </h3>

                <button
                    class="text-gray-400 hover:text-gray-600"
                    @click="$emit('close')"
                >
                    ✕
                </button>
            </div>

            <!-- BODY -->
            <div class="px-6 py-8 text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 rounded-full bg-indigo-500 flex items-center justify-center">
                        <i class="mdi mdi-check-circle-outline text-white text-4xl"></i>
                    </div>
                </div>

                <p class="text-gray-700 text-base">
                    {{ message }}
                </p>
            </div>

            <!-- FOOTER -->
            <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50">
                <button
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
                    @click="$emit('close')"
                >
                    Cancel
                </button>

                <button
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                    @click="$emit('confirm')"
                >
                    {{ confirmText }}
                </button>
            </div>

        </div>
    </div>
</template>
