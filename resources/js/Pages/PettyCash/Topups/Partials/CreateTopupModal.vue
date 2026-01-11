<script setup>
import { useForm } from '@inertiajs/vue3'
import { inject } from 'vue'

const props = defineProps({
    show: Boolean,
    projects: {
        type: Array,
        required: true,
    },
})

const emit = defineEmits(['close'])

/* =========================
   TOAST
========================= */
const toast = inject('toast', null)

/* =========================
   FORM
========================= */
const form = useForm({
    context_type: 'office', // office | project
    project_id: '',
    amount: '',
    reason: '',
})

/* =========================
   HELPERS
========================= */
function onChangePurpose() {
    if (form.context_type === 'office') {
        form.project_id = ''
    }
}

/* =========================
   SUBMIT
========================= */
function submit() {
    form.post(route('petty-cash.topups.store'), {
        preserveScroll: true,

        onSuccess: () => {
            toast?.value?.show(
                'Top-up request submitted successfully.',
                'success'
            )

            form.reset()
            emit('close')
        },

        onError: () => {
            const first = Object.values(form.errors)?.[0]
            const msg = Array.isArray(first) ? first[0] : first

            toast?.value?.show(
                msg || 'Failed to submit top-up request.',
                'error'
            )
        },
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <!-- BACKDROP -->
        <div
            class="absolute inset-0 bg-black bg-opacity-40"
            @click="emit('close')"
        ></div>

        <!-- MODAL -->
        <div class="relative bg-white rounded-lg shadow-lg w-full max-w-md p-6 z-10">
            <h3 class="text-lg font-semibold mb-4">
                Request Petty Cash Top-Up
            </h3>

            <!-- ================= PURPOSE ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium block mb-2">
                    Top-Up Purpose
                </label>

                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="office"
                            v-model="form.context_type"
                            @change="onChangePurpose"
                        />
                        <span>Office</span>
                    </label>

                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="project"
                            v-model="form.context_type"
                            @change="onChangePurpose"
                        />
                        <span>Project</span>
                    </label>
                </div>
            </div>

            <!-- ================= PROJECT ================= -->
            <div
                v-if="form.context_type === 'project'"
                class="mb-4"
            >
                <label class="text-sm font-medium">
                    Project
                </label>

                <select
                    v-model="form.project_id"
                    class="input w-full"
                >
                    <option value="">Select Project</option>
                    <option
                        v-for="p in projects"
                        :key="p.id"
                        :value="p.id"
                    >
                        {{ p.name }}
                    </option>
                </select>
            </div>

            <!-- ================= AMOUNT ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium">
                    Amount
                </label>
                <input
                    v-model="form.amount"
                    type="number"
                    step="0.01"
                    class="input w-full"
                    placeholder="0.00"
                />
            </div>

            <!-- ================= REASON ================= -->
            <div class="mb-4">
                <label class="text-sm font-medium">
                    Reason
                </label>
                <textarea
                    v-model="form.reason"
                    class="input w-full"
                    rows="2"
                    placeholder="Optional"
                />
            </div>

            <!-- ================= ACTIONS ================= -->
            <div class="flex justify-end gap-3">
                <button
                    class="btn-outline"
                    @click="emit('close')"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    class="btn-primary"
                    @click="submit"
                >
                    Submit Request
                </button>
            </div>
        </div>
    </div>
</template>
