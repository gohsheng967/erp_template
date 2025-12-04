<script setup>
import { watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { router } from '@inertiajs/vue3'

const props = defineProps({
    modelValue: Boolean,
    department: Object,
});
const emit = defineEmits(["update:modelValue"]);

const form = useForm({
    name: "",
});

// When department changes → load values
watch(
    () => props.department,
    (d) => {
        if (!d) return;
        form.name = d.name;
    },
    { immediate: true }
);

function submit() {
    form.patch(route("departments.update", props.department.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("update:modelValue");
            router.reload();
        }
    });
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50"
    >
        <div class="bg-white w-full max-w-md rounded-lg shadow p-6 relative">
            <button
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                @click="emit('update:modelValue', false)"
            >
                ✕
            </button>

            <h2 class="text-xl font-semibold mb-4">Edit Department</h2>

            <div class="mb-4">
                <label class="text-sm font-medium">Department Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full border rounded px-3 py-2"
                />
                <div
                    v-if="form.errors.name"
                    class="text-red-600 text-xs mt-1"
                >
                    {{ form.errors.name }}
                </div>
            </div>

            <div class="mt-6 text-right">
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700"
                >
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</template>
