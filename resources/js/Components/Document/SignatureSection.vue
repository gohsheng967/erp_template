<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    title: {
        type: String,
        default: 'Prepared Signature',
    },
    name: {
        type: String,
        default: '',
    },
})

const page = usePage()

const signatureUrl = computed(() => page.props.auth?.user?.data?.signature ?? null)
const signerName = computed(() => props.name || page.props.auth?.user?.data?.name || '-')

function onSignatureImageError(event) {
    const img = event?.target
    if (!img) return
    img.classList.add('hidden')
    const fallback = img.nextElementSibling
    if (fallback) fallback.classList.remove('hidden')
}
</script>

<template>
    <div class="mt-8">
        <div class="text-[11px] text-gray-500 mb-1">
            {{ title }}
        </div>

        <div class="h-14 flex items-end">
            <template v-if="signatureUrl">
                <img
                    :src="signatureUrl"
                    alt="User signature"
                    class="h-12 max-w-[180px] object-contain"
                    @error="onSignatureImageError"
                >
                <div class="hidden text-[11px] text-gray-400 italic">
                    No signature on file
                </div>
            </template>
            <div v-else class="text-[11px] text-gray-400 italic">
                No signature on file
            </div>
        </div>

        <div class="border-t border-gray-300 pt-1 text-[11px] text-gray-500">
            {{ signerName }}
        </div>
    </div>
</template>
