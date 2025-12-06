<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch } from "vue";

const props = defineProps({
    show: Boolean,
    category: Object,
    allCategories: Array
});

const emit = defineEmits(["close"]);

// Form
const form = useForm({
    name: "",
    parent_id: null,
    allowed_extensions: [],
    max_size: 5000,
    visibility: "public",
    allowed_departments: [],
    allowed_roles: []
});

// If editing, preload values
watch(
    () => props.category,
    (cat) => {
        if (cat) {
            form.name = cat.name;
            form.parent_id = cat.parent_id;
            form.allowed_extensions = cat.allowed_extensions ?? [];
            form.max_size = cat.max_size;
            form.visibility = cat.visibility;
            form.allowed_departments = cat.allowed_departments ?? [];
            form.allowed_roles = cat.allowed_roles ?? [];
        } else {
            form.reset();
        }
    },
    { immediate: true }
);

function save() {
    if (props.category) {
        form.patch(route("file-categories.update", props.category.id), {
            onSuccess: () => emit("close")
        });
    } else {
        form.post(route("file-categories.store"), {
            onSuccess: () => emit("close")
        });
    }
}

function close() {
    emit("close");
}
</script>

<template>
    <div class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white w-full max-w-lg rounded shadow p-6">
            
            <h2 class="text-lg font-semibold mb-4">
                {{ category ? "Edit Category" : "New Category" }}
            </h2>

            <!-- FORM -->
            <div class="space-y-4">

                <!-- Name -->
                <div>
                    <label class="block text-gray-700 mb-1">Name</label>
                    <input v-model="form.name" class="input" type="text" />
                </div>

                <!-- Parent -->
                <div>
                    <label class="block text-gray-700 mb-1">Parent Category</label>
                    <select v-model="form.parent_id" class="input">
                        <option :value="null">None</option>
                        <option v-for="c in allCategories" :key="c.id" :value="c.id">
                            {{ c.name }}
                        </option>
                    </select>
                </div>

                <!-- Extensions -->
                <div>
                    <label class="block text-gray-700 mb-1">Allowed Extensions</label>
                    <input
                        v-model="form.allowed_extensions"
                        placeholder="pdf, png, docx"
                        class="input"
                        @change="form.allowed_extensions = form.allowed_extensions.split(',').map(e => e.trim())"
                    />
                </div>

                <!-- Size -->
                <div>
                    <label class="block text-gray-700 mb-1">Max Size (KB)</label>
                    <input v-model="form.max_size" type="number" class="input" />
                </div>

                <!-- Permission -->
                <div>
                    <label class="block text-gray-700 mb-1">Visibility</label>
                    <select v-model="form.visibility" class="input">
                        <option value="public">Public</option>
                        <option value="department">Department Only</option>
                        <option value="role">Role Based</option>
                    </select>
                </div>

            </div>

            <!-- ACTIONS -->
            <div class="mt-6 flex justify-end gap-2">
                <button @click="close" class="px-4 py-2 bg-gray-200 rounded">Cancel</button>

                <button
                    @click="save"
                    class="px-4 py-2 bg-blue-600 text-white rounded"
                >
                    Save
                </button>
            </div>
        </div>
    </div>
</template>

<style>
.input {
    @apply w-full border rounded px-3 py-2 text-sm;
}
</style>
