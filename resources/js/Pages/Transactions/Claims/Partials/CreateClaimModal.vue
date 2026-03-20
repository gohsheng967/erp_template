<script setup>
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    show: Boolean,
    projects: Array,
})

const emit = defineEmits(['close'])

const form = useForm({
    project_id: '',
    title:'',
    total_amount: '',
})

function submit() {
    form.post(route('claims.store'), {
        onSuccess: () => {
            emit('close')
        }
    })
}
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="bg-white rounded-lg shadow-lg w-full max-w-md z-10 p-6">
            <h3 class="text-base font-semibold mb-3">
                Create Claim (Draft)
            </h3>

            <p class="text-sm text-gray-500 mb-4">
                You can add claim items after creating the claim.
            </p>

            <div class="space-y-4">

                <!-- PROJECT -->
                <div>
                    <label class="text-sm font-medium">Project</label>
                    <select
                        v-model="form.project_id"
                        class="h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Others / No Project</option>
                        <option
                            v-for="p in projects"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}
                        </option>
                    </select>
                </div>
                <!-- TITLE -->
                <div>
                    <label class="text-sm font-medium">Title</label>
                    <input
                        type="text"
                        v-model="form.title"
                        class="h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="UAT Claim"
                    />
                    <div
                        v-if="form.errors.title"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.title }}
                    </div>
                </div>

                <!-- TOTAL AMOUNT -->
                <div>
                    <label class="text-sm font-medium">Total Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        v-model="form.total_amount"
                        class="h-9 w-full rounded-lg border border-slate-300 px-2.5 text-xs focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="0.00"
                    />
                    <div
                        v-if="form.errors.total_amount"
                        class="text-sm text-red-500"
                    >
                        {{ form.errors.total_amount }}
                    </div>
                </div>

            </div>

            <!-- ACTIONS -->
            <div class="mt-6 flex justify-end gap-3">
                <button
                    @click="emit('close')"
                    class="rounded-md bg-gray-200 px-3 py-1.5 text-xs font-semibold hover:bg-gray-300"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700"
                    :disabled="form.processing"
                >
                    Create Draft
                </button>
            </div>
        </div>
    </div>
</template>
