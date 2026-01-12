<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

/* =====================
   PROPS / EMITS
===================== */
const props = defineProps({
    modelValue: Boolean,
    user: Object,
    departments: Array,
    rolesByDept: Object,
});

const emit = defineEmits([
    "update:modelValue",
    "updated",
]);

/* =====================
   FLAGS
===================== */
const isProtected = computed(() => !!props.user?.is_protected);

/* =====================
   DEPARTMENT / ROLE ROWS
===================== */
const rows = ref([]);

watch(
    () => props.user,
    (u) => {
        if (!u) return;

        rows.value = u.assignments.map(a => ({
            department_id: a.department_id,
            role_id: a.role_id,
        }));
    },
    { immediate: true }
);

/* =====================
   FORM
===================== */
const form = useForm({
    identity_no: "",
    name: "",
    email: "",
    status: 1,
    department_roles: [],
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

/* =====================
   ROW ACTIONS
===================== */
function addRow() {
    if (isProtected.value) return;
    rows.value.push({ department_id: "", role_id: "" });
}

function removeRow(index) {
    if (isProtected.value) return;
    rows.value.splice(index, 1);
}

/* =====================
   SUBMIT
===================== */
function submit() {
    if (isProtected.value) return;

    form.department_roles = rows.value;

    form.patch(route("users.update", props.user.id), {
        preserveScroll: true,
        onSuccess: () => {
            emit("update:modelValue", false);
            emit("updated"); // 👈 parent refresh
        },
    });
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40"
    >
        <div class="bg-white w-full max-w-lg rounded-lg shadow p-6 relative">

            <!-- CLOSE -->
            <button
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-700"
                @click="emit('update:modelValue', false)"
            >
                ✕
            </button>

            <h2 class="text-xl font-semibold mb-1">
                Edit User
                <span
                    v-if="isProtected"
                    class="text-sm text-red-600 ml-2"
                >
                    (Super Admin Locked)
                </span>
            </h2>

            <p
                v-if="isProtected"
                class="text-xs text-gray-500 mb-4"
            >
                Super Admin accounts cannot be modified.
            </p>

            <!-- IDENTITY -->
            <div class="mb-4">
                <label class="label">Identity No</label>
                <input
                    v-model="form.identity_no"
                    class="input"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.identity_no" class="error">
                    {{ form.errors.identity_no }}
                </div>
            </div>

            <!-- NAME -->
            <div class="mb-4">
                <label class="label">Name</label>
                <input
                    v-model="form.name"
                    class="input"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.name" class="error">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- EMAIL -->
            <div class="mb-4">
                <label class="label">Email</label>
                <input
                    v-model="form.email"
                    type="email"
                    class="input"
                    :disabled="isProtected"
                />
                <div v-if="form.errors.email" class="error">
                    {{ form.errors.email }}
                </div>
            </div>

            <!-- STATUS -->
            <div class="mb-4">
                <label class="label">Status</label>
                <select
                    v-model="form.status"
                    class="input"
                    :disabled="isProtected"
                >
                    <option :value="1">Active</option>
                    <option :value="0">Suspended</option>
                </select>
            </div>

            <!-- DEPARTMENT / ROLE -->
            <h3 class="text-sm font-semibold mt-2 mb-2">
                Department & Role
            </h3>

            <div
                v-for="(row, index) in rows"
                :key="index"
                class="flex gap-3 items-center mb-3"
            >
                <select
                    v-model="row.department_id"
                    class="input w-1/2"
                    :disabled="isProtected"
                >
                    <option value="">Department</option>
                    <option
                        v-for="d in departments"
                        :key="d.id"
                        :value="d.id"
                    >
                        {{ d.name }}
                    </option>
                </select>

                <select
                    v-model="row.role_id"
                    class="input w-1/2"
                    :disabled="isProtected"
                >
                    <option value="">Role</option>
                    <option
                        v-for="r in rolesByDept[row.department_id] ?? []"
                        :key="r.id"
                        :value="r.id"
                    >
                        {{ r.name }}
                    </option>
                </select>

                <button
                    v-if="rows.length > 1"
                    class="text-red-600"
                    @click="removeRow(index)"
                    :disabled="isProtected"
                >
                    ✕
                </button>
            </div>

            <button
                class="text-indigo-600 text-sm"
                @click="addRow"
                :disabled="isProtected"
            >
                + Add Department & Role
            </button>

            <!-- ACTION -->
            <div class="mt-6 text-right">
                <button
                    @click="submit"
                    :disabled="form.processing || isProtected"
                    class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:bg-gray-400"
                >
                    Save Changes
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.label {
    @apply text-sm font-medium text-gray-700;
}
.input {
    @apply w-full border rounded px-3 py-2;
}
.error {
    @apply text-red-600 text-xs mt-1;
}
</style>
