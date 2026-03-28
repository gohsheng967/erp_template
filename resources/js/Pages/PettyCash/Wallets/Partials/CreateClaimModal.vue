<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, inject, onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
    show: { type: Boolean, required: true },
    wallet: { type: Object, required: true },
    claimTypes: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['close', 'success'])
const toast = inject('toast', null)

const form = useForm({
    wallet_uuid: props.wallet.uuid,
    claim_type: '',
    title: '',
    description: '',
    receipt_no: '',
    amount: '',
    attachments: [],
})

const MAX_FILES = 3
const dragging = ref(false)
const previews = ref(new Map())

function getPreview(file) {
    if (!(file instanceof File)) return null

    if (!previews.value.has(file)) {
        previews.value.set(file, URL.createObjectURL(file))
    }

    return previews.value.get(file)
}

function cleanupPreviews() {
    previews.value.forEach((url) => URL.revokeObjectURL(url))
    previews.value.clear()
}

const canSubmit = computed(() => {
    return (
        form.claim_type &&
        form.title &&
        form.receipt_no &&
        Number(form.amount) > 0 &&
        form.attachments.length >= 1 &&
        !form.processing
    )
})

watch(
    () => props.wallet?.uuid,
    (uuid) => {
        if (!uuid) return
        form.wallet_uuid = uuid
    },
    { immediate: true }
)

function handleFiles(e) {
    const files = e?.target?.files || e?.dataTransfer?.files
    if (!files) return

    const incoming = Array.from(files).filter((f) => f instanceof File)

    const merged = [
        ...form.attachments.filter((f) => f instanceof File),
        ...incoming,
    ]

    if (merged.length > MAX_FILES) {
        toast?.value?.show('Maximum 3 attachments allowed', 'error')
    }

    form.attachments = merged.slice(0, MAX_FILES)
}

function removeFile(index) {
    const file = form.attachments[index]
    if (previews.value.has(file)) {
        URL.revokeObjectURL(previews.value.get(file))
        previews.value.delete(file)
    }
    form.attachments.splice(index, 1)
}

function isImage(file) {
    return file instanceof File && file.type.startsWith('image/')
}

function fileSize(size) {
    return (size / 1024 / 1024).toFixed(2) + ' MB'
}

function submit() {
    if (!canSubmit.value) {
        toast?.value?.show('Please complete all required fields', 'warning')
        return
    }

    form.post(route('petty-cash.wallets.store.claims'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            toast?.value?.show('Claim created successfully')
            emit('success')
            close()
        },
    })
}

function close() {
    cleanupPreviews()
    form.reset()
    emit('close')
}

function onKeydown(e) {
    if (e.key === 'Escape') close()
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => {
    cleanupPreviews()
    window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
        @click.self="close"
    >
        <div class="bg-white w-full max-w-2xl rounded-lg shadow-xl max-h-[90vh] flex flex-col">
            <div class="px-5 py-3 border-b flex justify-between items-center shrink-0">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">
                        Create Petty Cash Claim
                    </h3>
                    <p class="text-xs text-gray-500">
                        Wallet: {{ wallet.name }}
                    </p>
                </div>
                <button @click="close" class="text-gray-400 hover:text-gray-700">
                    <i class="mdi mdi-close text-xl leading-none"></i>
                </button>
            </div>

            <div class="p-5 space-y-4 overflow-y-auto">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Claim Type <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.claim_type"
                        class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Select type</option>
                        <option
                            v-for="(label, key) in claimTypes"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Title <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.title"
                        class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Receipt No <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="form.receipt_no"
                        class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Amount <span class="text-red-500">*</span>
                    </label>
                    <input
                        type="number"
                        step="0.01"
                        v-model="form.amount"
                        class="mt-1 h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Attachments <span class="text-red-500">*</span>
                        <span class="text-gray-400 normal-case font-medium">(1-3 files)</span>
                    </label>

                    <label
                        class="mt-2 flex items-center justify-center h-24 border-2 border-dashed rounded-lg cursor-pointer bg-gray-50"
                        :class="dragging ? 'border-indigo-500 bg-indigo-100' : 'border-gray-300'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false"
                    >
                        <span class="text-xs text-gray-600">
                            Click or drag files here
                        </span>
                        <input
                            type="file"
                            multiple
                            accept="image/*,application/pdf"
                            class="hidden"
                            @change="handleFiles"
                        />
                    </label>

                    <div v-if="form.attachments.length" class="mt-3 space-y-2">
                        <div
                            v-for="(file, index) in form.attachments"
                            :key="index"
                            class="flex items-center gap-3 p-2.5 border rounded-lg"
                        >
                            <div class="w-10 h-10 border rounded bg-gray-100 overflow-hidden">
                                <img
                                    v-if="isImage(file) && getPreview(file)"
                                    :src="getPreview(file)"
                                    class="object-cover w-full h-full"
                                />
                                <span v-else class="text-xs font-semibold text-red-600 flex items-center justify-center h-full">
                                    PDF
                                </span>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium truncate">
                                    {{ file.name }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ fileSize(file.size) }}
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="removeFile(index)"
                                class="text-xs text-red-500 hover:text-red-700"
                            >
                                Remove
                            </button>
                        </div>
                    </div>

                    <div class="mt-2 text-xs text-gray-500">
                        {{ form.attachments.length }} / {{ MAX_FILES }} files attached
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 border-t bg-gray-50 flex justify-end gap-2 shrink-0">
                <button
                    @click="close"
                    class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                >
                    Cancel
                </button>
                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="rounded-md px-3 py-1.5 text-xs font-semibold text-white"
                    :class="canSubmit ? 'bg-indigo-600' : 'bg-indigo-300'"
                >
                    {{ form.processing ? 'Creating...' : 'Create Claim' }}
                </button>
            </div>
        </div>
    </div>
</template>



