<script setup>
import { watch, inject  } from 'vue'
import { useForm } from '@inertiajs/vue3'
const toast = inject('toast', null)

const props = defineProps({
    show: Boolean,
    warehouse: Object,
})

const emit = defineEmits(['close'])

const form = useForm({
    title: '',
    address: '',
})

watch(
    () => props.warehouse,
    (warehouse) => {
        form.reset()

        if (warehouse) {
            form.title = warehouse.title
            form.address = warehouse.address
        }
    },
    { immediate: true }
)

function submit() {
    if (props.warehouse) {
        form.put(route('warehouses.update', props.warehouse.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Warehouse / Office updated', 'success')
                emit('close')
            },
            onError: () => {
                toast?.value?.show('Failed to update warehouse / office', 'error')
            },
        })
    } else {
        form.post(route('warehouses.store'), {
            preserveScroll: true,
            onSuccess: () => {
                toast?.value?.show('Warehouse / Office created', 'success')
                emit('close')
            },
            onError: () => {
                toast?.value?.show('Failed to create warehouse / office', 'error')
            },
        })
    }
}

</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center"
    >
        <div class="absolute inset-0 bg-black bg-opacity-40"></div>

        <div class="bg-white rounded-lg shadow-lg w-full max-w-md z-10 p-6">
            <h3 class="text-lg font-semibold mb-4">
                {{ warehouse ? 'Edit Warehouse' : 'Add Warehouse' }}
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Warehouse / Office Name
                    </label>
                    <input
                        v-model="form.title"
                        type="text"
                        class="mt-1 w-full rounded border-gray-300"
                    />
                    <p v-if="form.errors.title" class="text-sm text-red-600">
                        {{ form.errors.title }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Location
                    </label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="mt-1 w-full rounded border-gray-300"
                    ></textarea>
                    <p v-if="form.errors.address" class="text-sm text-red-600">
                        {{ form.errors.address }}
                    </p>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-2">
                <button
                    @click="emit('close')"
                    class="px-4 py-2 border rounded"
                >
                    Cancel
                </button>

                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</template>
