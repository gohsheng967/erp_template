<script setup>
import { useForm } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
    show: Boolean,
    category: Object,
    allCategories: Array
});

const emit = defineEmits(["close"]);
const extensionOptions = [
    { value: "pdf", label: "PDF" },
    { value: "doc", label: "DOC" },
    { value: "docx", label: "DOCX" },
    { value: "xls", label: "XLS" },
    { value: "xlsx", label: "XLSX" },
    { value: "png", label: "PNG" },
    { value: "jpg", label: "JPG" },
    { value: "jpeg", label: "JPEG" },
]

const visibilityOptions = [
    { value: "public", label: "Public", description: "Available to all users." },
    { value: "department", label: "Department", description: "Restricted by department." },
    { value: "role", label: "Role", description: "Restricted by role." },
]

const customExtensions = ref("")

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

function normalizeExtensions(value) {
    if (Array.isArray(value)) {
        return value.map((ext) => String(ext).trim().toLowerCase()).filter(Boolean)
    }

    if (typeof value === "string") {
        return value
            .split(",")
            .map((ext) => ext.trim().toLowerCase())
            .filter(Boolean)
    }

    return []
}

function parseCustomExtensions(value) {
    return String(value ?? "")
        .split(",")
        .map((ext) => ext.trim().toLowerCase().replace(/^\./, ""))
        .filter(Boolean)
}

// If editing, preload values
watch(
    () => props.category,
    (cat) => {
        if (cat) {
            form.name = cat.name;
            form.parent_id = cat.parent_id;
            const normalized = normalizeExtensions(cat.allowed_extensions)
            form.allowed_extensions = normalized.filter((ext) => extensionOptions.some((opt) => opt.value === ext))
            customExtensions.value = normalized
                .filter((ext) => !extensionOptions.some((opt) => opt.value === ext))
                .join(", ")
            form.max_size = cat.max_size;
            form.visibility = cat.visibility;
            form.allowed_departments = cat.allowed_departments ?? [];
            form.allowed_roles = cat.allowed_roles ?? [];
        } else {
            form.reset();
            customExtensions.value = ""
        }
    },
    { immediate: true }
);

function save() {
    const selected = Array.isArray(form.allowed_extensions)
        ? form.allowed_extensions
            .map((ext) => String(ext).trim().toLowerCase().replace(/^\./, ""))
            .filter(Boolean)
        : []
    const custom = parseCustomExtensions(customExtensions.value)

    // Rebuild from current UI state only to avoid stale values on edit.
    form.allowed_extensions = Array.from(new Set([...selected, ...custom]))

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
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/40 p-4"
    >
        <div class="mx-auto my-8 w-full max-w-2xl rounded bg-white p-6 shadow max-h-[calc(100vh-4rem)] flex flex-col">
            
            <h2 class="text-lg font-semibold mb-4">
                {{ category ? "Edit Category" : "New Category" }}
            </h2>

            <!-- FORM -->
            <div class="space-y-4 overflow-y-auto pr-1">

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
                    <div class="grid grid-cols-2 gap-2 rounded border border-gray-200 p-3 sm:grid-cols-4">
                        <label
                            v-for="ext in extensionOptions"
                            :key="ext.value"
                            class="inline-flex items-center gap-2 rounded border border-gray-200 px-2 py-1.5 text-sm hover:bg-gray-50"
                        >
                            <input
                                v-model="form.allowed_extensions"
                                :value="ext.value"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span>{{ ext.label }}</span>
                        </label>
                    </div>

                    <input
                        v-model="customExtensions"
                        placeholder="Other extensions (comma separated), e.g. zip, csv"
                        class="input mt-2"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Use this for formats not listed above (example: zip, csv, dwg).
                    </p>
                </div>

                <!-- Size -->
                <div>
                    <label class="block text-gray-700 mb-1">Max Size (KB)</label>
                    <input v-model="form.max_size" type="number" class="input" />
                </div>

                <!-- Permission -->
                <div>
                    <label class="block text-gray-700 mb-1">Visibility</label>
                    <div class="space-y-2 rounded border border-gray-200 p-3">
                        <label
                            v-for="option in visibilityOptions"
                            :key="option.value"
                            class="flex cursor-pointer items-start gap-3 rounded border px-3 py-2 hover:bg-gray-50"
                            :class="form.visibility === option.value ? 'border-blue-400 bg-blue-50' : 'border-gray-200'"
                        >
                            <input
                                v-model="form.visibility"
                                :value="option.value"
                                type="radio"
                                class="mt-1 h-4 w-4 border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span>
                                <span class="block text-sm font-medium text-gray-800">{{ option.label }}</span>
                                <span class="block text-xs text-gray-500">{{ option.description }}</span>
                            </span>
                        </label>
                    </div>
                </div>

            </div>

            <!-- ACTIONS -->
            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-4">
                <button @click="close" class="form-btn form-btn-secondary">Cancel</button>

                <button
                    @click="save"
                    class="form-btn form-btn-primary"
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

.form-btn {
    @apply inline-flex h-9 items-center justify-center rounded-md px-4 text-sm font-medium transition;
}

.form-btn-primary {
    @apply bg-blue-600 text-white hover:bg-blue-700;
}

.form-btn-secondary {
    @apply bg-gray-200 text-gray-700 hover:bg-gray-300;
}
</style>
