<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { router } from '@inertiajs/vue3'

const props = defineProps({
    modelValue: Boolean,
    user: Object,
    departments: Array,
    rolesByDept: Object,
});
const emit = defineEmits(["update:modelValue"]);

const isProtected = computed(() => props.user?.is_protected);

// rows setup from user.assignments
const rows = ref([]);

watch(
    () => props.user,
    (u) => {
        if (!u) return;

        rows.value = u.assignments.map(a => ({
            department_id: a.department_id,
            role_id: a.role_id
        }));
    },
    { immediate: true }
);

const form = useForm({
    identity_no: "",
    name: "",
    email: "",
    status: "",
    department_roles: rows.value
});

watch(
    () => props.user,
    (u) => {
        if (!u) return;

        form.identity_no = u.identity_no;
        form.name = u.name;
        form.email = u.email;
        form.status = u.status;
    },
    { immediate: true }
);

// Remove row only if not protected
function removeRow(index) {
    if (isProtected.value) return;
    rows.value.splice(index, 1);
}

function addRow() {
    if (isProtected.value) return;
    rows.value.push({ department_id: "", role_id: "" });
}

function submit() {
    if (isProtected.value) return;

    form.department_roles = rows.value;

    form.patch(route("users.update", props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("update:modelValue", false);
            router.reload({ only: ['users'] }); 
        }
    });
}
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">

        <div class="bg-white w-full max-w-lg rounded-lg shadow p-6 relative">
            <!-- Close -->
            <button
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                @click="emit('update:modelValue', false)"
            >✕</button>

            <h2 class="text-xl font-semibold mb-4">
                Edit User
                <span v-if="isProtected" class="text-red-600 text-sm">(Superadmin Locked)</span>
            </h2>

            <!-- Identity No -->
            <div class="mb-4">
                <label class="text-sm font-medium">Identity No</label>
                <input
                    v-model="form.identity_no"
                    type="text"
                    class="w-full border rounded px-3 py-2"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.identity_no" class="text-red-600 text-xs mt-1">
                    {{ form.errors.identity_no }}
                </div>
            </div>

            <!-- Name -->
            <div class="mb-4">
                <label class="text-sm font-medium">Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full border rounded px-3 py-2"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.name" class="text-red-600 text-xs mt-1">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Email -->
            <div class="mb-4">
                <label class="text-sm font-medium">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    class="w-full border rounded px-3 py-2"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.email" class="text-red-600 text-xs mt-1">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- STATUS -->
            <div class="mb-4">
                <label class="text-sm font-medium">Status</label>
                <select
                    v-model="form.status"
                    class="w-full border rounded px-3 py-2"
                    :disabled="isProtected"
                >
                    <option :value="1">Active</option>
                    <option :value="0">Suspended</option>
                </select>
            </div>

            <!-- DEPARTMENT-ROLE -->
            <h3 class="text-sm font-semibold mt-2 mb-2">Department & Role</h3>

            <div
                v-for="(row, index) in rows"
                :key="index"
                class="flex gap-3 items-center mb-3"
            >
                <!-- Department -->
                <select
                    v-model="row.department_id"
                    class="border rounded px-2 py-2 w-1/2"
                    :disabled="isProtected"
                >
                    <option value="">Select Department</option>
                    <option v-for="d in departments" :value="d.id">
                        {{ d.name }}
                    </option>
                </select>

                <!-- Role -->
                <select
                    v-model="row.role_id"
                    class="border rounded px-2 py-2 w-1/2"
                    :disabled="isProtected"
                >
                    <option value="">Select Role</option>
                    <option
                        v-for="r in rolesByDept[row.department_id] ?? []"
                        :value="r.id"
                    >
                        {{ r.name }}
                    </option>
                </select>

                <!-- Remove Row -->
                <button
                    v-if="rows.length > 1"
                    class="text-red-600 hover:text-red-800"
                    @click="removeRow(index)"
                    :disabled="isProtected"
                >
                    ✕
                </button>
            </div>

            <!-- Add Row -->
            <button
                class="text-indigo-600 hover:text-indigo-800 text-sm"
                @click="addRow"
                :disabled="isProtected"
            >
                + Add Department & Role
            </button>

            <!-- Submit -->
            <div class="mt-6 text-right">
                <button
                    @click="submit"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:bg-gray-400"
                    :disabled="form.processing || isProtected"
                >
                    Save Changes
                </button>
            </div>

        </div>

    </div>
</template>
